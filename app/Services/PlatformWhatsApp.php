<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pengirim WhatsApp resmi milik tim Yoka (bukan token Fonnte milik toko).
 * Hanya dipanggil setelah tugas disetujui manusia.
 */
class PlatformWhatsApp
{
    public static function configured(): bool
    {
        return filled(config('services.yoka_fonnte.token'));
    }

    public static function normalize(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return preg_match('/^62\d{8,13}$/', $digits) ? $digits : null;
    }

    /** @return array{ok:bool, error:?string} */
    public function send(string $phone, string $message): array
    {
        $target = self::normalize($phone);
        if (! $target) {
            return ['ok' => false, 'error' => 'Nomor WhatsApp tidak valid.'];
        }
        if (! self::configured()) {
            return ['ok' => false, 'error' => 'WhatsApp Yoka belum terhubung (YOKA_FONNTE_TOKEN kosong).'];
        }

        try {
            $response = Http::withHeaders(['Authorization' => config('services.yoka_fonnte.token')])
                ->asForm()->timeout(20)
                ->post('https://api.fonnte.com/send', ['target' => $target, 'message' => $message, 'typing' => false, 'delay' => 2]);

            if (! $response->successful() || $response->json('status') === false) {
                return ['ok' => false, 'error' => 'Fonnte menolak pesan: '.mb_substr($response->body(), 0, 200)];
            }

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('Platform WA failed', ['exception' => class_basename($e)]);

            return ['ok' => false, 'error' => 'Gagal menghubungi Fonnte.'];
        }
    }
}
