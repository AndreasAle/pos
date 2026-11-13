<?php

namespace App\Services\Channels;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Klien untuk memanggil ShopeeFood OpenAPI (arah Yoka ke Shopee).
 * Belum dipakai oleh alur apa pun sampai VendorID resmi tersedia.
 */
class ShopeeFoodClient
{
    public function enabled(): bool
    {
        return filled(config('channels.shopeefood.vendor_id')) && filled(config('channels.shopeefood.vendor_secret'));
    }

    /** Token akses, di-cache sampai sedikit sebelum kedaluwarsa. */
    public function token(): string
    {
        $this->requireEnabled();

        return Cache::remember('shopeefood:outbound-token', 3000, function () {
            $id = config('channels.shopeefood.vendor_id');
            $secret = config('channels.shopeefood.vendor_secret');

            $res = Http::asForm()
                ->withHeaders(['Authorization' => 'Basic '.base64_encode("{$id}:{$secret}")])
                ->timeout(15)
                ->post($this->url("/api/adaptor/{$id}/v1/auth/token"), [
                    'grant_type' => 'client_credentials',
                    'scope'      => 'all',
                ]);

            $token = $res->json('data.access_token');
            if (! $res->successful() || $res->json('code') !== 0 || ! $token) {
                throw new RuntimeException('ShopeeFood token gagal: '.($res->json('msg') ?: 'HTTP '.$res->status()));
            }

            return $token;
        });
    }

    /** Pesan Shopee bahwa menu toko sudah diperbarui (PUT .../store/menu/notification). */
    public function notifyMenuUpdated(string $storeId): Response
    {
        $id = config('channels.shopeefood.vendor_id');

        return $this->send('PUT', "/api/adaptor/{$id}/v1/store/menu/notification", ['store_id' => $storeId]);
    }

    /**
     * Permintaan bertanda tangan. $payload array dikirim sebagai JSON (dan itulah yang
     * ditandatangani, byte demi byte); $payload string dipakai apa adanya (query string GET).
     */
    public function send(string $method, string $path, array|string|null $payload = null): Response
    {
        $this->requireEnabled();

        $appId = (string) config('channels.shopeefood.vendor_id');
        $secret = (string) config('channels.shopeefood.vendor_secret');
        $token = $this->token();
        $timestamp = time();

        $body = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $payload;

        $http = Http::timeout(20)->withHeaders([
            'X-SF-AppID'       => $appId,
            'X-SF-AccessToken' => $token,
            'X-SF-RequestID'   => (string) Str::uuid(),
            'X-SF-Timestamp'   => (string) $timestamp,
            'X-SF-Signature'   => ShopeeFoodSigner::sign($secret, $token, $appId, $path, $body, $timestamp),
        ]);

        $url = $this->url($path);

        return is_array($payload)
            ? $http->withBody($body, 'application/json')->send($method, $url)
            : $http->send($method, $url.($body !== '' ? '?'.$body : ''));
    }

    private function url(string $path): string
    {
        return config('channels.shopeefood.api_url').$path;
    }

    private function requireEnabled(): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('ShopeeFood belum dikonfigurasi (SHOPEEFOOD_VENDOR_ID / SHOPEEFOOD_VENDOR_SECRET).');
        }
    }
}
