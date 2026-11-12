<?php

return [
    // API Server Key authenticates backend requests (Snap, Core API, status).
    'server_key'      => env('MIDTRANS_SERVER_KEY', ''),
    'client_key'      => env('MIDTRANS_CLIENT_KEY', ''),

    // New Midtrans dashboards expose a separate secret for webhook signatures.
    // Fall back to the Server Key for legacy accounts that still use one key.
    'signature_secret' => env('MIDTRANS_SIGNATURE_SECRET', env('MIDTRANS_SERVER_KEY', '')),

    'is_production'   => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized'    => true,
    'is_3ds'          => true,
    'snap_url'        => env('MIDTRANS_IS_PRODUCTION', false)
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js',
];
