<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getSummary(Business $business, ?int $outletId = null): array
    {
        $baseQuery = fn() => Order::forBusiness($business->id)
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->paid();

        $today = $baseQuery()->today();

        $todayRevenue = (float) $today->sum('grand_total');
        $todayOrders  = $today->count();
        $todayProfit  = (float) $today->with('items')->get()
            ->sum(fn($o) => $o->estimated_profit);

        $avgOrder = $todayOrders > 0 ? round($todayRevenue / $todayOrders) : 0;

        return compact('todayRevenue', 'todayOrders', 'todayProfit', 'avgOrder');
    }

    public function getTopProducts(Business $business, ?int $outletId = null, int $limit = 5): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $business->id)
            ->where('orders.status', 'paid')
            ->whereDate('orders.created_at', today())
            ->when($outletId, fn($q) => $q->where('orders.outlet_id', $outletId))
            ->select(
                'order_items.product_name',
                DB::raw('SUM(order_items.qty) as total_qty'),
                DB::raw('SUM(order_items.subtotal) as total_revenue')
            )
            ->groupBy('order_items.product_name')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getSalesChart(Business $business, ?int $outletId = null, int $days = 7): array
    {
        $data = Order::forBusiness($business->id)
            ->paid()
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $labels  = [];
        $revenue = [];
        $orders  = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date      = now()->subDays($i)->format('Y-m-d');
            $labels[]  = now()->subDays($i)->format('d M');
            $revenue[] = (float) ($data[$date]->revenue ?? 0);
            $orders[]  = (int) ($data[$date]->orders ?? 0);
        }

        return compact('labels', 'revenue', 'orders');
    }

    public function getPaymentBreakdown(Business $business, ?int $outletId = null): array
    {
        return Order::forBusiness($business->id)
            ->paid()
            ->today()
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(grand_total) as total'))
            ->groupBy('payment_method')
            ->get()
            ->toArray();
    }

    public function getLowStockIngredients(Business $business, ?int $outletId = null): array
    {
        return Ingredient::forBusiness($business->id)
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->lowStock()
            ->where('is_active', true)
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function getRecentOrders(Business $business, ?int $outletId = null, int $limit = 10): array
    {
        return Order::forBusiness($business->id)
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->with(['outlet', 'user'])
            ->latest()
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getOutletPerformance(Business $business): array
    {
        return Order::where('orders.business_id', $business->id)
            ->where('orders.status', 'paid')
            ->whereDate('orders.created_at', today())
            ->join('outlets', 'outlets.id', '=', 'orders.outlet_id')
            ->select(
                'outlets.name as outlet_name',
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(orders.grand_total) as revenue')
            )
            ->groupBy('outlets.id', 'outlets.name')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();
    }
}
