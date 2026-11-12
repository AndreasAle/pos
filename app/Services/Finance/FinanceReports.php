<?php

namespace App\Services\Finance;

use App\Models\Business;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Services\Reporting\SalesMetricsService;
use App\Services\Reporting\SalesReportFilter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the finance modules (tax, returns, profit & loss, aging). Sales figures
 * come from SalesMetricsService so every report agrees with Laporan Penjualan.
 *
 * Basis: management accounts on a cash basis for expenses (by expense_date) and the
 * sales basis already used by the sales report. Purchases of stock are inventory, not
 * expense; cost of goods sold comes from the unit-cost snapshot on each sold item.
 */
class FinanceReports
{
    public function __construct(private SalesMetricsService $sales) {}

    // ── Pajak ────────────────────────────────────────────────────────────────

    public function tax(SalesReportFilter $f): array
    {
        $paid = $this->sales->orders($f, 'orders', false)
            ->selectRaw('COUNT(*) orders, SUM(CASE WHEN orders.tax_amount > 0 THEN 1 ELSE 0 END) taxed_orders,'
                .' COALESCE(SUM(orders.subtotal - orders.discount_amount),0) net, COALESCE(SUM(CASE WHEN orders.tax_amount > 0 THEN orders.subtotal - orders.discount_amount ELSE 0 END),0) taxable_base,'
                .' COALESCE(SUM(orders.tax_amount),0) tax, COALESCE(SUM(orders.service_amount),0) service')->first();

        $day = $this->sales->orders($f, 'orders', false)
            ->selectRaw('DATE('.$this->local($f, 'orders.created_at').') d, COUNT(*) orders, SUM(CASE WHEN orders.tax_amount > 0 THEN orders.subtotal - orders.discount_amount ELSE 0 END) base, SUM(orders.tax_amount) tax, SUM(orders.service_amount) service')
            ->groupBy('d')->orderBy('d')->get();

        $outlet = $this->sales->orders($f, 'orders', false)->join('outlets', 'outlets.id', '=', 'orders.outlet_id')
            ->selectRaw('outlets.id, outlets.name, COUNT(*) orders, SUM(CASE WHEN orders.tax_amount > 0 THEN orders.subtotal - orders.discount_amount ELSE 0 END) base, SUM(orders.tax_amount) tax')
            ->groupBy('outlets.id', 'outlets.name')->orderByDesc('tax')->get();

        $reversed = $this->sales->orders($f, 'orders', false, ['refunded', 'cancelled'])
            ->selectRaw('COUNT(*) orders, COALESCE(SUM(orders.tax_amount),0) tax')->first();

        $remitted = $this->expenseQuery($f)->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('expense_categories.group', 'tax')->sum('expenses.amount');

        $business = Business::find($f->businessId);
        $settings = $business?->settings ?? [];
        $rate = ! empty($settings['enable_tax']) ? (float) ($settings['tax_percent'] ?? 0) : null;
        $base = round((float) $paid->taxable_base, 2);
        $tax = round((float) $paid->tax, 2);
        $warnings = [];
        if ((int) $paid->orders === 0) {
            $warnings[] = 'Belum ada transaksi paid pada filter ini.';
        }
        if ($base > 0 && $rate && abs($tax - $base * $rate / 100) > max(1, $base * 0.005)) {
            $warnings[] = 'Pajak tercatat berbeda dari tarif pengaturan saat ini ('.$rate.'%); tarif mungkin pernah diubah pada periode ini.';
        }

        return [
            'orders' => (int) $paid->orders, 'taxed_orders' => (int) $paid->taxed_orders,
            'net_sales' => round((float) $paid->net, 2), 'taxable_base' => $base, 'tax_collected' => $tax,
            'service_collected' => round((float) $paid->service, 2),
            'effective_rate_pct' => $base > 0 ? round($tax / $base * 100, 2) : null, 'configured_rate_pct' => $rate,
            'tax_reversed' => ['orders' => (int) $reversed->orders, 'amount' => round((float) $reversed->tax, 2)],
            'tax_remitted' => round((float) $remitted, 2), 'tax_balance' => round($tax - (float) $remitted, 2),
            'by_day' => $day->map(fn ($r) => ['date' => $r->d, 'orders' => (int) $r->orders, 'base' => round((float) $r->base, 2), 'tax' => round((float) $r->tax, 2), 'service' => round((float) $r->service, 2)])->all(),
            'by_outlet' => $outlet->map(fn ($r) => ['name' => $r->name, 'orders' => (int) $r->orders, 'base' => round((float) $r->base, 2), 'tax' => round((float) $r->tax, 2)])->all(),
            'warnings' => $warnings, 'evidence' => $this->sales->evidence($f),
        ];
    }

    // ── Retur transaksi ──────────────────────────────────────────────────────

    public function returns(SalesReportFilter $f): array
    {
        $by = $this->sales->orders($f, 'orders', false, ['paid', 'refunded', 'cancelled'])
            ->selectRaw('orders.status, COUNT(*) orders, COALESCE(SUM(orders.subtotal - orders.discount_amount),0) net, COALESCE(SUM(orders.grand_total),0) grand, COALESCE(SUM(orders.tax_amount),0) tax')
            ->groupBy('orders.status')->get()->keyBy('status');
        $row = fn ($s) => ['orders' => (int) ($by[$s]->orders ?? 0), 'net_sales' => round((float) ($by[$s]->net ?? 0), 2), 'grand_total' => round((float) ($by[$s]->grand ?? 0), 2), 'tax' => round((float) ($by[$s]->tax ?? 0), 2)];
        $paid = $row('paid');
        $refunded = $row('refunded');
        $cancelled = $row('cancelled');
        $all = $paid['orders'] + $refunded['orders'] + $cancelled['orders'];

        $reasons = $this->sales->orders($f, 'orders', false, ['refunded', 'cancelled'])
            ->selectRaw("orders.status, COALESCE(NULLIF(TRIM(CASE WHEN orders.status = 'refunded' THEN orders.refund_reason ELSE orders.cancel_reason END), ''), 'Tanpa alasan') reason, COUNT(*) orders, SUM(orders.grand_total) amount")
            ->groupBy('orders.status', 'reason')->orderByDesc('amount')->limit(15)->get();

        $cashiers = $this->sales->orders($f, 'orders', false, ['refunded', 'cancelled'])->join('users', 'users.id', '=', 'orders.user_id')
            ->selectRaw('users.name, COUNT(*) orders, SUM(orders.grand_total) amount')->groupBy('users.id', 'users.name')->orderByDesc('orders')->limit(10)->get();

        return [
            'refunded' => $refunded, 'cancelled' => $cancelled, 'paid' => $paid,
            'return_rate_pct' => $all > 0 ? round(($refunded['orders'] + $cancelled['orders']) / $all * 100, 2) : null,
            'reasons' => $reasons->map(fn ($r) => ['status' => $r->status, 'reason' => $r->reason, 'orders' => (int) $r->orders, 'amount' => round((float) $r->amount, 2)])->all(),
            'cashiers' => $cashiers->map(fn ($r) => ['name' => $r->name, 'orders' => (int) $r->orders, 'amount' => round((float) $r->amount, 2)])->all(),
            'evidence' => $this->sales->evidence($f),
        ];
    }

    // ── Expenses ─────────────────────────────────────────────────────────────

    /** Posted expenses inside the window. Shared (outlet-less) costs only count when every outlet is in scope. */
    public function expenseQuery(SalesReportFilter $f)
    {
        $allOutlets = count($f->outletIds) === count($f->allowedOutletIds);

        return DB::table('expenses')->where('expenses.business_id', $f->businessId)->where('expenses.status', 'posted')
            ->where('expenses.expense_date', '>=', $f->dateFrom())->where('expenses.expense_date', '<=', $f->dateTo())
            ->where(fn ($q) => $q->whereIn('expenses.outlet_id', $f->outletIds)->when($allOutlets, fn ($w) => $w->orWhereNull('expenses.outlet_id')));
    }

    // ── Laba rugi ────────────────────────────────────────────────────────────

    public function profitLoss(SalesReportFilter $f): array
    {
        $s = $this->sales->summary($f);
        $m = $s['metrics'];
        $cats = $this->expenseQuery($f)->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.code, expense_categories.name, expense_categories.group, SUM(expenses.amount) amount, COUNT(*) entries')
            ->groupBy('expense_categories.id', 'expense_categories.code', 'expense_categories.name', 'expense_categories.group')->orderBy('expense_categories.code')->get();

        $opex = $cats->where('group', 'operating')->map(fn ($c) => ['code' => $c->code, 'name' => $c->name, 'amount' => round((float) $c->amount, 2), 'entries' => (int) $c->entries])->values()->all();
        $other = $cats->where('group', 'other_expense')->map(fn ($c) => ['code' => $c->code, 'name' => $c->name, 'amount' => round((float) $c->amount, 2), 'entries' => (int) $c->entries])->values()->all();
        $opexTotal = round(array_sum(array_column($opex, 'amount')), 2);
        $otherTotal = round(array_sum(array_column($other, 'amount')), 2);

        $net = $m['net_sales'];
        $cogs = $m['cogs'];
        $coverage = $m['cost_coverage_pct'];
        $gross = round($net - $cogs, 2);
        $operating = round($gross - $opexTotal, 2);
        $beforeTax = round($operating - $otherTotal, 2);

        $purchases = Purchase::where('business_id', $f->businessId)->where('status', 'received')
            ->whereBetween('purchase_date', [$f->dateFrom(), $f->dateTo()])->sum('total');

        $warnings = $s['warnings'];
        if (($coverage === null || $coverage <= 0) && $m['transactions'] > 0) {
            $warnings[] = 'HPP tidak tersedia pada transaksi ini sehingga laba kotor sama dengan penjualan bersih (terlalu tinggi).';
        } elseif ($coverage !== null && $coverage < 100) {
            $warnings[] = 'HPP hanya tercatat untuk '.$coverage.'% nilai item; HPP sesungguhnya lebih besar, laba kotor terlalu tinggi.';
        }
        if (! $cats->count() && $m['transactions'] > 0) {
            $warnings[] = 'Belum ada biaya operasional tercatat pada periode ini; laba operasional mungkin terlalu tinggi. Catat di menu Biaya.';
        }

        return [
            'revenue' => ['gross_item_sales' => $m['gross_item_sales'], 'discounts' => $m['discounts'], 'net_sales' => $net],
            'cogs' => $cogs, 'cogs_coverage_pct' => $coverage, 'gross_profit' => $gross,
            'gross_margin_pct' => $net > 0 ? round($gross / $net * 100, 1) : null,
            'opex' => $opex, 'opex_total' => $opexTotal, 'operating_profit' => $operating,
            'operating_margin_pct' => $net > 0 ? round($operating / $net * 100, 1) : null,
            'other_expenses' => $other, 'other_total' => $otherTotal, 'profit_before_tax' => $beforeTax,
            'net_margin_pct' => $net > 0 ? round($beforeTax / $net * 100, 1) : null,
            'memo' => [
                'tax_collected' => $m['tax_amount'], 'service_amount' => $m['service_amount'], 'delivery_fees' => $m['delivery_fees'],
                'purchases_received' => round((float) $purchases, 2), 'transactions' => $m['transactions'],
                'tax_remitted' => round((float) $cats->where('group', 'tax')->sum('amount'), 2),
            ],
            'warnings' => array_values(array_unique($warnings)), 'evidence' => $s['evidence'],
        ];
    }

    // ── Aging (hutang / piutang) ─────────────────────────────────────────────

    public const BUCKETS = ['current' => 'Belum jatuh tempo', 'd1_30' => '1-30 hari', 'd31_60' => '31-60 hari', 'd61_90' => '61-90 hari', 'd90_plus' => '> 90 hari'];

    public static function bucket(?CarbonImmutable $due, CarbonImmutable $asOf): string
    {
        if (! $due || $due->endOfDay()->gte($asOf)) {
            return 'current';
        }
        $days = (int) $due->startOfDay()->diffInDays($asOf->startOfDay());

        return match (true) {
            $days <= 30 => 'd1_30', $days <= 60 => 'd31_60', $days <= 90 => 'd61_90', default => 'd90_plus'
        };
    }

    public function payablesAging(int $businessId, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now(config('app.timezone'));
        $rows = Purchase::where('business_id', $businessId)->where('status', 'received')->whereColumn('paid_amount', '<', 'total')
            ->with('supplier:id,name')->orderBy('due_date')->get();

        return $this->age($rows->map(fn ($p) => [
            'id' => $p->id, 'number' => $p->purchase_number, 'party' => $p->supplier?->name ?? '-', 'date' => $p->purchase_date,
            'due' => $p->due_date, 'total' => (float) $p->total, 'paid' => (float) $p->paid_amount, 'outstanding' => $p->outstanding,
        ]), $asOf);
    }

    public function receivablesAging(int $businessId, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now(config('app.timezone'));
        $rows = Receivable::where('business_id', $businessId)->where('status', 'open')->whereColumn('paid_amount', '<', 'amount')->orderBy('due_date')->get();

        return $this->age($rows->map(fn ($r) => [
            'id' => $r->id, 'number' => $r->receivable_number, 'party' => $r->party_name, 'date' => $r->issue_date,
            'due' => $r->due_date, 'total' => (float) $r->amount, 'paid' => (float) $r->paid_amount, 'outstanding' => $r->outstanding,
        ]), $asOf);
    }

    private function age($rows, CarbonImmutable $asOf): array
    {
        $buckets = array_fill_keys(array_keys(self::BUCKETS), 0.0);
        $parties = [];
        $rows = $rows->map(function ($r) use (&$buckets, &$parties, $asOf) {
            $due = $r['due'] ? CarbonImmutable::parse($r['due']) : null;
            $b = self::bucket($due, $asOf);
            $buckets[$b] += $r['outstanding'];
            $parties[$r['party']][$b] = ($parties[$r['party']][$b] ?? 0) + $r['outstanding'];
            $parties[$r['party']]['total'] = ($parties[$r['party']]['total'] ?? 0) + $r['outstanding'];

            return $r + ['bucket' => $b, 'days_overdue' => $due && $due->endOfDay()->lt($asOf) ? (int) $due->startOfDay()->diffInDays($asOf->startOfDay()) : 0];
        });
        uasort($parties, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'rows' => $rows->all(), 'buckets' => array_map(fn ($v) => round($v, 2), $buckets), 'total' => round(array_sum($buckets), 2),
            'overdue' => round($buckets['d1_30'] + $buckets['d31_60'] + $buckets['d61_90'] + $buckets['d90_plus'], 2),
            'parties' => $parties, 'as_of' => $asOf->toDateString(),
        ];
    }

    private function local(SalesReportFilter $f, string $col): string
    {
        $off = $f->dbOffsetSeconds();

        return $off === 0 ? $col : "DATE_ADD({$col}, INTERVAL {$off} SECOND)";
    }

    public function expenseTotals(SalesReportFilter $f): array
    {
        $rows = $this->expenseQuery($f)->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.code, expense_categories.name, expense_categories.group, SUM(expenses.amount) amount, COUNT(*) entries')
            ->groupBy('expense_categories.id', 'expense_categories.code', 'expense_categories.name', 'expense_categories.group')->orderByDesc('amount')->get();

        return ['rows' => $rows->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'group' => $r->group, 'amount' => round((float) $r->amount, 2), 'entries' => (int) $r->entries])->all(), 'total' => round((float) $rows->sum('amount'), 2)];
    }
}
