<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Outlet;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'outlet_name'   => 'required|string|max:255',
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8|confirmed',
            'phone'         => 'nullable|string|max:20',
        ]);

        DB::transaction(function () use ($request) {
            // Create business
            $business = Business::create([
                'name'     => $request->business_name,
                'slug'     => Str::slug($request->business_name) . '-' . Str::random(4),
                'phone'    => $request->phone,
                'email'    => $request->email,
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'settings' => [
                    'enable_tax'          => false,
                    'tax_percent'         => 10,
                    'enable_service'      => false,
                    'service_percent'     => 5,
                    'allow_negative_stock'=> false,
                    'receipt_header'      => $request->business_name,
                    'receipt_footer'      => 'Terima kasih atas kunjungan Anda!',
                    'receipt_size'        => '80mm',
                    'points_per_rupiah'   => 0,
                ],
            ]);

            // Create default outlet
            $outlet = Outlet::create([
                'business_id' => $business->id,
                'name'        => $request->outlet_name,
                'code'        => 'MAIN',
                'is_active'   => true,
            ]);

            // Create owner user
            $user = User::create([
                'business_id' => $business->id,
                'outlet_id'   => $outlet->id,
                'name'        => $request->name,
                'email'       => $request->email,
                'password'    => $request->password,
                'role'        => 'owner',
                'is_active'   => true,
                'phone'       => $request->phone,
            ]);

            // Assign 7-hari free trial — semua fitur terbuka selama masa trial
            $freePlan = SubscriptionPlan::where('slug', 'free')->first();
            if ($freePlan) {
                BusinessSubscription::create([
                    'business_id'          => $business->id,
                    'subscription_plan_id' => $freePlan->id,
                    'starts_at'            => now(),
                    'ends_at'              => now()->addDays(7),
                    'status'               => 'trial',
                ]);
            }

            Auth::login($user);
        });

        return redirect()->route('dashboard')
            ->with('success', 'Bisnis Anda berhasil didaftarkan! Selamat datang di FNB POS System.');
    }
}
