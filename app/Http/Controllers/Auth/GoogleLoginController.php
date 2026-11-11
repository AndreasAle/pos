<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleLoginController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->loginError('Login Google belum dikonfigurasi. Silakan masuk memakai email dan password.');
        }

        // OAuth state disimpan di sesi. Mulai alurnya dari domain utama agar
        // callback ke yokapos.com membaca cookie/sesi yang sama, termasuk saat
        // pengguna semula membuka situs lama di pos.conweb.id.
        $canonicalHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (app()->isProduction() && $canonicalHost && $request->getHost() !== $canonicalHost) {
            return redirect()->away(rtrim((string) config('app.url'), '/').'/auth/google');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->loginError('Login Google belum dikonfigurasi. Silakan masuk memakai email dan password.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            Log::warning('Google login callback gagal.', [
                'exception' => $exception::class,
            ]);

            return $this->loginError('Login Google dibatalkan atau gagal. Silakan coba lagi.');
        }

        $googleId = trim((string) $googleUser->getId());
        $email = mb_strtolower(trim((string) $googleUser->getEmail()));

        if ($googleId === '' || $email === '' || ! $this->hasVerifiedEmail($googleUser)) {
            return $this->loginError('Akun Google Anda tidak menyediakan email yang sudah terverifikasi.');
        }

        $userByGoogle = User::query()->where('google_id', $googleId)->first();
        $userByEmail = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($userByGoogle && $userByEmail && ! $userByGoogle->is($userByEmail)) {
            Log::warning('Google login ditolak karena identitas bertabrakan.', [
                'google_user_id' => $userByGoogle->id,
                'email_user_id' => $userByEmail->id,
            ]);

            return $this->loginError('Akun Google tersebut tidak cocok dengan akun Yoka Anda. Hubungi administrator.');
        }

        $user = $userByGoogle ?: $userByEmail;

        // Pendaftaran bisnis tetap melalui form registrasi agar nama bisnis,
        // outlet, role, serta trial dibuat lengkap dan tidak menjadi tenant yatim.
        if (! $user) {
            return $this->loginError('Email Google ini belum terdaftar di Yoka. Daftarkan bisnis Anda terlebih dahulu.');
        }

        if ($user->google_id && ! hash_equals($user->google_id, $googleId)) {
            return $this->loginError('Akun Yoka ini sudah terhubung ke akun Google lain.');
        }

        if (! $user->is_active) {
            return $this->loginError('Akun Anda tidak aktif. Hubungi administrator.');
        }

        if (! $user->business_id || ! $user->business) {
            return $this->loginError('Akun Anda belum terhubung ke bisnis mana pun. Hubungi pemilik bisnis.');
        }

        if (! $user->business->is_active) {
            return $this->loginError('Bisnis Anda tidak aktif. Hubungi administrator.');
        }

        $user->forceFill([
            'google_id' => $googleId,
            'email_verified_at' => $user->email_verified_at ?: now(),
        ])->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function hasVerifiedEmail(SocialiteUser $googleUser): bool
    {
        $raw = $googleUser->getRaw();

        return filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL
        );
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function loginError(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }
}
