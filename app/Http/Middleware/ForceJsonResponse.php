<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees a JSON reply from endpoints the POS screen talks to over fetch().
 *
 * Laravel decides the response format from the Accept header. A fetch() that
 * sets Content-Type but forgets Accept still gets HTML back on a validation
 * failure — a redirect, not a 422 — and the cashier sees
 * "unexpected token ... is not valid JSON" instead of the real reason.
 *
 * Rather than depend on every caller remembering the header, these routes
 * declare that they only ever speak JSON.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
