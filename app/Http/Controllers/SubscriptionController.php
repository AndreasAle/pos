<?php

namespace App\Http\Controllers;

use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Services\MidtransService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(protected MidtransService $midtrans) {}

    public function expired()
    {
        $business = auth()->user()->business;
        $sub      = $business->subscriptions()->with('plan')->latest()->first();

        if ($sub && $sub->isActive()) {
            return redirect()->route('dashboard');
        }

        $plans = SubscriptionPlan::where('is_active', true)->where('slug', '!=', 'free')->orderBy('sort_order')->get();

        return view('saas.expired', compact('sub', 'plans', 'business'));
    }

    public function plans()
    {
        $business = auth()->user()->business;
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('slug', '!=', 'free')
            ->where('price', '>', 0)
            ->orderBy('sort_order')
            ->get();
        $current = $business->activeSubscription()->with('plan')->first();
        $subs = $business->subscriptions()->with('plan')->latest()->limit(10)->get();
        $outletCount = $business->outlets()->where('is_active', true)->count();
        $midtransConfigured = $this->midtrans->isConfigured();
        $isSandbox = ! config('midtrans.is_production');

        return view('saas.plans', compact(
            'business', 'plans', 'current', 'subs', 'outletCount',
            'midtransConfigured', 'isSandbox'
        ));
    }

    public function current()
    {
        $business = auth()->user()->business;
        $subs     = $business->subscriptions()->with('plan')->latest()->paginate(10);
        $current  = $business->activeSubscription()->with('plan')->first();
        return view('saas.current', compact('subs', 'current', 'business'));
    }

    public function subscribe(SubscriptionPlan $plan)
    {
        $business = auth()->user()->business;

        abort_unless($plan->is_active, 404);

        if ((float) $plan->price > 0) {
            return redirect()->route('saas.plans')
                ->with('error', 'Paket berbayar harus diproses melalui checkout Midtrans.');
        }

        BusinessSubscription::create([
            'business_id'          => $business->id,
            'subscription_plan_id' => $plan->id,
            'starts_at'            => today(),
            'ends_at'              => today()->addDays(30),
            'status'               => 'active',
            'paid_at'              => now(),
            'notes'                => 'Manual activation',
        ]);

        return redirect()->route('saas.current')
            ->with('success', 'Paket ' . $plan->name . ' berhasil diaktifkan.');
    }
}
