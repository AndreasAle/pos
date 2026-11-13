<?php

use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Middleware\EnsureBusinessIsSet;
use App\Http\Middleware\EnsureOwnerEmailVerified;
use App\Http\Middleware\EnsurePlatformTwoFactor;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\Localize;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Percayai semua proxy (ngrok, reverse proxy, dll)
        $middleware->trustProxies(at: '*');

        // Exclude Midtrans webhook from CSRF
        $middleware->validateCsrfTokens(except: [
            '/midtrans/callback',
            '/telegram/webhook',
        ]);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            Localize::class,
        ]);

        // The tablet pairing cookie carries its own HMAC, so it is readable
        // without Laravel's cookie encryption while still being tamper-evident.
        $middleware->encryptCookies(except: [
            PinLoginController::OUTLET_COOKIE,
        ]);

        // A register paired to an outlet has no one who knows an email
        // password; send its expired sessions back to the PIN pad instead.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('platform') || $request->is('platform/*')
                ? route('platform.login')
                : ($request->cookies->has(PinLoginController::OUTLET_COOKIE)
                    ? route('pin.show')
                    : route('login')));

        $middleware->alias([
            'business' => EnsureBusinessIsSet::class,
            'owner.verified' => EnsureOwnerEmailVerified::class,
            'role' => RoleMiddleware::class,
            'subscription' => EnsureSubscriptionActive::class,
            'json' => ForceJsonResponse::class,
            'platform.2fa' => EnsurePlatformTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
