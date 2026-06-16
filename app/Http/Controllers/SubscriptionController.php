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
        $plans       = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();
        $current     = auth()->user()->business->activeSubscription()->with('plan')->first();
        $midtransKey = config('midtrans.client_key');
        return view('saas.plans', compact('plans', 'current', 'midtransKey'));
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

        if ($this->midtrans->isConfigured() && (float) $plan->price > 0) {
            // Redirect to Midtrans payment page
            return redirect()->route('midtrans.subscription', $plan);
        }

        // Fallback: manual activation (free plan or Midtrans not configured)
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
