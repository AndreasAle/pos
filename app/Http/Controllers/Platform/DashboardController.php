<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Order;
use App\Models\PlatformActivityLog;
use App\Models\WithdrawalRequest;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $admin = auth('platform')->user();
        $validSubscription = fn ($query) => $query
            ->whereIn('status', ['trial', 'active'])
            ->where(fn ($dates) => $dates->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()));

        $paidActive = Business::where('is_active', true)
            ->whereHas('latestSubscription', fn ($q) => $validSubscription($q)->where('status', 'active'))->count();
        $trialBusinesses = Business::where('is_active', true)
            ->whereHas('latestSubscription', fn ($q) => $validSubscription($q)->where('status', 'trial'))->count();
        $disabledBusinesses = Business::where('is_active', false)->count();
        $overdueBusinesses = Business::where('is_active', true)
            ->whereHas('latestSubscription', fn ($q) => $q->where('status', 'pending'))->count();
        $totalBusinesses = Business::count();

        $stats = [
            'businesses' => $totalBusinesses,
            'active_businesses' => $paidActive,
            'disabled_businesses' => $disabledBusinesses,
            'trial_businesses' => $trialBusinesses,
            'overdue_businesses' => $overdueBusinesses,
            'stopped_businesses' => max(0, $totalBusinesses - $paidActive - $trialBusinesses - $disabledBusinesses - $overdueBusinesses),
            'new_this_week' => Business::where('created_at', '>=', now()->startOfWeek())->count(),
            'orders' => Order::where('status', 'paid')->count(),
            'gross_revenue' => $admin->canViewFinancials()
                ? (float) Order::where('status', 'paid')->sum('grand_total')
                : null,
            'subscription_revenue' => $admin->canViewFinancials()
                ? (float) BusinessSubscription::query()
                    ->join('subscription_plans', 'subscription_plans.id', '=', 'business_subscriptions.subscription_plan_id')
                    ->whereBetween('business_subscriptions.paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('subscription_plans.price')
                : null,
            'pending_withdrawals' => WithdrawalRequest::where('status', 'pending')->count(),
        ];

        $recentBusinesses = Business::with(['owner', 'latestSubscription.plan'])
            ->withMax('orders as last_order_at', 'created_at')
            ->latest()->limit(8)->get();

        $expiringTrials = BusinessSubscription::with(['business.owner', 'plan'])
            ->where('status', 'trial')
            ->whereBetween('ends_at', [today(), today()->addDays(7)])
            ->orderBy('ends_at')->limit(8)->get();

        $recentActivity = PlatformActivityLog::with(['admin', 'business'])
            ->latest()->limit(8)->get();

        return view('platform.dashboard', compact(
            'stats', 'recentBusinesses', 'expiringTrials', 'recentActivity', 'admin'
        ));
    }
}
