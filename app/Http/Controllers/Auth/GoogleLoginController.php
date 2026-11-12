<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleLoginController extends Controller
{
    private const REGISTRATION_SESSION = 'auth.google_registration';

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

        if (! $user) {
            $request->session()->put(self::REGISTRATION_SESSION, [
                'google_id' => $googleId,
                'email' => $email,
                'name' => trim((string) $googleUser->getName()) ?: Str::before($email, '@'),
                'expires_at' => now()->addMinutes(15)->timestamp,
            ]);

            return redirect()->route('auth.google.register');
        }

        if ($user->google_id && ! hash_equals($user->google_id, $googleId)) {
            return $this->loginError('Akun Yoka ini sudah terhubung ke akun Google lain.');
        }

        if (! $user->is_active) {
            return $this->loginError('Akun Anda tidak aktif. Hubungi administrator.');
        }

        if ((! $user->business_id || ! $user->business) && ! $user->requires_business_setup) {
            return $this->loginError('Akun Anda belum terhubung ke bisnis mana pun. Hubungi pemilik bisnis.');
        }

        if ($user->business && ! $user->business->is_active) {
            return $this->loginError('Bisnis Anda tidak aktif. Hubungi administrator.');
        }

        $user->forceFill([
            'google_id' => $googleId,
            'email_verified_at' => $user->email_verified_at ?: now(),
        ])->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        $destination = $user->requires_business_setup && ! $user->business_id
            ? route('onboarding.business')
            : route('dashboard');

        return redirect()->intended($destination);
    }

    public function showRegistration(Request $request): View|RedirectResponse
    {
        $pending = $this->pendingRegistration($request);

        if (! $pending) {
            return $this->loginError('Sesi Google telah berakhir. Pilih kembali akun Google yang ingin digunakan.');
        }

        return view('auth.google-register', compact('pending'));
    }

    public function register(Request $request): RedirectResponse
    {
        $pending = $this->pendingRegistration($request);

        if (! $pending) {
            return $this->loginError('Sesi Google telah berakhir. Pilih kembali akun Google yang ingin digunakan.');
        }

        $user = DB::transaction(function () use ($pending) {
            $userByGoogle = User::query()->where('google_id', $pending['google_id'])->lockForUpdate()->first();
            $userByEmail = User::query()->whereRaw('LOWER(email) = ?', [$pending['email']])->lockForUpdate()->first();

            if ($userByGoogle && $userByEmail && ! $userByGoogle->is($userByEmail)) {
                abort(409, 'Identitas Google bertabrakan dengan akun lain.');
            }

            $user = $userByGoogle ?: $userByEmail;

            if ($user) {
                if ($user->google_id && ! hash_equals($user->google_id, $pending['google_id'])) {
                    abort(409, 'Email ini sudah terhubung ke akun Google lain.');
                }

                $user->forceFill([
                    'google_id' => $pending['google_id'],
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ])->save();

                return $user;
            }

            $user = new User([
                'business_id' => null,
                'outlet_id' => null,
                'name' => $pending['name'],
                'email' => $pending['email'],
                'google_id' => $pending['google_id'],
                'password' => Str::random(64),
                'role' => 'owner',
                'is_active' => true,
                'requires_business_setup' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()]);
            $user->save();

            return $user;
        });

        if (! $user->is_active) {
            return $this->loginError('Akun Anda tidak aktif. Hubungi administrator.');
        }

        $request->session()->forget(self::REGISTRATION_SESSION);
        Auth::login($user, true);
        $request->session()->regenerate();

        $destination = $user->requires_business_setup && ! $user->business_id
            ? route('onboarding.business')
            : route('dashboard');

        return redirect($destination)->with('success', 'Akun Google berhasil didaftarkan. Lanjutkan dengan informasi bisnis Anda.');
    }

    /** @return array{google_id:string,email:string,name:string,expires_at:int}|null */
    private function pendingRegistration(Request $request): ?array
    {
        $pending = $request->session()->get(self::REGISTRATION_SESSION);

        if (! is_array($pending)
            || empty($pending['google_id'])
            || empty($pending['email'])
            || empty($pending['expires_at'])
            || (int) $pending['expires_at'] < now()->timestamp) {
            $request->session()->forget(self::REGISTRATION_SESSION);

            return null;
        }

        return $pending;
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
