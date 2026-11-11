<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('platform')->user();
        if (! $admin?->is_active) {
            auth('platform')->logout();
            $request->session()->forget('platform_2fa_passed_at');

            return redirect()->route('platform.login')->withErrors([
                'email' => 'Akun platform Anda tidak aktif.',
            ]);
        }

        $passedAt = (int) $request->session()->get('platform_2fa_passed_at', 0);
        if ($passedAt > 0 && $passedAt < now()->subHours(8)->timestamp) {
            auth('platform')->logout();
            $request->session()->forget('platform_2fa_passed_at');

            return redirect()->route('platform.login')->withErrors([
                'email' => 'Sesi keamanan berakhir. Silakan login ulang.',
            ]);
        }

        if ($passedAt === 0) {
            return redirect()->route('platform.two-factor');
        }

        return $next($request);
    }
}
