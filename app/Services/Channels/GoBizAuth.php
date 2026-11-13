<?php

namespace App\Services\Channels;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OAuth 2.0 Authorization Code untuk GoBiz (Facilitator), sesuai dokumentasi resmi:
 * https://developer.gobiz.com/docs/api/auth/facilitator/authorization-code
 *
 * Merchant diarahkan ke GoAuth (login nomor HP + OTP), lalu kembali ke redirect_uri
 * dengan `code` sekali pakai (berlaku sekitar 2 menit). Code ditukar jadi token.
 */
class GoBizAuth
{
    public function enabled(): bool
    {
        return filled(config('channels.gofood.client_id'))
            && filled(config('channels.gofood.client_secret'))
            && filled(config('channels.gofood.redirect_uri'));
    }

    public function authorizeUrl(string $state): string
    {
        $this->requireEnabled();

        return config('channels.gofood.oauth_url').'/oauth2/auth?'.http_build_query([
            'client_id'     => config('channels.gofood.client_id'),
            'response_type' => 'code',
            'scope'         => config('channels.gofood.scopes'),
            'state'         => $state,
            'redirect_uri'  => config('channels.gofood.redirect_uri'),
            'user_type'     => 'merchant',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{access_token:string, refresh_token:?string, expires_in:int, scope:?string} */
    public function exchange(string $code): array
    {
        return $this->token([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => config('channels.gofood.redirect_uri'),
        ]);
    }

    /** @return array{access_token:string, refresh_token:?string, expires_in:int, scope:?string} */
    public function refresh(string $refreshToken): array
    {
        return $this->token([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    private function token(array $params): array
    {
        $this->requireEnabled();

        $res = Http::asForm()->timeout(15)->post(config('channels.gofood.oauth_url').'/oauth2/token', $params + [
            'client_id'     => config('channels.gofood.client_id'),
            'client_secret' => config('channels.gofood.client_secret'),
        ]);

        if (! $res->successful() || ! $res->json('access_token')) {
            // Jangan sertakan body mentah: bisa memuat kode atau token.
            throw new RuntimeException('GoBiz menolak permintaan token: '.($res->json('error_description') ?: $res->json('error') ?: 'HTTP '.$res->status()));
        }

        return [
            'access_token'  => $res->json('access_token'),
            'refresh_token' => $res->json('refresh_token'),
            'expires_in'    => (int) $res->json('expires_in', 3600),
            'scope'         => $res->json('scope'),
        ];
    }

    private function requireEnabled(): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('GoBiz belum dikonfigurasi (GOBIZ_CLIENT_ID, GOBIZ_CLIENT_SECRET, GOBIZ_REDIRECT_URI).');
        }
    }
}
