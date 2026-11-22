<?php

/*
 * Adit Business Development 2.0. Flag fitur; kebijakan komersial TETAP berlaku walau flag dimatikan.
 * Semua yang mengirim pesan keluar default mati.
 */
return [
    'discovery' => (bool) env('ADIT_BD_DISCOVERY', true),            // fakta, kualifikasi, pertanyaan berikutnya
    'objections' => (bool) env('ADIT_BD_OBJECTIONS', true),          // taksonomi keberatan + strategi
    'negotiation_advisor' => (bool) env('ADIT_BD_NEGOTIATION', true), // analisis harga + proposal untuk approval
    'trial_recommendations' => (bool) env('ADIT_BD_TRIAL_RECO', true), // rekomendasi follow-up trial (hanya tampil)
    'trial_followup_drafts' => (bool) env('ADIT_BD_TRIAL_DRAFTS', false), // buat draf WA follow-up trial (tetap butuh "Setujui & kirim")

    // Kebijakan harga. Belum ada promo resmi: diskon yang disetujui otomatis = 0%.
    // Semua permintaan di bawah harga resmi menjadi proposal yang harus disetujui manusia.
    'approved_discount_pct' => 0,
    // Biaya per pelanggan per bulan untuk analisis margin. null = belum diketahui -> INSUFFICIENT_DATA.
    'cost_per_customer_month' => env('ADIT_BD_COST_PER_CUSTOMER') !== null ? (float) env('ADIT_BD_COST_PER_CUSTOMER') : null,
    'payment_fee_pct' => env('ADIT_BD_PAYMENT_FEE_PCT') !== null ? (float) env('ADIT_BD_PAYMENT_FEE_PCT') : null,
    // Harga minimum yang boleh diusulkan ke reviewer (bukan ke prospek). null = tidak ada batas bawah otomatis.
    'floor_price' => env('ADIT_BD_FLOOR_PRICE') !== null ? (float) env('ADIT_BD_FLOOR_PRICE') : null,
    'trial_ending_days' => 2,

    // Adit BD 2.1: kualitas percakapan. Semua hanya mengubah cara menulis/menyaring balasan, bukan izin.
    'bd21' => [
        'opening' => (bool) env('ADIT_OPENING_STRATEGY_ENABLED', true),          // pembuka pendek tanpa link trial, nama bisnis bukan nama orang
        'strategy' => (bool) env('ADIT_CONVERSATION_STRATEGY_ENABLED', true),    // tujuan balasan, aturan bertanya, waktu link trial, closing
        'quality' => (bool) env('ADIT_RESPONSE_QUALITY_ENABLED', true),          // saring klaim/URL tak terverifikasi, pertanyaan berlebih
        'one_message' => (bool) env('ADIT_ONE_MESSAGE_PER_TURN', false),         // true = paksa satu pesan per giliran
        'max_bubbles' => (int) env('ADIT_MAX_BUBBLES', 2),                        // bila one_message=false: maks bubble berjeda per giliran (1-3)
        'playbook' => (bool) env('ADIT_INDUSTRY_PLAYBOOK_ENABLED', true),
        'hardware' => (bool) env('ADIT_HARDWARE_OPPORTUNITY_ENABLED', true),     // catat minat printer; bundel -> approval
        'coalesce_seconds' => (int) env('ADIT_INBOUND_COALESCE_SECONDS', 0),     // 0 = mati; 8 disarankan; maks 15 detik (lewat queue)
        'reply_lock_wait' => 45,                                                 // detik menunggu balasan lain ke nomor yang sama selesai
    ],
    // Nomor uji internal (pisahkan koma, format 62...). Boleh jadi prospek walau terdaftar sebagai toko, untuk tes alur Adit.
    // Diisi hanya di .env server; jangan taruh nomor di repo publik.
    'test_numbers' => array_values(array_filter(array_map(fn ($n) => preg_replace('/\D/', '', $n), explode(',', (string) env('ADIT_TEST_NUMBERS', ''))))),
];
