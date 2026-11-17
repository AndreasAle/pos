<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser hardening headers. Deliberately no Content-Security-Policy
 * yet: views use inline Alpine/scripts and CDN assets, so a CSP needs its own
 * report-only rollout (SEC-07 in docs/security/01-architecture-security-review.md).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff', false);
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $h->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()', false);

        // Public self-order and receipt pages may be embedded nowhere; admin
        // must not be framed (clickjacking on approve/void buttons).
        if (! $h->has('X-Frame-Options')) {
            $h->set('X-Frame-Options', 'SAMEORIGIN');
        }

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=15552000', false);
        }

        return $response;
    }
}
