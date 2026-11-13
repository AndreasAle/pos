<?php

/*
|--------------------------------------------------------------------------
| Kanal pesanan online (GoFood, ShopeeFood)
|--------------------------------------------------------------------------
|
| Semua nilai rahasia datang dari .env, tidak pernah dari repo. Sampai
| kredensial resmi dari Gojek / Shopee ada, kedua kanal berstatus "disabled":
| halaman Integrasi hanya mencatat pendaftaran dan endpoint masuk membalas 404.
|
| Dokumentasi resmi:
|   GoBiz     https://developer.gobiz.com/docs/api/intro
|   ShopeeFood https://developer.shopeefood.co.id/vendor/api-docs
*/

return [

    'gofood' => [
        'client_id'     => env('GOBIZ_CLIENT_ID'),
        'client_secret' => env('GOBIZ_CLIENT_SECRET'),
        // Sandbox dulu. Production: https://api.gobiz.co.id dan GoAuth production.
        'oauth_url'     => rtrim(env('GOBIZ_OAUTH_URL', 'https://integration-goauth.gojekapi.com'), '/'),
        'api_url'       => rtrim(env('GOBIZ_API_URL', 'https://api.partner-sandbox.gobiz.co.id'), '/'),
        // Harus sama persis dengan Redirect URI yang didaftarkan ke Gojek.
        'redirect_uri'  => env('GOBIZ_REDIRECT_URI'),
        'scopes'        => env('GOBIZ_SCOPES', 'openid offline partner:outlet:read gofood:catalog:read gofood:catalog:write gofood:order:read gofood:order:write'),
    ],

    'shopeefood' => [
        // Kredensial yang DIBERIKAN ShopeeFood kepada kita (untuk memanggil API mereka).
        'vendor_id'     => env('SHOPEEFOOD_VENDOR_ID'),
        'vendor_secret' => env('SHOPEEFOOD_VENDOR_SECRET'),
        // Staging: https://food-open.uat.shopee.co.id | Production: https://food-open.shopee.co.id
        'api_url'       => rtrim(env('SHOPEEFOOD_API_URL', 'https://food-open.uat.shopee.co.id'), '/'),

        // Kredensial yang KITA buat dan berikan ke ShopeeFood (untuk memanggil endpoint Yoka).
        // client_secret maksimal 200 karakter.
        'inbound_client_id'     => env('SHOPEEFOOD_INBOUND_CLIENT_ID'),
        'inbound_client_secret' => env('SHOPEEFOOD_INBOUND_CLIENT_SECRET'),
        // Segmen "shopee-indicator" di URL endpoint Yoka: /api/shopeefood/{indicator}/...
        'indicator'             => env('SHOPEEFOOD_INDICATOR'),
        'inbound_token_ttl'     => 3600,
    ],

];
