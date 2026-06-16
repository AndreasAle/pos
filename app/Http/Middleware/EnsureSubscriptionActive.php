<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    /**
     * Routes that must stay reachable even when the trial/subscription has expired,
     * so the owner can still upgrade, log out, or manage their account.
     */
    protected array $allowedRouteNames = [
        'subscription.expired',
        'saas.plans',
        'saas.current',
        'saas.subscribe',
        'profile',
        'profile.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->user()?->business;
        $sub      = $business?->activeSubscription;

        if ($business && (!$sub || !$sub->isActive())) {
            if (!in_array($request->route()?->getName(), $this->allowedRouteNames, true)) {
                return redirect()->route('subscription.expired');
            }
        }

        return $next($request);
    }
}
