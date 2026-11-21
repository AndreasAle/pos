# 03 — Aturan bisnis

## Discovery (`LeadIntelligence`)
- Fakta dari spreadsheet (sumber `lead`) dan pesan prospek (sumber `prospek`, dengan `message_id`). Yang tidak terdeteksi = UNKNOWN, tidak ditebak.
- Fakta: jenis usaha, jumlah outlet, cara mencatat/POS sekarang, jumlah kasir, peran pengambil keputusan, masalah utama, waktu.
- Tidak menanyakan omzet atau data keuangan pribadi.
- Pertanyaan berikutnya: urutan pengaruh ke rekomendasi (jenis → outlet → cara catat → masalah → kasir → peran); tidak mengulang kunci yang disarankan di 2 giliran terakhir; tidak disarankan saat ada keberatan.

## Kualifikasi
Dimensi: business_fit, operational_need, decision_authority, timing, budget, engagement → positive / negative / UNKNOWN + bukti.
Kategori: `qualified` (fit + need positif, otoritas tidak negatif), `low_fit` (skor ≤ −2), `opted_out`, selain itu `needs_discovery`.

## Keberatan (`ObjectionClassifier`)
REQUEST_STOP, REQUEST_HUMAN, PRICE_TOO_HIGH, ALREADY_USING_COMPETITOR, NO_CURRENT_NEED, PREVIOUS_BAD_EXPERIENCE, LOW_TRUST, FEATURE_UNAVAILABLE, TECHNICAL_CONCERN, DATA_MIGRATION_CONCERN, PAYMENT_CONCERN, TRIAL_UNCERTAINTY, NEED_INTERNAL_APPROVAL, NOT_DECISION_MAKER, GUARANTEE_REQUEST, CONFIDENTIAL_PROBE. Boleh lebih dari satu. Bukti = potongan teks asli.
GUARANTEE_REQUEST memicu eskalasi.

## Harga (`CommercialPolicy`)
- Harga resmi = `subscription_plans` aktif dengan harga > 0. Tidak ada harga di prompt selain dari tabel ini.
- Diskon otomatis yang disetujui: `yoka_sales.approved_discount_pct` = 0.
- Biaya tidak diketahui ≠ 0 → INSUFFICIENT_DATA dan tetap butuh manusia.
- Pendapatan bukan laba; kontribusi = harga × (1 − fee) − biaya, hanya bila kedua data ada.

## Value & ROI
Tanpa testimoni, studi kasus, atau angka penghematan karangan. Hitungan hanya dari angka prospek sendiri, asumsi disebut, tanpa jaminan.

## Penjaga balasan
Untuk prospek, kalimat yang menyebut harga selain harga resmi, "gratis selamanya", atau klaim "sudah aku aktifkan/setujui" dibuang sebelum dikirim. Penjaga lama (diskon/promo) tetap berlaku.
