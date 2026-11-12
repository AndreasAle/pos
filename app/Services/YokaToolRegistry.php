<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\Intelligence\MetricDictionary;
use App\Services\Intelligence\PeriodResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class YokaToolRegistry
{
    public const SCHEMA_VERSION = '2.0';

    public function __construct(private PeriodResolver $periods, private MetricDictionary $metrics) {}

    public function definitions(): array
    {
        $tools = [
            'sales_summary' => 'Ringkasan penjualan, transaksi, AOV dan estimasi laba kotor.',
            'sales_series' => 'Deret penjualan harian untuk tren.',
            'payment_breakdown' => 'Pembayaran tercatat per metode, termasuk split payment.',
            'product_performance' => 'Ranking produk, kuantitas, penjualan dan estimasi margin.',
            'stock_status' => 'Stok teoritis dan estimasi ketahanan bahan.',
            'peak_hours' => 'Jam ramai berdasarkan transaksi paid.',
            'outlet_performance' => 'Perbandingan penjualan antar-outlet berizin.',
            'customer_performance' => 'Pelanggan teratas berdasarkan order paid.',
            'shift_summary' => 'Ringkasan shift dan kasir.',
            'recipe_cost' => 'Estimasi biaya resep dari average cost bahan.',
        ];

        return collect($tools)->map(fn ($description, $name) => ['type' => 'function', 'function' => [
            'name' => $name, 'description' => $description, 'strict' => true,
            'parameters' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                'period' => ['type' => ['string', 'null'], 'enum' => ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'last_30_days', 'all_time', 'custom', null]],
                'date_from' => ['type' => ['string', 'null']], 'date_to' => ['type' => ['string', 'null']],
                'outlet_ids' => ['type' => ['array', 'null'], 'items' => ['type' => 'integer'], 'maxItems' => 20],
                'query' => ['type' => ['string', 'null'], 'maxLength' => 100],
                'limit' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 20],
            ], 'required' => ['period', 'date_from', 'date_to', 'outlet_ids', 'query', 'limit']],
        ]])->values()->all();
    }

    public function execute(User $user, string $tool, array $args = []): array
    {
        if (! in_array($tool, collect($this->definitions())->pluck('function.name')->all(), true)) {
            throw ValidationException::withMessages(['tool' => 'Tool intelligence tidak diizinkan.']);
        }
        $args = $this->validateArgs($args);
        $period = $this->periods->resolve($user->business, $args);
        $outlets = $this->authorizedOutlets($user, $args['outlet_ids'] ?? []);
        $query = trim((string) ($args['query'] ?? ''));
        $limit = min(20, max(1, (int) ($args['limit'] ?? 10)));
        $payload = match ($tool) {
            'sales_summary' => $this->sales($user, $period, $outlets),
            'sales_series' => $this->series($user, $period, $outlets),
            'payment_breakdown' => $this->payments($user, $period, $outlets),
            'product_performance' => $this->products($user, $period, $outlets, $query, $limit),
            'stock_status' => $this->stock($user, $outlets, $query, $limit),
            'peak_hours' => $this->hours($user, $period, $outlets),
            'outlet_performance' => $this->outlets($user, $period, $outlets),
            'customer_performance' => $this->customers($user, $period, $outlets, $limit),
            'shift_summary' => $this->shifts($user, $period, $outlets, $limit),
            'recipe_cost' => $this->recipes($user, $outlets, $query, $limit),
        };

        return $this->envelope($tool, $user, $period, $outlets, $payload);
    }

    private function sales(User $u, array $p, array $o): array
    {
        $t = $this->orders($u, $p, $o)->selectRaw('COUNT(*) transactions, COALESCE(SUM(subtotal),0) gross_item_sales, COALESCE(SUM(discount_amount),0) discounts, COALESCE(SUM(subtotal-discount_amount),0) net_sales, COALESCE(SUM(grand_total),0) recorded_order_total')->first();
        $c = $this->within(OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.business_id', $u->business_id)->whereIn('orders.outlet_id', $o)->where('orders.status', 'paid'), 'orders.created_at', $p)->selectRaw('COALESCE(SUM(order_items.subtotal-order_items.qty*order_items.cost_price),0) profit, COUNT(*) rows_count, SUM(CASE WHEN order_items.cost_price>0 THEN 1 ELSE 0 END) costed')->first();
        $n = (int) $t->transactions;
        $coverage = $c->rows_count ? round($c->costed / $c->rows_count * 100) : null;

        return $this->payload([
            'gross_item_sales' => (float) $t->gross_item_sales, 'discounts' => (float) $t->discounts,
            'net_sales' => (float) $t->net_sales, 'recorded_order_total' => (float) $t->recorded_order_total,
            'transactions' => $n, 'average_order' => $n ? round($t->net_sales / $n, 2) : null,
            'estimated_gross_profit' => (float) $c->profit, 'cogs_coverage_percent' => $coverage,
        ], ['gross_item_sales', 'discounts', 'net_sales', 'transactions', 'aov', 'gross_profit_estimate'], $coverage === 100 ? 'complete' : 'partial', array_values(array_filter([
            $coverage !== null && $coverage < 100 ? "HPP hanya tersedia untuk {$coverage}% baris item." : null,
            'Estimasi laba kotor bukan laba bersih; biaya operasional tidak tersedia.',
        ])));
    }

    private function series(User $u, array $p, array $o): array
    {
        $rows = $this->orders($u, $p, $o)->selectRaw('DATE(created_at) date, SUM(subtotal-discount_amount) value, COUNT(*) transactions')->groupBy('date')->orderBy('date')->limit(366)->get()->map(fn ($r) => ['date' => $r->date, 'value' => (float) $r->value, 'transactions' => (int) $r->transactions])->all();

        return $this->payload(['points' => $rows, 'total' => array_sum(array_column($rows, 'value')), 'bucket' => 'day'], ['net_sales', 'transactions'], 'complete', ['Tanggal tanpa transaksi tidak dibuat sebagai titik nol.']);
    }

    private function payments(User $u, array $p, array $o): array
    {
        $single = $this->orders($u, $p, $o)->where('is_split_payment', false)->selectRaw('payment_method, COUNT(*) transactions, SUM(grand_total) amount')->groupBy('payment_method')->get()->keyBy('payment_method');
        $split = $this->within(OrderPayment::query()->join('orders', 'orders.id', '=', 'order_payments.order_id')->where('orders.business_id', $u->business_id)->whereIn('orders.outlet_id', $o)->where('orders.status', 'paid')->where('orders.is_split_payment', true), 'orders.created_at', $p)->selectRaw('order_payments.payment_method, COUNT(DISTINCT orders.id) transactions, SUM(order_payments.amount) amount')->groupBy('order_payments.payment_method')->get()->keyBy('payment_method');
        $rows = $single->keys()->merge($split->keys())->unique()->map(fn ($m) => ['payment_method' => $m, 'transactions' => (int) ($single[$m]->transactions ?? 0) + (int) ($split[$m]->transactions ?? 0), 'amount' => (float) ($single[$m]->amount ?? 0) + (float) ($split[$m]->amount ?? 0), 'payment_status' => 'recorded_on_paid_order'])->sortByDesc('amount')->values()->all();

        return $this->payload(['payments' => $rows, 'total' => array_sum(array_column($rows, 'amount'))], ['payment_collected'], 'complete', ['Nilai adalah pembayaran tercatat, bukan settlement atau biaya gateway.']);
    }

    private function products(User $u, array $p, array $o, string $q, int $limit): array
    {
        $rows = $this->within(OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.business_id', $u->business_id)->whereIn('orders.outlet_id', $o)->where('orders.status', 'paid'), 'orders.created_at', $p)->when($q, fn ($b) => $b->where('order_items.product_name', 'like', '%'.$q.'%'))->select('order_items.product_id', 'order_items.product_name')->selectRaw('SUM(order_items.qty) quantity, SUM(order_items.subtotal) net_sales, SUM((order_items.price-order_items.cost_price)*order_items.qty) estimated_gross_profit')->groupBy('order_items.product_id', 'order_items.product_name')->orderByDesc('quantity')->limit($limit)->get()->map(fn ($r) => ['product_id' => $r->product_id, 'name' => $r->product_name, 'quantity' => (float) $r->quantity, 'net_sales' => (float) $r->net_sales, 'estimated_gross_profit' => (float) $r->estimated_gross_profit])->all();

        return $this->payload(['products' => $rows], ['net_sales', 'gross_profit_estimate'], 'partial', ['Estimasi margin mengikuti kelengkapan snapshot HPP.']);
    }

    private function stock(User $u, array $o, string $q, int $limit): array
    {
        $usage = DB::table('stock_movements')->where('business_id', $u->business_id)->where(fn ($b) => $b->whereNull('outlet_id')->orWhereIn('outlet_id', $o))->whereIn('type', ['sale', 'out', 'waste'])->where('created_at', '>=', now()->subDays(14))->select('ingredient_id')->selectRaw('SUM(ABS(qty))/14 daily_usage')->groupBy('ingredient_id')->pluck('daily_usage', 'ingredient_id');
        $rows = Ingredient::forBusiness($u->business_id)->where('is_active', true)->where(fn ($b) => $b->whereNull('outlet_id')->orWhereIn('outlet_id', $o))->when($q, fn ($b) => $b->where('name', 'like', '%'.$q.'%'))->orderBy('current_stock')->limit($limit)->get()->map(function ($i) use ($usage) {
            $daily = (float) ($usage[$i->id] ?? 0);

            return ['ingredient_id' => $i->id, 'name' => $i->name, 'stock' => (float) $i->current_stock, 'unit' => $i->unit, 'minimum' => (float) $i->minimum_stock, 'low' => $i->isLowStock(), 'estimated_days_left' => $daily > 0 ? round($i->current_stock / $daily, 1) : null, 'last_movement_at' => $i->movements()->latest('id')->value('created_at')];
        })->all();

        return $this->payload(['ingredients' => $rows], ['stock_on_hand'], 'partial', ['Stok bersifat teoritis; stock count fisik/FIFO belum tersedia.', 'Estimasi hari memakai rata-rata pemakaian 14 hari.']);
    }

    private function hours(User $u, array $p, array $o): array
    {
        $rows = $this->orders($u, $p, $o)->selectRaw($this->hour('created_at').' hour, COUNT(*) transactions, SUM(subtotal-discount_amount) net_sales')->groupBy('hour')->orderByDesc('transactions')->limit(8)->get()->map(fn ($r) => ['hour' => (int) $r->hour, 'transactions' => (int) $r->transactions, 'net_sales' => (float) $r->net_sales])->all();

        return $this->payload(['hours' => $rows], ['transactions', 'net_sales']);
    }

    private function outlets(User $u, array $p, array $o): array
    {
        $rows = $this->orders($u, $p, $o)->join('outlets', 'outlets.id', '=', 'orders.outlet_id')->select('outlets.id', 'outlets.name')->selectRaw('COUNT(*) transactions, SUM(orders.subtotal-orders.discount_amount) net_sales')->groupBy('outlets.id', 'outlets.name')->orderByDesc('net_sales')->get()->map(fn ($r) => ['outlet_id' => $r->id, 'name' => $r->name, 'transactions' => (int) $r->transactions, 'net_sales' => (float) $r->net_sales])->all();

        return $this->payload(['outlets' => $rows], ['transactions', 'net_sales']);
    }

    private function customers(User $u, array $p, array $o, int $limit): array
    {
        $rows = $this->orders($u, $p, $o)->whereNotNull('customer_id')->join('customers', 'customers.id', '=', 'orders.customer_id')->select('customers.id', 'customers.name')->selectRaw('COUNT(*) visits, SUM(orders.subtotal-orders.discount_amount) spending')->groupBy('customers.id', 'customers.name')->orderByDesc('spending')->limit($limit)->get()->map(fn ($r) => ['customer_id' => $r->id, 'name' => $r->name, 'visits' => (int) $r->visits, 'spending' => (float) $r->spending])->all();

        return $this->payload(['customers' => $rows], ['net_sales', 'transactions'], 'partial', ['Hanya order yang terhubung ke pelanggan.']);
    }

    private function shifts(User $u, array $p, array $o, int $limit): array
    {
        $rows = $this->within(CashierShift::where('business_id', $u->business_id)->whereIn('outlet_id', $o), 'opened_at', $p)->with(['user:id,name', 'outlet:id,name'])->latest('opened_at')->limit($limit)->get()->map(fn ($s) => ['shift_id' => $s->id, 'cashier' => $s->user?->name, 'outlet' => $s->outlet?->name, 'opened_at' => $s->opened_at?->toIso8601String(), 'closed_at' => $s->closed_at?->toIso8601String(), 'status' => $s->status, 'cash_difference' => (float) $s->cash_difference])->all();

        return $this->payload(['shifts' => $rows], [], 'complete', ['Data wage, break, dan payroll tidak tersedia.']);
    }

    private function recipes(User $u, array $o, string $q, int $limit): array
    {
        $rows = $u->business->products()->where(fn ($b) => $b->whereNull('outlet_id')->orWhereIn('outlet_id', $o))->when($q, fn ($b) => $b->where('name', 'like', '%'.$q.'%'))->with('recipe.items.ingredient')->limit($limit)->get()->map(function ($product) {
            $items = $product->recipe?->items ?? collect();

            return ['product_id' => $product->id, 'name' => $product->name, 'estimated_recipe_cost' => $items->sum(fn ($item) => $item->qty * ($item->ingredient?->average_cost ?? 0)), 'ingredient_count' => $items->count(), 'costed_ingredient_count' => $items->filter(fn ($item) => $item->ingredient && $item->ingredient->average_cost > 0)->count()];
        })->all();

        return $this->payload(['products' => $rows], ['gross_profit_estimate'], 'partial', ['Biaya memakai average_cost bahan dan resep saat ini.']);
    }

    private function envelope(string $tool, User $u, array $p, array $o, array $payload): array
    {
        $id = hash('sha256', implode('|', [$u->business_id, $tool, $p['inclusive_start'], $p['exclusive_end'], implode(',', $o), now()->format('Y-m-d H:i')]));

        return ['schema_version' => self::SCHEMA_VERSION, 'tool' => $tool, 'data' => $payload['data'], 'scope' => ['business_id' => $u->business_id, 'outlet_ids' => $o], 'period' => ['label' => $p['label'], 'start' => $p['inclusive_start'], 'end_exclusive' => $p['exclusive_end']], 'timezone' => $p['timezone'], 'currency' => $u->business->currency, 'metric_definitions' => $this->metrics->definitions($payload['metrics']), 'completeness' => ['status' => $payload['completeness']], 'generated_at' => now($p['timezone'])->toIso8601String(), 'evidence_refs' => [['id' => $id, 'source' => 'yoka_pos', 'tool' => $tool]], 'warnings' => $payload['warnings']];
    }

    private function payload(array $data, array $metrics = [], string $completeness = 'complete', array $warnings = []): array
    {
        return compact('data', 'metrics', 'completeness', 'warnings');
    }

    private function orders(User $u, array $p, array $o): Builder
    {
        return $this->within(Order::query()->where('business_id', $u->business_id)->whereIn('outlet_id', $o)->where('status', 'paid'), 'created_at', $p);
    }

    private function within($query, string $column, array $p)
    {
        [$from, $to] = $this->range($p);

        return $query->where($column, '>=', $from)->where($column, '<', $to);
    }

    private function range(array $p): array
    {
        return [$p['from']->setTimezone(config('app.timezone')), $p['to']->setTimezone(config('app.timezone'))];
    }

    private function hour(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "CAST(strftime('%H', {$column}) AS INTEGER)" : "HOUR({$column})";
    }

    private function authorizedOutlets(User $u, array $requested): array
    {
        $all = $u->business->outlets()->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $requested = array_values(array_unique(array_map('intval', $requested)));
        if (! $all || ($requested && array_diff($requested, $all))) {
            throw ValidationException::withMessages(['outlet_ids' => 'Outlet tidak tersedia dalam akses kamu.']);
        }

        return $requested ?: $all;
    }

    private function validateArgs(array $args): array
    {
        $allowed = ['period', 'date_from', 'date_to', 'outlet_ids', 'query', 'limit'];
        if ($unknown = array_diff(array_keys($args), $allowed)) {
            throw ValidationException::withMessages(['arguments' => 'Argumen tool tidak dikenal: '.implode(', ', $unknown)]);
        }
        if (isset($args['query']) && mb_strlen((string) $args['query']) > 100) {
            throw ValidationException::withMessages(['query' => 'Pencarian terlalu panjang.']);
        }

        return array_filter($args, fn ($value) => $value !== null);
    }
}
