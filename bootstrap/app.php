<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);

        // The tablet pairing cookie carries its own HMAC, so it is readable
        // without Laravel's cookie encryption while still being tamper-evident.
        $middleware->encryptCookies(except: [
            \App\Http\Controllers\Auth\PinLoginController::OUTLET_COOKIE,
        ]);

        // A register paired to an outlet has no one who knows an email
        // password; send its expired sessions back to the PIN pad instead.
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) =>
            $request->cookies->has(\App\Http\Controllers\Auth\PinLoginController::OUTLET_COOKIE)
                ? route('pin.show')
                : route('login'));

        $middleware->alias([
            'business'     => \App\Http\Middleware\EnsureBusinessIsSet::class,
            'role'         => \App\Http\Middleware\RoleMiddleware::class,
            'subscription' => \App\Http\Middleware\EnsureSubscriptionActive::class,
            'json'         => \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
