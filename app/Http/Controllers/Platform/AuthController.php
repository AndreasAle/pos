<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformActivityLog;
use App\Notifications\PlatformTwoFactorCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('platform')->check()) {
            return redirect()->route(
                session()->has('platform_2fa_passed_at') ? 'platform.dashboard' : 'platform.two-factor'
            );
        }

        return view('platform.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('platform')->attempt($credentials, false)) {
            PlatformActivityLog::record('login.failed', 'Percobaan login platform gagal.', null, null, ['email' => mb_substr((string) $credentials['email'], 0, 120)]);
            throw ValidationException::withMessages([
                'email' => 'Email atau password akun platform salah.',
            ]);
        }

        $admin = Auth::guard('platform')->user();
        if (! $admin->is_active) {
            Auth::guard('platform')->logout();

            throw ValidationException::withMessages([
                'email' => 'Akun platform Anda tidak aktif.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget(['platform_2fa_passed_at', 'platform_2fa_attempts']);

        if (! $this->sendCode($admin)) {
            Auth::guard('platform')->logout();

            return back()->withErrors([
                'email' => 'Kode keamanan gagal dikirim. Periksa konfigurasi email server.',
            ]);
        }

        return redirect()->route('platform.two-factor');
    }

    public function showTwoFactor(): View
    {
        return view('platform.auth.two-factor', [
            'admin' => Auth::guard('platform')->user(),
        ]);
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $usingRecovery = $request->filled('recovery_code');
        $request->validate($usingRecovery ? ['recovery_code' => ['required', 'string', 'max:20']] : ['code' => ['required', 'digits:6']]);
        $admin = Auth::guard('platform')->user();
        $attempts = (int) $request->session()->get('platform_2fa_attempts', 0) + 1;
        $request->session()->put('platform_2fa_attempts', $attempts);

        if ($attempts > 5) {
            $this->clearCode($admin);
            Auth::guard('platform')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('platform.login')->withErrors([
                'email' => 'Terlalu banyak kode yang salah. Silakan login ulang.',
            ]);
        }

        if ($usingRecovery) {
            $valid = $admin->consumeRecoveryCode($request->string('recovery_code')->toString());
            $field = 'recovery_code';
        } else {
            $valid = $admin->two_factor_code_hash
                && $admin->two_factor_expires_at?->isFuture()
                && Hash::check($request->string('code')->toString(), $admin->two_factor_code_hash);
            $field = 'code';
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                $field => $usingRecovery ? 'Kode pemulihan salah atau sudah dipakai.' : 'Kode salah atau sudah kedaluwarsa.',
            ]);
        }

        $this->clearCode($admin);
        $admin->forceFill(['last_login_at' => now()])->save();
        $request->session()->put('platform_2fa_passed_at', now()->timestamp);
        $request->session()->forget('platform_2fa_attempts');

        PlatformActivityLog::record($usingRecovery ? 'login.recovery' : 'login', $usingRecovery ? 'Login platform memakai kode pemulihan (sisa '.count((array) $admin->fresh()->recovery_codes).').' : 'Login platform berhasil dengan verifikasi dua langkah.');

        return redirect()->intended(route('platform.dashboard'));
    }

    public function resendTwoFactor(): RedirectResponse
    {
        $admin = Auth::guard('platform')->user();

        return $this->sendCode($admin)
            ? back()->with('success', 'Kode keamanan baru sudah dikirim.')
            : back()->withErrors(['code' => 'Kode gagal dikirim. Coba beberapa saat lagi.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        if (Auth::guard('platform')->check() && $request->session()->has('platform_2fa_passed_at')) {
            PlatformActivityLog::record('logout', 'Keluar dari dashboard platform.');
        }

        Auth::guard('web')->logout();
        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }

    private function sendCode(object $admin): bool
    {
        $code = (string) random_int(100000, 999999);
        $admin->forceFill([
            'two_factor_code_hash' => Hash::make($code),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        try {
            $admin->notifyNow(new PlatformTwoFactorCode($code));

            return true;
        } catch (Throwable $exception) {
            report($exception);
            $this->clearCode($admin);

            return false;
        }
    }

    private function clearCode(object $admin): void
    {
        $admin->forceFill([
            'two_factor_code_hash' => null,
            'two_factor_expires_at' => null,
        ])->save();
    }
}
