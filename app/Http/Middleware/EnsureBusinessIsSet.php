<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessIsSet
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->business_id) {
            return redirect()->route('business.create')
                ->with('info', 'Silakan lengkapi data bisnis Anda terlebih dahulu.');
        }

        if (!$user->business || !$user->business->is_active) {
            abort(403, 'Bisnis Anda tidak aktif. Hubungi administrator.');
        }

        // Share business to all views
        view()->share('currentBusiness', $user->business);
        view()->share('currentUser', $user);

        return $next($request);
    }
}
