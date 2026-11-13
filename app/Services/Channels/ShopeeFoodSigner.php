<?php

namespace App\Services\Channels;

/**
 * Tanda tangan permintaan ke ShopeeFood OpenAPI.
 *
 * Aturan dari dokumentasi resmi (How to generate the request signature):
 *   - payload: body mentah untuk POST/PUT, query string untuk GET
 *   - isi yang ditandatangani, urut abjad nama parameter:
 *       access_token, app_id, path, payload, timestamp  (digabung dengan "&")
 *   - HMAC-SHA256 dengan vendor_secret sebagai kunci, hasil HEX huruf kecil
 */
class ShopeeFoodSigner
{
    public static function content(string $accessToken, string $appId, string $path, string $payload, int $timestamp): string
    {
        return "access_token={$accessToken}&app_id={$appId}&path={$path}&payload={$payload}&timestamp={$timestamp}";
    }

    public static function sign(string $vendorSecret, string $accessToken, string $appId, string $path, string $payload, int $timestamp): string
    {
        return hash_hmac('sha256', self::content($accessToken, $appId, $path, $payload, $timestamp), $vendorSecret);
    }
}
