<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessIsSet
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // An account with no business has nowhere to go: this app only creates
        // businesses during registration, and the old redirect pointed at a
        // route that does not exist — every orphaned account hit a 500 and was
        // then locked in a loop, because /login bounces signed-in users back to
        // the dashboard. Sign them out instead and say why.
        //
        // Accounts end up here when their business is deleted: users.business_id
        // is nullOnDelete, so the row survives without a tenant.
        if (!$user || !$user->business_id || !$user->business) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda belum terhubung ke bisnis mana pun. Hubungi pemilik atau daftarkan bisnis baru.',
            ]);
        }

        if (!$user->business->is_active) {
            abort(403, 'Bisnis Anda tidak aktif. Hubungi administrator.');
        }

        // Share business to all views
        view()->share('currentBusiness', $user->business);
        view()->share('currentUser', $user);

        return $next($request);
    }
}
