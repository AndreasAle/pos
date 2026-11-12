<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()->is_active) {
            Auth::logout();
            return redirect()->route('login')
                ->withErrors(['email' => 'Akun Anda tidak aktif. Hubungi administrator.']);
        }

        // Sesi bantuan tim Yoka dibatasi 60 menit.
        $expires = (int) $request->session()->get('platform_impersonation_expires_at', 0);
        if (Auth::check() && (bool) $request->session()->get('platform_impersonating', false) && $expires > 0 && $expires < now()->timestamp) {
            Auth::guard('web')->logout();
            $request->session()->forget(['platform_impersonating', 'platform_impersonated_business_id', 'platform_impersonation_expires_at']);

            return redirect()->route('platform.login')->withErrors(['email' => 'Sesi bantuan toko berakhir (maksimal 60 menit). Mulai lagi bila masih perlu.']);
        }

        return $next($request);
    }
}
