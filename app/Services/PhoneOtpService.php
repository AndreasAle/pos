<?php

namespace App\Services;

use App\Models\PhoneOtp;
use App\Models\User;

/**
 * Kode verifikasi WhatsApp untuk pemilik yang mendaftar dari aplikasi.
 *
 * Batas keras ada di sini, bukan di tampilan: kode 6 digit berlaku 5 menit,
 * maksimal 5 kali salah per kode, kirim ulang paling cepat 60 detik, dan
 * maksimal 5 kode per nomor per jam (melindungi nomor WhatsApp Yoka dari
 * penyalahgunaan form daftar). Kode hanya disimpan sebagai HMAC.
 */
class PhoneOtpService
{
    public const TTL_MINUTES     = 5;
    public const MAX_ATTEMPTS    = 5;
    public const RESEND_SECONDS  = 60;
    public const MAX_PER_HOUR    = 5;

    public function __construct(private PlatformWhatsApp $whatsapp) {}

    /** Detik sampai boleh kirim lagi; 0 berarti boleh sekarang. */
    public function secondsUntilResend(User $user): int
    {
        $last = PhoneOtp::where('user_id', $user->id)->latest('id')->first();
        if (! $last) {
            return 0;
        }

        return max(0, self::RESEND_SECONDS - (int) $last->created_at->diffInSeconds(now()));
    }

    /** Ada kode yang masih bisa dipakai (belum kedaluwarsa/terpakai/terkunci). */
    public function hasActiveCode(User $user): bool
    {
        return (bool) $this->active($user);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function send(User $user): array
    {
        $phone = PlatformWhatsApp::normalize((string) $user->phone);
        if (! $phone) {
            return ['ok' => false, 'error' => 'Nomor WhatsApp belum valid.'];
        }

        if (($wait = $this->secondsUntilResend($user)) > 0) {
            return ['ok' => false, 'error' => "Tunggu {$wait} detik sebelum kirim ulang."];
        }

        $sentLastHour = PhoneOtp::where('phone', $phone)->where('created_at', '>=', now()->subHour())->count();
        if ($sentLastHour >= self::MAX_PER_HOUR) {
            return ['ok' => false, 'error' => 'Terlalu banyak permintaan kode untuk nomor ini. Coba lagi dalam satu jam, atau pakai verifikasi email.'];
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Kode lama tidak berlaku lagi begitu kode baru dibuat.
        PhoneOtp::where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        PhoneOtp::create([
            'user_id'    => $user->id,
            'phone'      => $phone,
            'code_hash'  => $this->hash($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $result = $this->whatsapp->send(
            $phone,
            "Kode verifikasi Yoka POS: *{$code}*\n\nBerlaku ".self::TTL_MINUTES." menit. Jangan berikan kode ini kepada siapa pun, termasuk yang mengaku dari Yoka."
        );

        if (! ($result['ok'] ?? false)) {
            report(new \RuntimeException('OTP WhatsApp gagal: '.($result['error'] ?? 'tidak diketahui')));

            return ['ok' => false, 'error' => 'Kode belum bisa dikirim ke WhatsApp. Coba lagi sebentar, atau pakai verifikasi email.'];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function verify(User $user, string $code): array
    {
        $code = preg_replace('/\D/', '', $code);
        $otp  = $this->active($user);

        if (! $otp) {
            return ['ok' => false, 'error' => 'Kode sudah tidak berlaku. Kirim ulang kode baru.'];
        }

        if (strlen($code) !== 6 || ! hash_equals($otp->code_hash, $this->hash($code))) {
            $otp->increment('attempts');
            $left = self::MAX_ATTEMPTS - $otp->attempts;

            return ['ok' => false, 'error' => $left > 0
                ? "Kode salah. Sisa {$left} kali percobaan."
                : 'Kode salah terlalu banyak. Kirim ulang kode baru.'];
        }

        $otp->update(['consumed_at' => now()]);
        $user->forceFill(['phone_verified_at' => now()])->save();

        return ['ok' => true];
    }

    private function active(User $user): ?PhoneOtp
    {
        return PhoneOtp::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->latest('id')
            ->first();
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
