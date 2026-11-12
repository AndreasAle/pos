<?php

namespace App\Services;

use App\Models\Business;
use App\Models\CashierShift;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class YokaToolRegistry
{
    public function definitions(): array
    {
        $tools = [
            'sales_summary' => 'Ringkasan omzet, transaksi, rata-rata order, laba, dan perbandingan periode.',
            'product_performance' => 'Produk terlaris, omzet produk, kuantitas, dan margin produk.',
            'stock_status' => 'Stok bahan, bahan menipis, serta perkiraan hari sampai habis.',
            'peak_hours' => 'Jam operasional paling ramai berdasarkan jumlah transaksi.',
            'payment_breakdown' => 'Komposisi metode pembayaran.',
            'outlet_performance' => 'Perbandingan omzet dan transaksi antar-outlet.',
            'customer_performance' => 'Pelanggan teratas berdasarkan frekuensi dan nilai transaksi.',
            'open_shifts' => 'Shift kasir yang masih berjalan.',
        ];
        return collect($tools)->map(fn ($description, $name) => [
            'type' => 'function', 'function' => [
                'name' => $name, 'description' => $description,
                'parameters' => ['type' => 'object', 'properties' => [
                    'period' => ['type' => 'string', 'enum' => ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'last_30_days']],
                    'query' => ['type' => 'string', 'description' => 'Nama produk, bahan, pelanggan, atau outlet bila disebut.'],
                ]],
            ],
        ])->values()->all();
    }

    public function execute(Business $business, string $tool, array $args = []): array
    {
        [$from, $to, $label] = $this->period($args['period'] ?? 'today');
        $query = trim((string) ($args['query'] ?? ''));
        return match ($tool) {
            'sales_summary' => $this->sales($business, $from, $to, $label),
            'product_performance' => $this->products($business, $from, $to, $label, $query),
            'stock_status' => $this->stock($business, $query),
            'peak_hours' => $this->hours($business, $from, $to, $label),
            'payment_breakdown' => $this->payments($business, $from, $to, $label),
            'outlet_performance' => $this->outlets($business, $from, $to, $label),
            'customer_performance' => $this->customers($business, $from, $to, $label),
            'open_shifts' => $this->shifts($business),
            default => ['error' => 'Tool tidak dikenal.'],
        };
    }

    private function sales(Business $b, Carbon $from, Carbon $to, string $label): array
    {
        $q = Order::forBusiness($b->id)->paid()->whereBetween('created_at', [$from, $to]);
        $orders = (clone $q)->count(); $revenue = (float) (clone $q)->sum('grand_total');
        $profit = (float) (clone $q)->with('items')->get()->sum->estimated_profit;
        $seconds = max(1, $from->diffInSeconds($to));
        $previous = (float) Order::forBusiness($b->id)->paid()
            ->whereBetween('created_at', [$from->copy()->subSeconds($seconds + 1), $from->copy()->subSecond()])->sum('grand_total');
        return compact('label', 'orders', 'revenue', 'profit', 'previous') + [
            'average_order' => $orders ? round($revenue / $orders) : 0,
            'change_percent' => $previous > 0 ? round(($revenue - $previous) / $previous * 100, 1) : null,
        ];
    }

    private function products(Business $b, Carbon $from, Carbon $to, string $label, string $query): array
    {
        $rows = OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $b->id)->where('orders.status', 'paid')->whereBetween('orders.created_at', [$from, $to])
            ->when($query, fn ($q) => $q->where('order_items.product_name', 'like', '%'.$query.'%'))
            ->select('order_items.product_name')->selectRaw('SUM(order_items.qty) quantity, SUM(order_items.subtotal) revenue')
            ->selectRaw('SUM((order_items.price-order_items.cost_price)*order_items.qty) profit')
            ->groupBy('order_items.product_name')->orderByDesc('quantity')->limit(10)->get();
        return ['label' => $label, 'products' => $rows->map(fn ($r) => [
            'name' => $r->product_name, 'quantity' => (float) $r->quantity, 'revenue' => (float) $r->revenue,
            'profit' => (float) $r->profit, 'margin_percent' => $r->revenue > 0 ? round($r->profit / $r->revenue * 100, 1) : 0,
        ])->all()];
    }

    private function stock(Business $b, string $query): array
    {
        $usage = DB::table('stock_movements')->where('business_id', $b->id)->whereIn('type', ['sale', 'out', 'waste'])
            ->where('created_at', '>=', now()->subDays(14))->select('ingredient_id')->selectRaw('SUM(ABS(qty))/14 daily_usage')
            ->groupBy('ingredient_id')->pluck('daily_usage', 'ingredient_id');
        $rows = Ingredient::forBusiness($b->id)->where('is_active', true)
            ->when($query, fn ($q) => $q->where('name', 'like', '%'.$query.'%'))->orderBy('current_stock')->limit(15)->get();
        return ['ingredients' => $rows->map(function ($i) use ($usage) {
            $daily = (float) ($usage[$i->id] ?? 0);
            return ['name' => $i->name, 'stock' => (float) $i->current_stock, 'unit' => $i->unit,
                'minimum' => (float) $i->minimum_stock, 'low' => $i->isLowStock(),
                'estimated_days_left' => $daily > 0 ? round((float) $i->current_stock / $daily, 1) : null];
        })->all()];
    }

    private function hours(Business $b, Carbon $from, Carbon $to, string $label): array
    {
        $rows = Order::forBusiness($b->id)->paid()->whereBetween('created_at', [$from, $to])
            ->selectRaw($this->hourExpression('created_at').' hour, COUNT(*) orders, SUM(grand_total) revenue')->groupBy('hour')->orderByDesc('orders')->limit(5)->get();
        return ['label' => $label, 'hours' => $rows->map(fn ($r) => ['hour'=>(int)$r->hour,'orders'=>(int)$r->orders,'revenue'=>(float)$r->revenue])->all()];
    }

    private function payments(Business $b, Carbon $from, Carbon $to, string $label): array
    {
        $rows = Order::forBusiness($b->id)->paid()->whereBetween('created_at', [$from, $to])
            ->select('payment_method')->selectRaw('COUNT(*) orders, SUM(grand_total) total')->groupBy('payment_method')->orderByDesc('total')->get();
        return ['label' => $label, 'payments' => $rows->map(fn($r)=>['payment_method'=>$r->payment_method,'orders'=>(int)$r->orders,'total'=>(float)$r->total])->all()];
    }

    private function outlets(Business $b, Carbon $from, Carbon $to, string $label): array
    {
        $rows = Order::forBusiness($b->id)->paid()->whereBetween('orders.created_at', [$from, $to])->join('outlets', 'outlets.id', '=', 'orders.outlet_id')
            ->select('outlets.name')->selectRaw('COUNT(*) orders, SUM(orders.grand_total) revenue')->groupBy('outlets.id', 'outlets.name')->orderByDesc('revenue')->get();
        return ['label' => $label, 'outlets' => $rows->map(fn($r)=>['name'=>$r->name,'orders'=>(int)$r->orders,'revenue'=>(float)$r->revenue])->all()];
    }

    private function customers(Business $b, Carbon $from, Carbon $to, string $label): array
    {
        $rows = Order::forBusiness($b->id)->paid()->whereBetween('orders.created_at', [$from, $to])->whereNotNull('customer_id')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')->select('customers.name')
            ->selectRaw('COUNT(*) visits, SUM(orders.grand_total) spending')->groupBy('customers.id', 'customers.name')->orderByDesc('spending')->limit(10)->get();
        return ['label' => $label, 'customers' => $rows->map(fn($r)=>['name'=>$r->name,'visits'=>(int)$r->visits,'spending'=>(float)$r->spending])->all()];
    }

    private function shifts(Business $b): array
    {
        return ['shifts' => CashierShift::where('business_id', $b->id)->where('status', 'open')->with(['user:id,name', 'outlet:id,name'])->get()
            ->map(fn ($s) => ['cashier' => $s->user?->name, 'outlet' => $s->outlet?->name, 'opened_at' => $s->opened_at?->toIso8601String()])->all()];
    }

    private function period(string $period): array
    {
        return match ($period) {
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), 'kemarin'],
            'this_week' => [now()->startOfWeek(), now(), 'minggu ini'],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek(), 'minggu lalu'],
            'this_month' => [now()->startOfMonth(), now(), 'bulan ini'],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth(), 'bulan lalu'],
            'last_30_days' => [now()->subDays(29)->startOfDay(), now(), '30 hari terakhir'],
            default => [now()->startOfDay(), now(), 'hari ini'],
        };
    }

    private function hourExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', {$column}) AS INTEGER)"
            : "HOUR({$column})";
    }
}
