<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function index(Request $request)
    {
        $user     = auth()->user();
        $business = $user->business;
        $outletId = $request->input('outlet_id', $user->outlet_id);

        $summary        = $this->dashboard->getSummary($business, $outletId);
        $topProducts    = $this->dashboard->getTopProducts($business, $outletId);
        $salesChart     = $this->dashboard->getSalesChart($business, $outletId);
        $paymentBreakdown = $this->dashboard->getPaymentBreakdown($business, $outletId);
        $lowStock       = $this->dashboard->getLowStockIngredients($business, $outletId);
        $recentOrders   = $this->dashboard->getRecentOrders($business, $outletId);
        $outlets        = $business->outlets()->where('is_active', true)->get();
        $outletPerformance = $user->isOwner() || $user->isAdmin()
            ? $this->dashboard->getOutletPerformance($business)
            : [];

        return view('dashboard.index', compact(
            'summary', 'topProducts', 'salesChart', 'paymentBreakdown',
            'lowStock', 'recentOrders', 'outlets', 'outletId', 'outletPerformance'
        ));
    }
}
