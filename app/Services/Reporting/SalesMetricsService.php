<?php

namespace App\Services\Reporting;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Intelligence\MetricDictionary;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for sales numbers. Reports, exports, the dashboard-style
 * summaries and Tanya Yoka all read through here so a figure can never differ
 * between surfaces.
 *
 * Rules that keep the money honest:
 *  - eligible = orders.status 'paid' inside the [start,end) window; refunded and
 *    cancelled orders are reported separately and never mixed into sales.
 *  - item-level, order-level and payment-level sums run as separate queries so a
 *    join (items x payments) can never multiply a total.
 *  - payment_collected is what the POS recorded, not gateway settlement.
 */
class SalesMetricsService
{
    public const EXCLUDED_STATUSES = ['refunded', 'cancelled'];

    public function __construct(private MetricDictionary $dictionary) {}

    // ── Summary ──────────────────────────────────────────────────────────────

    public function summary(SalesReportFilter $f): array
    {
        $o = $this->orders($f)->selectRaw(
            'COUNT(*) transactions, COALESCE(SUM(orders.subtotal),0) gross, COALESCE(SUM(orders.discount_amount),0) discounts,'
            .' COALESCE(SUM(orders.tax_amount),0) tax, COALESCE(SUM(orders.service_amount),0) service,'
            .' COALESCE(SUM(orders.delivery_fee),0) delivery, COALESCE(SUM(orders.grand_total),0) grand'
        )->first();

        $items = $this->orders($f)->join('order_items', 'order_items.order_id', '=', 'orders.id')->selectRaw(
            'COALESCE(SUM(order_items.subtotal),0) item_gross, COALESCE(SUM(order_items.qty*order_items.cost_price),0) cogs,'
            .' COALESCE(SUM(CASE WHEN order_items.cost_price > 0 THEN order_items.subtotal ELSE 0 END),0) costed, COALESCE(SUM(order_items.qty),0) qty'
        )->first();

        $n = (int) $o->transactions;
        $gross = $this->money($o->gross);
        $discounts = $this->money($o->discounts);
        $net = $this->money($gross - $discounts);
        $payments = $this->payments($f);
        $itemGross = $this->money($items->item_gross);
        $coverage = $itemGross > 0 ? round($items->costed / $itemGross * 100, 1) : null;
        $recordedTotal = $payments['total'];
        $expected = $this->money($net + $o->tax + $o->service + $o->delivery);

        $warnings = [];
        if ($n === 0) {
            $warnings[] = 'Belum ada transaksi paid pada filter ini.';
        }
        if ($n > 0 && abs($itemGross - $gross) > 1) {
            $warnings[] = 'Subtotal order dan jumlah item tidak sama persis; periksa order lama sebelum mengandalkan angka item.';
        }
        if ($n > 0 && ! $f->paymentMethods && abs($recordedTotal - $expected) > 1) {
            $warnings[] = 'Total pembayaran tercatat berbeda dari penjualan bersih + pajak + service + ongkir; kemungkinan data pembayaran tidak lengkap.';
        }
        if ($coverage !== null && $coverage < 100) {
            $warnings[] = 'Snapshot HPP hanya tersedia untuk '.$coverage.'% nilai item; estimasi laba kotor bersifat parsial.';
        }
        if ($f->categoryIds) {
            $warnings[] = 'Filter kategori hanya berlaku pada Produk dan Breakdown, bukan Ringkasan.';
        }
        $notes = [];
        if ($f->isPartialToday()) {
            $notes[] = 'Periode belum berakhir; angka mencakup data sampai '.$f->asOf->format('d M Y H:i').'.';
        }

        return [
            'metrics' => [
                'gross_item_sales' => $gross,
                'discounts' => $discounts,
                'net_sales' => $net,
                'tax_amount' => $this->money($o->tax),
                'service_amount' => $this->money($o->service),
                'delivery_fees' => $this->money($o->delivery),
                'order_grand_total' => $this->money($o->grand),
                'payment_collected' => $recordedTotal,
                'transactions' => $n,
                'aov' => $n > 0 ? $this->money($net / $n) : null,
                'quantity_sold' => (float) $items->qty,
                'cogs' => $this->money($items->cogs),
                'gross_profit_estimate' => $coverage === null ? null : $this->money($itemGross - $items->cogs),
                'cost_coverage_pct' => $coverage,
            ],
            'payments' => $payments['rows'],
            'excluded' => $this->excluded($f),
            'completeness' => $warnings && $n > 0 ? 'partial' : ($n === 0 ? 'empty' : 'complete'),
            'warnings' => $warnings,
            'notes' => $notes,
            'evidence' => $this->evidence($f),
        ];
    }

    /** Refunded / cancelled orders in the same window: reported, never summed into sales. */
    public function excluded(SalesReportFilter $f): array
    {
        $rows = $this->orders($f, 'orders', true, self::EXCLUDED_STATUSES)
            ->selectRaw('orders.status, COUNT(*) orders, COALESCE(SUM(orders.grand_total),0) amount')->groupBy('orders.status')->get()->keyBy('status');

        return array_map(fn ($s) => [
            'orders' => (int) ($rows[$s]->orders ?? 0),
            'amount' => $this->money($rows[$s]->amount ?? 0),
        ], ['refunded' => 'refunded', 'cancelled' => 'cancelled']);
    }

    // ── Payments ─────────────────────────────────────────────────────────────

    /**
     * One row per recorded payment line. A non-split order is one line carrying
     * grand_total; a split order contributes its real order_payments rows. Change
     * handed back to the customer is taken out of the cash line so cash is what
     * the till actually kept.
     */
    public function paymentLines(SalesReportFilter $f): Builder
    {
        $nonSplit = $this->orders($f, 'orders', false)->where('orders.is_split_payment', false)
            ->when($f->paymentMethods, fn ($q) => $q->whereIn('orders.payment_method', $f->paymentMethods))
            ->selectRaw('orders.id order_id, orders.outlet_id, orders.user_id, orders.created_at, orders.order_type, orders.payment_method method, orders.grand_total amount');

        $split = $this->orders($f, 'orders', false)->where('orders.is_split_payment', true)
            ->join('order_payments', 'order_payments.order_id', '=', 'orders.id')
            ->when($f->paymentMethods, fn ($q) => $q->whereIn('order_payments.payment_method', $f->paymentMethods))
            ->selectRaw("orders.id order_id, orders.outlet_id, orders.user_id, orders.created_at, orders.order_type, order_payments.payment_method method, CASE WHEN order_payments.payment_method = 'cash' THEN GREATEST(order_payments.amount - orders.change_amount, 0) ELSE order_payments.amount END amount");

        return DB::query()->fromSub($nonSplit->unionAll($split), 'pl');
    }

    public function payments(SalesReportFilter $f): array
    {
        $rows = $this->paymentLines($f)->selectRaw('method, COUNT(DISTINCT order_id) orders, SUM(amount) amount')->groupBy('method')->orderByDesc('amount')->get();
        $total = $this->money($rows->sum('amount'));

        return [
            'total' => $total,
            'rows' => $rows->map(fn ($r) => [
                'method' => $r->method, 'orders' => (int) $r->orders, 'amount' => $this->money($r->amount),
                'share_pct' => $total > 0 ? round($r->amount / $total * 100, 1) : null,
            ])->all(),
        ];
    }

    // ── Trends ───────────────────────────────────────────────────────────────

    public function trend(SalesReportFilter $f): array
    {
        $expr = $this->bucketExpr($f, 'orders.created_at', $f->grouping);
        $rows = $this->orders($f)->selectRaw("{$expr} bucket, COUNT(*) transactions, SUM(orders.subtotal) gross, SUM(orders.discount_amount) discounts")
            ->groupBy('bucket')->orderBy('bucket')->get()->keyBy('bucket');

        $points = [];
        foreach ($this->bucketKeys($f) as $key => $label) {
            $r = $rows[$key] ?? null;
            $gross = $r ? $this->money($r->gross) : 0.0;
            $disc = $r ? $this->money($r->discounts) : 0.0;
            $n = $r ? (int) $r->transactions : 0;
            $points[] = [
                'key' => (string) $key, 'label' => $label, 'transactions' => $n, 'gross_item_sales' => $gross,
                'discounts' => $disc, 'net_sales' => $this->money($gross - $disc), 'aov' => $n ? $this->money(($gross - $disc) / $n) : null,
            ];
        }
        // A zero point is only truthful when the query succeeded; with no orders at all
        // there is nothing to chart, so return no series instead of a flat line.
        $hasData = $rows->isNotEmpty();

        return ['grouping' => $f->grouping, 'timezone' => $f->timezone, 'points' => $hasData ? $points : []];
    }

    // ── Compare ──────────────────────────────────────────────────────────────

    public function compare(SalesReportFilter $f): ?array
    {
        $other = $f->comparisonFilter();
        if (! $other) {
            return null;
        }
        $cur = $this->summary($f)['metrics'];
        $prior = $this->summary($other)['metrics'];
        $deltas = [];
        foreach (['gross_item_sales', 'discounts', 'net_sales', 'payment_collected', 'transactions', 'aov', 'tax_amount'] as $k) {
            $deltas[$k] = self::delta($cur[$k], $prior[$k]);
        }

        return [
            'mode' => $f->comparison,
            'current' => $cur, 'prior' => $prior, 'deltas' => $deltas,
            'prior_label' => $f->comparison === 'outlets'
                ? 'Outlet pembanding' : $other->dateFrom().' – '.$other->dateTo(),
            'prior_trend' => $this->trend($other),
            'partial_note' => $f->comparison !== 'outlets' && $f->isPartialToday()
                ? 'Periode berjalan dibandingkan dengan periode penuh; pertumbuhan terlihat lebih rendah dari sebenarnya.' : null,
        ];
    }

    /** (current - prior) / |prior| x 100; N/A (pct null) when prior is 0 or either side is missing. */
    public static function delta(float|int|null $current, float|int|null $prior): array
    {
        if ($current === null || $prior === null) {
            return ['current' => $current, 'prior' => $prior, 'abs' => null, 'pct' => null];
        }

        return [
            'current' => $current, 'prior' => $prior, 'abs' => round($current - $prior, 2),
            'pct' => (float) $prior === 0.0 ? null : round(($current - $prior) / abs($prior) * 100, 1),
        ];
    }

    // ── Categories and product mix ──────────────────────────────────────────

    /** Order-level discount spread over items by their share of the order subtotal. */
    private const NET_ITEM = 'oi.subtotal - CASE WHEN o.subtotal > 0 THEN o.discount_amount * oi.subtotal / o.subtotal ELSE 0 END';

    public function categories(SalesReportFilter $f, int $limit = 10): array
    {
        $rows = $this->itemQuery($f)->leftJoin('products as p', 'p.id', '=', 'oi.product_id')->leftJoin('product_categories as c', 'c.id', '=', 'p.product_category_id')
            ->selectRaw('COALESCE(c.id,0) category_id, COALESCE(c.name,\'Tanpa kategori\') name, SUM(oi.qty) qty, SUM(oi.subtotal) gross, SUM('.self::NET_ITEM.') net')
            ->groupBy('category_id', 'name')->orderByDesc('net')->limit($limit)->get();

        return $rows->map(fn ($r) => ['category_id' => (int) $r->category_id, 'name' => $r->name, 'quantity' => (float) $r->qty, 'gross_item_sales' => $this->money($r->gross), 'net_sales' => $this->money($r->net)])->all();
    }

    public function productMix(SalesReportFilter $f, string $sort = 'net_sales', string $dir = 'desc', int $perPage = 25)
    {
        $sorts = ['net_sales' => 'net', 'gross_item_sales' => 'gross', 'quantity' => 'qty', 'name' => 'name', 'cogs' => 'cogs'];
        $col = $sorts[$sort] ?? 'net';

        $q = $this->itemQuery($f)->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->when($f->categoryIds, fn ($q) => $q->whereIn('p.product_category_id', $f->categoryIds))
            ->selectRaw('oi.product_id, oi.product_name name, oi.variant_name variant, SUM(oi.qty) qty, SUM(oi.subtotal) gross, SUM('.self::NET_ITEM.') net,'
                .' SUM(oi.qty*oi.cost_price) cogs, SUM(CASE WHEN oi.cost_price > 0 THEN oi.subtotal ELSE 0 END) costed')
            ->groupBy('oi.product_id', 'oi.product_name', 'oi.variant_name')
            ->orderBy($col, $dir === 'asc' ? 'asc' : 'desc')->orderBy('name');

        return $q->paginate($perPage)->through(function ($r) {
            $gross = (float) $r->gross;
            $coverage = $gross > 0 ? round($r->costed / $gross * 100, 1) : null;

            return [
                'product_id' => $r->product_id, 'matched' => $r->product_id !== null, 'name' => $r->name, 'variant' => $r->variant,
                'quantity' => (float) $r->qty, 'gross_item_sales' => $this->money($gross),
                'discount_allocated' => $this->money($gross - $r->net), 'net_sales' => $this->money($r->net),
                'cogs' => $this->money($r->cogs), 'cost_coverage_pct' => $coverage,
                'gross_margin_estimate' => $coverage === 100.0 ? $this->money($r->net - $r->cogs) : null,
            ];
        });
    }

    // ── Two-dimension breakdown ──────────────────────────────────────────────

    public const DIMENSIONS = [
        'category' => 'Kategori', 'product' => 'Produk', 'outlet' => 'Outlet', 'cashier' => 'Kasir', 'payment_method' => 'Metode bayar',
        'day' => 'Hari', 'weekday' => 'Hari dalam minggu', 'hour' => 'Jam', 'order_type' => 'Tipe layanan',
    ];

    public const METRICS = ['net_sales' => 'Penjualan bersih', 'gross_item_sales' => 'Penjualan kotor item', 'quantity' => 'Qty terjual', 'orders' => 'Jumlah order', 'payment_collected' => 'Pembayaran tercatat'];

    private const ITEM_DIMS = ['category', 'product'];

    private const MAX_CELLS = 5000;

    public function breakdown(SalesReportFilter $f, string $rowDim, string $colDim, string $metric): array
    {
        foreach ([$rowDim, $colDim] as $d) {
            if (! isset(self::DIMENSIONS[$d])) {
                throw ValidationException::withMessages(['row' => 'Dimensi tidak dikenal.']);
            }
        }
        if (! isset(self::METRICS[$metric])) {
            throw ValidationException::withMessages(['metric' => 'Metrik tidak dikenal.']);
        }
        if ($rowDim === $colDim) {
            throw ValidationException::withMessages(['col' => 'Pilih dua dimensi yang berbeda.']);
        }
        $dims = [$rowDim, $colDim];
        $hasItem = (bool) array_intersect($dims, self::ITEM_DIMS);
        $hasPay = in_array('payment_method', $dims, true);
        $source = match (true) {
            $metric === 'payment_collected' || $hasPay => 'payments',
            $hasItem || $metric === 'quantity' => 'items',
            default => 'orders',
        };
        if ($source === 'payments' && ($hasItem || $metric === 'quantity' || $metric === 'net_sales' || $metric === 'gross_item_sales')) {
            throw ValidationException::withMessages(['metric' => 'Metode bayar tidak dapat diatribusikan ke item atau penjualan bersih karena alokasi pembayaran per item tidak tercatat. Gunakan metrik Pembayaran tercatat atau Jumlah order.']);
        }
        if ($source !== 'payments' && $metric === 'payment_collected') {
            throw ValidationException::withMessages(['metric' => 'Pembayaran tercatat tidak dapat dipecah per item.']);
        }

        if ($source === 'items') {
            $builder = $this->itemQuery($f)->leftJoin('products as p', 'p.id', '=', 'oi.product_id')->leftJoin('product_categories as c', 'c.id', '=', 'p.product_category_id')
                ->when($f->categoryIds, fn ($q) => $q->whereIn('p.product_category_id', $f->categoryIds));
            $alias = 'o';
            $valueExpr = match ($metric) {
                'quantity' => 'SUM(oi.qty)', 'gross_item_sales' => 'SUM(oi.subtotal)', 'orders' => 'COUNT(DISTINCT o.id)', default => 'SUM('.self::NET_ITEM.')',
            };
        } elseif ($source === 'payments') {
            $builder = $this->paymentLines($f);
            $alias = 'pl';
            $valueExpr = $metric === 'orders' ? 'COUNT(DISTINCT pl.order_id)' : 'SUM(pl.amount)';
        } else {
            $builder = $this->scope(DB::table('orders as o'), $f, 'o');
            $alias = 'o';
            $valueExpr = match ($metric) {
                'gross_item_sales' => 'SUM(o.subtotal)', 'orders' => 'COUNT(*)', default => 'SUM(o.subtotal - o.discount_amount)',
            };
        }

        $selects = [];
        $groups = [];
        foreach (['r' => $rowDim, 'c' => $colDim] as $short => $dim) {
            [$key, $label] = $this->dimExpr($f, $dim, $alias);
            $selects[] = "{$key} {$short}_key";
            $groups[] = "{$short}_key";
            if ($label) {
                $selects[] = "{$label} {$short}_label";
                $groups[] = "{$short}_label";
            }
        }
        $cells = $builder->selectRaw(implode(', ', $selects).", {$valueExpr} value")->groupBy($groups)->limit(self::MAX_CELLS)->get();

        return $this->pivot($f, $cells, $rowDim, $colDim, $metric, $source);
    }

    private function dimExpr(SalesReportFilter $f, string $dim, string $o): array
    {
        $local = $this->bucketLocal($f, "{$o}.created_at");
        $created = "{$o}.created_at";

        return match ($dim) {
            'category' => ['COALESCE(c.id,0)', 'COALESCE(c.name,\'Tanpa kategori\')'],
            'product' => ['oi.product_name', null],
            'outlet' => ["{$o}.outlet_id", null],
            'cashier' => ["{$o}.user_id", null],
            'payment_method' => ['pl.method', null],
            'order_type' => ["{$o}.order_type", null],
            'day' => ["DATE({$local})", null],
            'weekday' => ["WEEKDAY({$local})", null],
            'hour' => ["HOUR({$local})", null],
        };
    }

    private function pivot(SalesReportFilter $f, $cells, string $rowDim, string $colDim, string $metric, string $source): array
    {
        $rowLabels = $this->labels($f, $rowDim, $cells->pluck('r_key')->unique()->all(), $cells->pluck('r_label', 'r_key')->all());
        $colLabels = $this->labels($f, $colDim, $cells->pluck('c_key')->unique()->all(), $cells->pluck('c_label', 'c_key')->all());
        $matrix = [];
        foreach ($cells as $c) {
            $matrix[(string) $c->r_key][(string) $c->c_key] = $this->money($c->value);
        }
        $rowTotals = array_map('array_sum', $matrix);
        arsort($rowTotals);
        $colTotals = [];
        foreach ($matrix as $cols) {
            foreach ($cols as $k => $v) {
                $colTotals[$k] = ($colTotals[$k] ?? 0) + $v;
            }
        }
        arsort($colTotals);
        $grand = $this->money(array_sum($rowTotals));
        $rows = [];
        foreach (array_slice(array_keys($rowTotals), 0, 50, true) as $k) {
            $rows[] = ['key' => (string) $k, 'label' => $rowLabels[$k] ?? (string) $k, 'total' => $this->money($rowTotals[$k]), 'pct' => $grand > 0 ? round($rowTotals[$k] / $grand * 100, 1) : null, 'cells' => $matrix[$k]];
        }
        $cols = [];
        foreach (array_slice(array_keys($colTotals), 0, 12, true) as $k) {
            $cols[] = ['key' => (string) $k, 'label' => $colLabels[$k] ?? (string) $k, 'total' => $this->money($colTotals[$k])];
        }

        return [
            'row_dimension' => $rowDim, 'col_dimension' => $colDim, 'metric' => $metric, 'source' => $source,
            'rows' => $rows, 'columns' => $cols, 'grand_total' => $grand,
            'truncated' => count($rowTotals) > 50 || count($colTotals) > 12 || $cells->count() >= self::MAX_CELLS,
            'evidence' => $this->evidence($f),
        ];
    }

    private function labels(SalesReportFilter $f, string $dim, array $keys, array $given): array
    {
        $out = [];
        $names = match ($dim) {
            'outlet' => Outlet::where('business_id', $f->businessId)->whereIn('id', $keys)->pluck('name', 'id')->all(),
            'cashier' => User::where('business_id', $f->businessId)->whereIn('id', $keys)->pluck('name', 'id')->all(),
            default => [],
        };
        $weekdays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        $types = ['dine_in' => 'Dine-in', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery'];
        $methods = ['cash' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer', 'ewallet' => 'E-wallet', 'debit' => 'Debit', 'other' => 'Lainnya'];
        foreach ($keys as $k) {
            $out[$k] = match ($dim) {
                'outlet', 'cashier' => $names[$k] ?? '#'.$k,
                'weekday' => $weekdays[(int) $k] ?? (string) $k,
                'hour' => sprintf('%02d:00', (int) $k),
                'order_type' => $types[$k] ?? (string) $k,
                'payment_method' => $methods[$k] ?? (string) $k,
                default => $given[$k] ?? (string) $k,
            };
        }

        return $out;
    }

    // ── Orders ───────────────────────────────────────────────────────────────

    public function orderList(SalesReportFilter $f, ?string $search = null, string $status = 'paid', int $perPage = 25, ?int $hour = null)
    {
        $statuses = $status === 'all' ? ['paid', 'refunded', 'cancelled'] : [in_array($status, ['paid', 'refunded', 'cancelled'], true) ? $status : 'paid'];

        return $this->orders($f, 'orders', true, $statuses, Order::query())
            ->when($hour !== null, fn ($q) => $q->whereRaw('HOUR('.$this->bucketLocal($f, 'orders.created_at').') = ?', [$hour]))
            ->when($search, fn ($q) => $q->where('orders.order_number', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'))
            ->with(['outlet:id,name', 'user:id,name', 'payments:id,order_id,payment_method,amount'])
            ->orderByDesc('orders.created_at')->orderByDesc('orders.id')
            ->paginate($perPage);
    }

    /** Order ids must belong to this tenant and to an outlet the user may see; anything else is a 404. */
    public function orderDetail(SalesReportFilter $f, int $orderId): Order
    {
        return Order::query()->where('business_id', $f->businessId)->whereIn('outlet_id', $f->allowedOutletIds)
            ->with(['items.addons', 'payments', 'outlet:id,name', 'user:id,name', 'customer:id,name', 'promotion:id,name,type,value', 'shift:id,opened_at,closed_at'])
            ->findOrFail($orderId);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /** Eligible-order query. $aggregate=true returns a fresh builder ready for selectRaw on `orders`. */
    private function orders(SalesReportFilter $f, string $table = 'orders', bool $withPaymentFilter = true, array $statuses = ['paid'], $base = null)
    {
        $q = $base ?? DB::table($table);

        return $this->scope($q, $f, $table, $withPaymentFilter, $statuses);
    }

    private function scope($q, SalesReportFilter $f, string $t, bool $withPaymentFilter = true, array $statuses = ['paid'])
    {
        [$from, $to] = $f->dbRange();
        $q->where("{$t}.business_id", $f->businessId)->whereIn("{$t}.status", $statuses)
            ->whereIn("{$t}.outlet_id", $f->outletIds)
            ->where("{$t}.created_at", '>=', $from)->where("{$t}.created_at", '<', $to);
        if ($f->cashierIds) {
            $q->whereIn("{$t}.user_id", $f->cashierIds);
        }
        if ($withPaymentFilter && $f->paymentMethods) {
            $methods = $f->paymentMethods;
            $q->where(function ($w) use ($methods, $t) {
                $w->where(fn ($x) => $x->where("{$t}.is_split_payment", false)->whereIn("{$t}.payment_method", $methods))
                    ->orWhereExists(fn ($x) => $x->select(DB::raw(1))->from('order_payments')->whereColumn('order_payments.order_id', "{$t}.id")->whereIn('order_payments.payment_method', $methods));
            });
        }

        return $q;
    }

    private function itemQuery(SalesReportFilter $f)
    {
        $q = DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id');

        return $this->scope($q, $f, 'o');
    }

    private function bucketLocal(SalesReportFilter $f, string $col): string
    {
        $off = $f->dbOffsetSeconds();

        return $off === 0 ? $col : "DATE_ADD({$col}, INTERVAL {$off} SECOND)";
    }

    private function bucketExpr(SalesReportFilter $f, string $col, string $grouping): string
    {
        $d = $this->bucketLocal($f, $col);

        return match ($grouping) {
            'hour' => "HOUR({$d})",
            'weekday' => "WEEKDAY({$d})",
            'week' => "DATE(DATE_SUB({$d}, INTERVAL WEEKDAY({$d}) DAY))",
            'month' => "DATE_FORMAT({$d}, '%Y-%m')",
            default => "DATE({$d})",
        };
    }

    /** @return array<string|int, string> bucket key => label, in order. */
    private function bucketKeys(SalesReportFilter $f): array
    {
        $out = [];
        switch ($f->grouping) {
            case 'hour':
                foreach (range(0, 23) as $h) {
                    $out[$h] = sprintf('%02d:00', $h);
                }
                break;
            case 'weekday':
                foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $i => $n) {
                    $out[$i] = $n;
                }
                break;
            case 'week':
                for ($d = $f->start->startOfWeek(); $d->lt($f->end); $d = $d->addWeek()) {
                    $out[$d->toDateString()] = $d->format('d M');
                }
                break;
            case 'month':
                for ($d = $f->start->startOfMonth(); $d->lt($f->end); $d = $d->addMonthNoOverflow()) {
                    $out[$d->format('Y-m')] = $d->isoFormat('MMM YYYY');
                }
                break;
            default:
                for ($d = $f->start; $d->lt($f->end); $d = $d->addDay()) {
                    $out[$d->toDateString()] = $d->isoFormat('ddd D MMM');
                }
        }

        return $out;
    }

    public function evidence(SalesReportFilter $f): array
    {
        return [
            'metric_version' => MetricDictionary::VERSION,
            'as_of' => $f->asOf?->toIso8601String(),
            'timezone' => $f->timezone,
            'period' => ['from' => $f->dateFrom(), 'to' => $f->dateTo()],
            'outlet_ids' => $f->outletIds,
            'filter_hash' => $f->hash(),
            'eligibility' => 'Order berstatus paid; refunded dan cancelled dilaporkan terpisah.',
            'payment_note' => 'Pembayaran tercatat di POS, bukan settlement gateway atau rekonsiliasi bank.',
        ];
    }

    public function definitions(array $keys = []): array
    {
        return $this->dictionary->definitions($keys);
    }

    private function money(mixed $v): float
    {
        return round((float) $v, 2);
    }
}
