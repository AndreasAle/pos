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
    // Nomor uji internal (pisahkan koma, format 62...). Boleh jadi prospek walau terdaftar sebagai toko, untuk tes alur Adit.
    // Diisi hanya di .env server; jangan taruh nomor di repo publik.
    'test_numbers' => array_values(array_filter(array_map(fn ($n) => preg_replace('/\D/', '', $n), explode(',', (string) env('ADIT_TEST_NUMBERS', ''))))),
];
