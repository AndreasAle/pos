<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Outlet;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->business_id && $user->business) {
            return redirect()->route('dashboard');
        }

        abort_unless($user->isOwner() && $user->requires_business_setup, 403);

        return view('onboarding.business', compact('user'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_type' => 'required|in:fnb,retail,service',
            'outlet_scale' => 'required|in:1,2-3,4-10,10+',
            'city' => 'required|string|max:100',
            'business_name' => 'required|string|max:255',
            'outlet_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:1000',
        ]);

        $business = DB::transaction(function () use ($request, $data) {
            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);

            if ($user->business_id && $user->business) {
                $user->update(['requires_business_setup' => false]);

                return $user->business;
            }

            abort_unless($user->isOwner() && $user->requires_business_setup, 403);

            $business = Business::create([
                'name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'outlet_scale' => $data['outlet_scale'],
                'slug' => Str::slug($data['business_name']).'-'.Str::lower(Str::random(5)),
                'phone' => $user->phone,
                'email' => $user->email,
                'address' => $data['address'] ?: null,
                'city' => $data['city'],
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'onboarding_completed_at' => now(),
                'settings' => [
                    'business_type' => $data['business_type'],
                    'enable_tax' => false,
                    'tax_percent' => 10,
                    'enable_service' => false,
                    'service_percent' => 5,
                    'allow_negative_stock' => false,
                    'receipt_header' => $data['business_name'],
                    'receipt_footer' => 'Terima kasih atas kunjungan Anda!',
                    'receipt_size' => '80mm',
                    'points_per_rupiah' => 0,
                ],
            ]);

            $outlet = Outlet::create([
                'business_id' => $business->id,
                'name' => $data['outlet_name'],
                'code' => 'MAIN',
                'address' => $data['address'] ?: null,
                'is_active' => true,
            ]);

            $user->update([
                'business_id' => $business->id,
                'outlet_id' => $outlet->id,
                'requires_business_setup' => false,
            ]);

            $freePlan = SubscriptionPlan::query()->where('slug', 'free')->first();
            if ($freePlan) {
                BusinessSubscription::create([
                    'business_id' => $business->id,
                    'subscription_plan_id' => $freePlan->id,
                    'starts_at' => now(),
                    'ends_at' => now()->addDays(7),
                    'status' => 'trial',
                ]);
            }

            return $business;
        });

        $request->user()->refresh();

        return redirect()->route('dashboard')->with(
            'success',
            'Bisnis '.$business->name.' siap digunakan. Yuk kenali ruang kerja Yoka POS.'
        );
    }

    public function completeTour(Request $request): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $request->user()->business->update(['dashboard_tour_completed_at' => now()]);

        return response()->json(['saved' => true]);
    }

    public function restartTour(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $request->user()->business->update(['dashboard_tour_completed_at' => null]);

        return redirect()->route('dashboard');
    }
}
