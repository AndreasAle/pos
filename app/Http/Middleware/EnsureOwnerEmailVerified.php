<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemilik bisnis yang baru mendaftar harus mengonfirmasi email sebelum memakai
 * aplikasi. Hanya role owner yang dicek: staf dan kasir dibuat oleh pemilik dan
 * masuk dengan PIN, jadi email mereka tidak pernah dikonfirmasi.
 */
class EnsureOwnerEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isOwner() && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? abort(403, 'Email belum dikonfirmasi.')
                : redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
