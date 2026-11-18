<?php

return [

    'payments' => [
        'default' => env('PAYMENT_PROVIDER', 'bayarpro'),
    ],

    'bayarpro' => [
        'url' => env('BAYARPRO_BASE_URL', 'https://bayar-pro.com'),
        'server_key' => env('BAYARPRO_SERVER_KEY'),
        'merchant_id' => env('BAYARPRO_MERCHANT_ID'),
        'webhook_secret' => env('BAYARPRO_WEBHOOK_SECRET'),
    ],

    'yoka_ai' => [
        'key' => env('YOKA_AI_API_KEY'),
        'url' => env('YOKA_AI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('YOKA_AI_MODEL', 'gpt-4.1-mini'),
        'deep_model' => env('YOKA_AI_MODEL_DEEP'),
        'timeout' => (int) env('YOKA_AI_TIMEOUT', 25),
        'max_history' => (int) env('YOKA_AI_MAX_HISTORY', 8),
        'daily_request_budget' => (int) env('YOKA_AI_DAILY_REQUEST_BUDGET', 250),
        'daily_cost_budget_usd' => (float) env('YOKA_AI_DAILY_COST_BUDGET_USD', 5),
        'input_cost_per_million' => (float) env('YOKA_AI_INPUT_COST_PER_MILLION', 0.40),
        'output_cost_per_million' => (float) env('YOKA_AI_OUTPUT_COST_PER_MILLION', 1.60),
        'prompt_version' => '2026-11-12.1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    'telegram_office' => [
        'timeout' => (int) env('TELEGRAM_OFFICE_TIMEOUT', 10),
        'daily_briefing_time' => env('TELEGRAM_OFFICE_BRIEFING_TIME', '08:00'),
        'bots' => [
            'chief' => ['token' => env('TELEGRAM_RAMLI_TOKEN'), 'username' => env('TELEGRAM_RAMLI_USERNAME'), 'webhook_secret' => env('TELEGRAM_RAMLI_WEBHOOK_SECRET')],
            'marketing' => ['token' => env('TELEGRAM_YUSUF_TOKEN'), 'username' => env('TELEGRAM_YUSUF_USERNAME'), 'webhook_secret' => env('TELEGRAM_YUSUF_WEBHOOK_SECRET')],
            'ads' => ['token' => env('TELEGRAM_YONO_TOKEN'), 'username' => env('TELEGRAM_YONO_USERNAME'), 'webhook_secret' => env('TELEGRAM_YONO_WEBHOOK_SECRET')],
            'finance' => ['token' => env('TELEGRAM_LUNA_TOKEN'), 'username' => env('TELEGRAM_LUNA_USERNAME'), 'webhook_secret' => env('TELEGRAM_LUNA_WEBHOOK_SECRET')],
            'accounting' => ['token' => env('TELEGRAM_NANDA_TOKEN'), 'username' => env('TELEGRAM_NANDA_USERNAME'), 'webhook_secret' => env('TELEGRAM_NANDA_WEBHOOK_SECRET')],
            'cs' => ['token' => env('TELEGRAM_ADIT_TOKEN'), 'username' => env('TELEGRAM_ADIT_USERNAME'), 'webhook_secret' => env('TELEGRAM_ADIT_WEBHOOK_SECRET')],
            'sales' => ['token' => env('TELEGRAM_YOSEF_TOKEN'), 'username' => env('TELEGRAM_YOSEF_USERNAME'), 'webhook_secret' => env('TELEGRAM_YOSEF_WEBHOOK_SECRET')],
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost'), '/').'/auth/google/callback'),
    ],

    // Kredensial tim AI hanya disimpan di environment server.
    'meta_marketing' => [
        'access_token' => env('META_MARKETING_ACCESS_TOKEN'),
        'ad_account_id' => env('META_AD_ACCOUNT_ID'),
        'instagram_account_id' => env('META_INSTAGRAM_ACCOUNT_ID'),
    ],

    'tiktok_marketing' => [
        'access_token' => env('TIKTOK_MARKETING_ACCESS_TOKEN'),
        'advertiser_id' => env('TIKTOK_ADVERTISER_ID'),
    ],

    'whatsapp_business' => [
        'access_token' => env('WHATSAPP_BUSINESS_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    ],

    // WhatsApp resmi tim Yoka (Fonnte). Hanya dipakai setelah tugas disetujui manusia.
    'yoka_fonnte' => [
        'token' => env('YOKA_FONNTE_TOKEN'),
        // Bagian rahasia di URL webhook pesan masuk. Kosong = webhook mati.
        'webhook_secret' => env('YOKA_FONNTE_WEBHOOK_SECRET'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
