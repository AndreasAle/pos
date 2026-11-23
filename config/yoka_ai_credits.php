<?php

/*
 * Kredit Tanya Yoka: saldo AI terpisah dari langganan.
 * Setiap jawaban yang memanggil model memotong kredit sesuai token yang benar-benar terpakai,
 * jadi biaya AI tidak lagi ditanggung harga langganan. Saldo habis = top-up lewat Midtrans.
 * AI di Telegram pemilik TIDAK memakai kredit ini (paket premium terpisah, diaktifkan manual).
 */
return [
    // Mati = Tanya Yoka berjalan seperti sebelumnya (hanya batas harian). Nyalakan per server.
    'enabled' => (bool) env('FEATURE_AI_CREDITS', false),

    // Token tertimbang = token input + 4 x token output (output gpt-4.1-mini 4x lebih mahal).
    // 1 kredit = sampai 12.000 token tertimbang; pertanyaan biasa sekitar 1 kredit.
    'tokens_per_credit' => (int) env('AI_CREDIT_TOKENS', 12000),
    'output_weight' => 4,
    'max_credits_per_answer' => 10,

    // Kredit gratis per bulan menurut paket aktif (slug). Hangus di akhir bulan, tidak menumpuk.
    'free_monthly' => [
        'free' => 20,        // masa coba
        'starter' => 30,     // YokaBasic
        'business' => 100,
        'enterprise' => 100,
    ],

    // Kredit top-up berlaku 12 bulan sejak top-up terakhir.
    'paid_validity_months' => 12,

    // Paket top-up resmi. Harga hanya dari sini; nominal pembayaran dicek sama persis.
    'packs' => [
        'ai-100' => ['name' => 'Starter', 'credits' => 100, 'price' => 15000],
        'ai-300' => ['name' => 'Reguler', 'credits' => 300, 'price' => 39000],
        'ai-1000' => ['name' => 'Hemat', 'credits' => 1000, 'price' => 99000],
    ],
];
