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
        return self::usesGateway()
            ? (filled(config('services.yoka_fonnte.gateway_url')) || filled(config('services.yoka_fonnte.gateway_socket'))) && filled(config('services.yoka_fonnte.gateway_token'))
            : filled(config('services.yoka_fonnte.token'));
    }

    public static function usesGateway(): bool
    {
        return config('services.yoka_fonnte.driver') === 'gateway';
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
    public function send(string $phone, string $message, bool $typing = false, int $delay = 2): array
    {
        $target = self::normalize($phone);
        if (! $target) {
            return ['ok' => false, 'error' => 'Nomor WhatsApp tidak valid.'];
        }
        $gateway = self::usesGateway();
        $label = $gateway ? 'Gateway WA' : 'Fonnte';
        if (! self::configured()) {
            return ['ok' => false, 'error' => $gateway
                ? 'Gateway WA belum diatur (YOKA_WA_GATEWAY_URL / YOKA_WA_GATEWAY_TOKEN kosong).'
                : 'WhatsApp Yoka belum terhubung (YOKA_FONNTE_TOKEN kosong).'];
        }

        // Gateway meniru API Fonnte; bedanya hanya URL dan token. Gateway antre
        // satu per satu dengan jeda + efek mengetik, jadi timeout dibuat lebih longgar.
        $url = $gateway ? rtrim((string) (config('services.yoka_fonnte.gateway_url') ?: 'http://localhost'), '/').'/send' : 'https://api.fonnte.com/send';
        $token = $gateway ? config('services.yoka_fonnte.gateway_token') : config('services.yoka_fonnte.token');

        try {
            $request = Http::withHeaders(['Authorization' => $token])->asForm()->timeout($gateway ? 60 : 20);
            // Hosting bersama tanpa port lokal: gateway didengar lewat file socket.
            $socket = (string) config('services.yoka_fonnte.gateway_socket');
            if ($gateway && $socket !== '') {
                $request = $request->withOptions(['curl' => [CURLOPT_UNIX_SOCKET_PATH => $socket]]);
            }
            $response = $request
                ->post($url, ['target' => $target, 'message' => $message, 'typing' => $typing, 'delay' => max(0, min(15, $delay))]);

            if (! $response->successful() || $response->json('status') === false) {
                // Gateway menjawab 429 + alasan bila rem anti-spam menahan pesan.
                $reason = $response->json('reason') ?? mb_substr($response->body(), 0, 200);

                return ['ok' => false, 'error' => $label.' menolak pesan: '.$reason];
            }

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('Platform WA failed', ['driver' => $gateway ? 'gateway' : 'fonnte', 'exception' => class_basename($e)]);

            return ['ok' => false, 'error' => 'Gagal menghubungi '.$label.'.'];
        }
    }
}
