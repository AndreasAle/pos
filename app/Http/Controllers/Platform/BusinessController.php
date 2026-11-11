<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\PlatformActivityLog;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $businesses = Business::query()
            ->with(['owner', 'latestSubscription.plan'])
            ->withCount(['outlets', 'users', 'products', 'orders'])
            ->withMax('orders as last_order_at', 'created_at')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('q')->trim()).'%';
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhereHas('owner', fn ($owner) => $owner->where('email', 'like', $search));
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                match ($request->string('status')->toString()) {
                    'disabled' => $query->where('is_active', false),
                    'trial' => $query->where('is_active', true)->whereHas('latestSubscription', fn ($q) => $q
                        ->where('status', 'trial')->whereDate('ends_at', '>=', today())),
                    'active' => $query->where('is_active', true)->whereHas('latestSubscription', fn ($q) => $q
                        ->where('status', 'active')->where(fn ($dates) => $dates->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))),
                    'overdue' => $query->whereHas('latestSubscription', fn ($q) => $q->where('status', 'pending')),
                    default => null,
                };
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('platform.businesses.index', compact('businesses'));
    }

    public function show(Business $business)
    {
        $business->load([
            'owner', 'outlets', 'latestSubscription.plan',
            'subscriptions' => fn ($q) => $q->with('plan')->latest(),
        ])->loadCount(['users', 'products', 'customers', 'orders']);

        $orderStats = $business->orders()
            ->where('status', 'paid')
            ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(grand_total), 0) as gross_revenue, MAX(created_at) as last_order_at')
            ->first();

        $plans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();
        $activity = PlatformActivityLog::with('admin')->where('business_id', $business->id)->latest()->limit(15)->get();

        return view('platform.businesses.show', compact('business', 'orderStats', 'plans', 'activity'));
    }

    public function updateStatus(Request $request, Business $business): RedirectResponse
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $old = $business->is_active;
        $business->update(['is_active' => $validated['is_active']]);

        PlatformActivityLog::record(
            'business.status_changed',
            ($business->is_active ? 'Mengaktifkan' : 'Menonaktifkan').' toko '.$business->name.'.',
            $business,
            $business,
            ['old' => $old, 'new' => $business->is_active]
        );

        return back()->with('success', 'Status toko berhasil diperbarui.');
    }

    public function updateSubscription(Request $request, Business $business): RedirectResponse
    {
        abort_unless(auth('platform')->user()->canManageSubscriptions(), 403);
        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'status' => ['required', 'in:trial,active,pending,expired,cancelled'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $subscription = DB::transaction(function () use ($business, $validated) {
            $business->subscriptions()->whereIn('status', ['trial', 'active'])
                ->update(['status' => 'cancelled']);

            return $business->subscriptions()->create([
                ...$validated,
                'starts_at' => today(),
            ]);
        });

        PlatformActivityLog::record(
            'subscription.changed',
            'Mengubah langganan toko '.$business->name.' menjadi '.$subscription->status.'.',
            $subscription,
            $business,
            ['plan_id' => $subscription->subscription_plan_id, 'ends_at' => $subscription->ends_at?->toDateString()]
        );

        return back()->with('success', 'Langganan toko berhasil diperbarui.');
    }

    public function extendTrial(Request $request, Business $business): RedirectResponse
    {
        abort_unless(auth('platform')->user()->canManageSubscriptions(), 403);
        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
        ]);

        $trial = $business->subscriptions()->where('status', 'trial')->latest('id')->first();
        if ($trial) {
            $base = $trial->ends_at && $trial->ends_at->isFuture() ? $trial->ends_at : today();
            $trial->update(['ends_at' => $base->copy()->addDays($validated['days'])]);
        } else {
            $trial = $business->subscriptions()->create([
                'subscription_plan_id' => $validated['subscription_plan_id'],
                'starts_at' => today(),
                'ends_at' => today()->addDays($validated['days']),
                'status' => 'trial',
                'notes' => 'Trial diberikan oleh tim platform.',
            ]);
        }

        PlatformActivityLog::record(
            'trial.extended',
            'Menambah trial toko '.$business->name.' selama '.$validated['days'].' hari.',
            $trial,
            $business,
            ['days' => $validated['days'], 'ends_at' => $trial->ends_at->toDateString()]
        );

        return back()->with('success', 'Masa trial berhasil diperpanjang.');
    }
}
