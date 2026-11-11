<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\PlatformActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(Request $request, Business $business): RedirectResponse
    {
        abort_unless(auth('platform')->user()->canImpersonate(), 403);
        abort_unless($business->is_active, 422, 'Toko yang dinonaktifkan tidak dapat dimasuki.');

        $owner = $business->owner()->where('is_active', true)->first();
        abort_unless($owner, 422, 'Toko ini tidak memiliki owner aktif.');

        PlatformActivityLog::record(
            'impersonation.started',
            'Masuk sebagai owner toko '.$business->name.'.',
            $owner,
            $business,
            ['owner_email' => $owner->email]
        );

        $request->session()->put([
            'platform_impersonating' => true,
            'platform_impersonated_business_id' => $business->id,
        ]);
        Auth::guard('web')->login($owner);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Anda sedang membantu toko '.$business->name.'.');
    }

    public function stop(Request $request): RedirectResponse
    {
        $businessId = (int) $request->session()->get('platform_impersonated_business_id');
        $business = Business::find($businessId);

        if ($request->session()->boolean('platform_impersonating')) {
            PlatformActivityLog::record(
                'impersonation.stopped',
                'Mengakhiri sesi bantuan toko '.($business?->name ?? '#'.$businessId).'.',
                null,
                $business
            );
        }

        Auth::guard('web')->logout();
        $request->session()->forget(['platform_impersonating', 'platform_impersonated_business_id']);
        $request->session()->regenerate();

        return $business
            ? redirect()->route('platform.businesses.show', $business)
            : redirect()->route('platform.dashboard');
    }
}
