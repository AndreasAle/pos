<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Beranda berbentuk daftar menu ala aplikasi chat: tiap baris punya pratinjau data terkini.
        $bid = $business->id;
        $stats = [
            'products'    => DB::table('products')->where('business_id', $bid)->count(),
            'categories'  => DB::table('product_categories')->where('business_id', $bid)->count(),
            'customers'   => DB::table('customers')->where('business_id', $bid)->count(),
            'users'       => DB::table('users')->where('business_id', $bid)->count(),
            'ingredients' => DB::table('ingredients')->where('business_id', $bid)->count(),
            'promotions'  => DB::table('promotions')->where('business_id', $bid)->count(),
        ];
        $activeShift = $user->activeShift()->with('outlet')->first();
        $business->loadMissing('outlets');

        return view('dashboard.index', compact(
            'summary', 'topProducts', 'salesChart', 'paymentBreakdown', 'lowStock', 'recentOrders',
            'outlets', 'outletId', 'outletPerformance', 'stats', 'activeShift', 'business'
        ));
    }
}
