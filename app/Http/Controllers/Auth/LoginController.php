<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** Failed attempts allowed per email+IP before the lockout kicks in. */
    private const MAX_ATTEMPTS = 5;

    /** Lockout duration in seconds. */
    private const DECAY_SECONDS = 60;

    // IP-independent cap per email: 20 failures per 15 minutes.
    private const ACCOUNT_MAX_ATTEMPTS = 20;
    private const ACCOUNT_DECAY_SECONDS = 900;

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $this->ensureIsNotRateLimited($request);

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $this->recordFailure($request);

            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $this->recordFailure($request);

            throw ValidationException::withMessages([
                'email' => 'Akun Anda tidak aktif.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();

        $destination = ! $user->hasVerifiedEmail()
            ? route('verification.notice')
            : ($user->requires_business_setup && ! $user->business_id
                ? route('onboarding.business')
                : route('dashboard'));

        return redirect()->intended($destination);
    }

    /**
     * Block further attempts once the limit is hit, so an attacker cannot grind
     * through a password list against a known email.
     *
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(Request $request): void
    {
        $key = RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)
            ? $this->throttleKey($request)
            : (RateLimiter::tooManyAttempts($this->accountKey($request), self::ACCOUNT_MAX_ATTEMPTS) ? $this->accountKey($request) : null);
        if ($key === null) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    /**
     * Keyed on email + IP so one attacker cannot lock out a legitimate user by
     * hammering their email from elsewhere.
     */
    private function recordFailure(Request $request): void
    {
        RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);
        RateLimiter::hit($this->accountKey($request), self::ACCOUNT_DECAY_SECONDS);
    }

    /**
     * IP-independent ceiling per email (SEC-04). request->ip() follows
     * X-Forwarded-For while all proxies are trusted, so the email|ip key alone
     * can be reset by rotating that header. Set higher than the per-IP limit
     * so a single stranger cannot cheaply lock a real owner out.
     */
    private function accountKey(Request $request): string
    {
        return 'login-account:'.Str::transliterate(Str::lower((string) $request->input('email')));
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // On a register paired to an outlet the next person signs in by PIN.
        if ($request->cookies->has(PinLoginController::OUTLET_COOKIE)) {
            return redirect()->route('pin.show');
        }

        return redirect()->route('login');
    }
}
