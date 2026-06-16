<?php

namespace App\Services;

use App\Models\Business;
use App\Models\CashierShift;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getFilters(array $filters): array
    {
        return $this->baseFilters($filters);
    }

    private function baseFilters(array $filters): array
    {
        return [
            'date_from' => $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d'),
            'date_to'   => $filters['date_to']   ?? now()->format('Y-m-d'),
            'outlet_id' => $filters['outlet_id'] ?? null,
        ];
    }

    public function salesReport(Business $business, array $filters): array
    {
        $f = $this->baseFilters($filters);
        $outlets = $business->outlets()->where('is_active', true)->get();

        $query = Order::forBusiness($business->id)->paid()
            ->whereBetween(DB::raw('DATE(created_at)'), [$f['date_from'], $f['date_to']])
            ->when($f['outlet_id'], fn($q) => $q->where('outlet_id', $f['outlet_id']));

        $summary = [
            'total_revenue'    => (float) $query->sum('grand_total'),
            'total_orders'     => $query->count(),
            'total_discount'   => (float) $query->sum('discount_amount'),
            'total_tax'        => (float) $query->sum('tax_amount'),
        ];
        $summary['avg_order'] = $summary['total_orders'] > 0
            ? round($summary['total_revenue'] / $summary['total_orders']) : 0;

        $daily = Order::forBusiness($business->id)->paid()
            ->whereBetween(DB::raw('DATE(created_at)'), [$f['date_from'], $f['date_to']])
            ->when($f['outlet_id'], fn($q) => $q->where('outlet_id', $f['outlet_id']))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(grand_total) as revenue'), DB::raw('COUNT(*) as orders'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $paymentBreakdown = Order::forBusiness($business->id)->paid()
            ->whereBetween(DB::raw('DATE(created_at)'), [$f['date_from'], $f['date_to']])
            ->when($f['outlet_id'], fn($q) => $q->where('outlet_id', $f['outlet_id']))
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(grand_total) as total'))
            ->groupBy('payment_method')
            ->get();

        return compact('summary', 'daily', 'paymentBreakdown', 'outlets', 'f');
    }

    public function productReport(Business $business, array $filters): array
    {
        $f = $this->baseFilters($filters);
        $outlets = $business->outlets()->where('is_active', true)->get();

        $products = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $business->id)
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$f['date_from'], $f['date_to']])
            ->when($f['outlet_id'], fn($q) => $q->where('orders.outlet_id', $f['outlet_id']))
            ->select(
                'order_items.product_name',
                DB::raw('SUM(order_items.qty) as total_qty'),
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
                DB::raw('SUM(order_items.qty * order_items.cost_price) as total_cost')
            )
            ->groupBy('order_items.product_name')
            ->orderByDesc('total_qty')
            ->paginate(30);

        return compact('products', 'outlets', 'f');
    }

    public function cashierReport(Business $business, array $filters): array
    {
        $f = $this->baseFilters($filters);
        $outlets = $business->outlets()->where('is_active', true)->get();

        $cashiers = Order::where('orders.business_id', $business->id)
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$f['date_from'], $f['date_to']])
            ->when($f['outlet_id'], fn($q) => $q->where('orders.outlet_id', $f['outlet_id']))
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->select('users.name as cashier_name', DB::raw('COUNT(*) as total_orders'), DB::raw('SUM(orders.grand_total) as total_revenue'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_revenue')
            ->get();

        return compact('cashiers', 'outlets', 'f');
    }

    public function shiftReport(Business $business, array $filters): array
    {
        $f = $this->baseFilters($filters);
        $outlets = $business->outlets()->where('is_active', true)->get();

        $shifts = CashierShift::forBusiness($business->id)
            ->whereBetween(DB::raw('DATE(opened_at)'), [$f['date_from'], $f['date_to']])
            ->when($f['outlet_id'], fn($q) => $q->where('outlet_id', $f['outlet_id']))
            ->with(['user', 'outlet'])
            ->latest('opened_at')
            ->paginate(30);

        return compact('shifts', 'outlets', 'f');
    }

    public function inventoryReport(Business $business, array $filters): array
    {
        $f = $this->baseFilters($filters);

        $ingredients   = Ingredient::forBusiness($business->id)->get();
        $lowStock      = Ingredient::forBusiness($business->id)->lowStock()->get();
        $movements     = \App\Models\StockMovement::where('business_id', $business->id)
            ->whereBetween(DB::raw('DATE(created_at)'), [$f['date_from'], $f['date_to']])
            ->with(['ingredient', 'user'])
            ->latest()
            ->paginate(30);

        return compact('ingredients', 'lowStock', 'movements', 'f');
    }

    public function profitReport(Business $business, array $filters): array
    {
        $f = $this->baseFilters($filters);
        $outlets = $business->outlets()->where('is_active', true)->get();

        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $business->id)
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$f['date_from'], $f['date_to']])
            ->when($f['outlet_id'], fn($q) => $q->where('orders.outlet_id', $f['outlet_id']))
            ->select(
                DB::raw('DATE(orders.created_at) as date'),
                DB::raw('SUM(order_items.subtotal) as revenue'),
                DB::raw('SUM(order_items.qty * order_items.cost_price) as cogs')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $totalRevenue = $rows->sum('revenue');
        $totalCogs    = $rows->sum('cogs');
        $totalProfit  = $totalRevenue - $totalCogs;
        $margin       = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return compact('rows', 'totalRevenue', 'totalCogs', 'totalProfit', 'margin', 'outlets', 'f');
    }

    public function exportSales(Business $business, array $filters)
    {
        // TODO: implement Excel export using maatwebsite/excel
        abort(501, 'Export Excel segera hadir.');
    }

    public function exportProducts(Business $business, array $filters)
    {
        abort(501, 'Export Excel segera hadir.');
    }
}
