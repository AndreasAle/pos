<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * PIN sign-in for the tablet register.
 *
 * A cashier standing at a queue should not be typing an email address, so the
 * tablet is paired to one outlet once and from then on a PIN is enough.
 *
 * The pairing is what makes a short PIN safe to use: it narrows the candidates
 * to the handful of staff at that outlet, so a four to six digit code does not
 * have to be unique across every tenant on the server.
 */
class PinLoginController extends Controller
{
    /** Cookie holding the paired outlet, kept for a year. */
    public const OUTLET_COOKIE = 'pos_outlet';
    private const COOKIE_DAYS  = 365;

    /** Wrong PINs allowed per outlet+device before a cool-off. */
    private const MAX_ATTEMPTS   = 5;
    private const DECAY_SECONDS  = 60;

    /** Roles that may sign in at a register. */
    private const ALLOWED_ROLES = ['cashier', 'admin', 'owner'];

    public function show(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('pos.index');
        }

        $outlet = $this->pairedOutlet($request);

        if (!$outlet) {
            return view('auth.pin-pair');
        }

        $staff = User::where('business_id', $outlet->business_id)
            ->where('is_active', true)
            ->whereIn('role', self::ALLOWED_ROLES)
            ->whereNotNull('pin')
            ->where(fn ($q) => $q->where('outlet_id', $outlet->id)->orWhereNull('outlet_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view('auth.pin-login', compact('outlet', 'staff'));
    }

    /**
     * Pair this device with an outlet. The code is printed on the outlet record
     * in the back office; it is not a secret, the PIN is.
     */
    public function pair(Request $request)
    {
        $request->validate([
            'outlet_code' => 'required|string|max:50',
        ]);

        $outlet = Outlet::where('code', trim($request->outlet_code))
            ->where('is_active', true)
            ->first();

        if (!$outlet) {
            throw ValidationException::withMessages([
                'outlet_code' => 'Kode outlet tidak ditemukan. Cek kembali di menu Outlet.',
            ]);
        }

        return redirect()->route('pin.show')->withCookie(
            cookie(self::OUTLET_COOKIE, $this->pairingToken($outlet), self::COOKIE_DAYS * 24 * 60)
        );
    }

    public function login(Request $request)
    {
        $request->validate([
            'pin' => 'required|string|min:4|max:8',
        ]);

        $outlet = $this->pairedOutlet($request);

        if (!$outlet) {
            return redirect()->route('pin.show');
        }

        $this->ensureIsNotRateLimited($request, $outlet);

        $candidates = User::where('business_id', $outlet->business_id)
            ->where('is_active', true)
            ->whereIn('role', self::ALLOWED_ROLES)
            ->whereNotNull('pin')
            ->where(fn ($q) => $q->where('outlet_id', $outlet->id)->orWhereNull('outlet_id'))
            ->get();

        $pin = trim($request->input('pin'));

        foreach ($candidates as $user) {
            if (Hash::check($pin, $user->pin)) {
                RateLimiter::clear($this->throttleKey($request, $outlet));

                Auth::login($user, true);
                $request->session()->regenerate();

                return redirect()->intended(route('pos.index'));
            }
        }

        RateLimiter::hit($this->throttleKey($request, $outlet), self::DECAY_SECONDS);

        throw ValidationException::withMessages([
            'pin' => 'PIN salah. Coba lagi.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The outlet pairing survives sign-out: the tablet stays this outlet's
        // register, only the person changes.
        return redirect()->route('pin.show');
    }

    /** Forget the pairing so the tablet can be moved to another outlet. */
    public function unpair(Request $request)
    {
        return redirect()->route('pin.show')
            ->withCookie(cookie()->forget(self::OUTLET_COOKIE));
    }

    /**
     * Signed pairing token: the outlet id plus an HMAC over it.
     *
     * Editing the id by hand invalidates the signature, so a tablet cannot be
     * repointed at another outlet to fish for its PINs.
     */
    private function pairingToken(Outlet $outlet): string
    {
        return $outlet->id . '.' . $this->signature((string) $outlet->id);
    }

    private function signature(string $value): string
    {
        return substr(hash_hmac('sha256', 'pos-outlet:' . $value, config('app.key')), 0, 32);
    }

    private function pairedOutlet(Request $request): ?Outlet
    {
        $token = (string) $request->cookie(self::OUTLET_COOKIE);

        if (!str_contains($token, '.')) {
            return null;
        }

        [$id, $mac] = explode('.', $token, 2);

        if (!ctype_digit($id) || !hash_equals($this->signature($id), $mac)) {
            return null;
        }

        return Outlet::where('id', $id)->where('is_active', true)->first();
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(Request $request, Outlet $outlet): void
    {
        $key = $this->throttleKey($request, $outlet);

        if (!RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'pin' => 'Terlalu banyak percobaan. Tunggu ' . RateLimiter::availableIn($key) . ' detik.',
        ]);
    }

    /**
     * Keyed on outlet and device so one tablet fumbling its PIN cannot lock the
     * registers at another outlet.
     */
    private function throttleKey(Request $request, Outlet $outlet): string
    {
        return 'pin:' . $outlet->id . '|' . $request->ip();
    }
}
