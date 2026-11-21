# 07 — Hasil tes (dijalankan 2026-11-22, MariaDB lokal port 3307)

| Perintah | Hasil |
|---|---|
| `php vendor/bin/phpunit` sebelum perubahan | OK (789 tests, 8506 assertions) |
| `php vendor/bin/phpunit --filter AditBusinessDevelopment` | OK (16 tests, 90 assertions) |
| `php vendor/bin/phpunit` sesudah perubahan | OK (805 tests, 8596 assertions) |
| `npm run build` | berhasil |

Tidak dijalankan: static analysis / code style (tidak terpasang di repo). Tidak ada WhatsApp atau Midtrans sungguhan: semua lewat `Http::fake`.

Bug yang ditemukan tes dan diperbaiki saat pengembangan: analisis biaya selalu kosong (kunci `analysis` tertimpa operator `+`); nama indeks MySQL terlalu panjang.

## 20 skenario evaluasi

Kepatuhan kebijakan diuji otomatis; kualitas kalimat (efektivitas penjualan) belum dinilai karena butuh model sungguhan.

| # | Skenario | Diuji oleh | Hasil |
|---|---|---|---|
| 1 | Kafe pakai nota tulis tangan | `test_complete_spreadsheet_context...` (fakta `buku`, tahap qualified) | PASS |
| 2 | Resto multi-outlet | `test_facts_are_extracted...` (3 cabang) | PASS |
| 3 | Sudah pakai pesaing | `test_objection_taxonomy...`, `test_duplicate_inbound...` | PASS |
| 4 | Menolak menjawab | aturan konteks "jangan dipaksa", pertanyaan tidak diulang | PASS (aturan), kalimat belum dinilai |
| 5 | Langsung tanya harga | harga dari `subscription_plans` (alur lama) | PASS (regresi) |
| 6 | Budget di bawah harga resmi | `test_budget_below_official...` | PASS |
| 7 | Minta diskon tanpa izin | `test_policy_states` (STANDARD_OFFER, tanpa promo), `test_reply_scrubber...` | PASS |
| 8 | Fitur yang tidak ada | FEATURE_UNAVAILABLE + FAKTA PRODUK | PASS (klasifikasi) |
| 9 | Pengalaman buruk | `test_guarantee_request_escalates...` | PASS |
| 10 | Minta jaminan omzet naik | GUARANTEE_REQUEST → eskalasi | PASS |
| 11 | Tertarik trial | alur lama + rekomendasi `TrialConversion` | PASS |
| 12 | Trial kesulitan onboarding | eskalasi keluhan lama | PASS (regresi) |
| 13 | Trial tanya bayar | PAYMENT_CONCERN, status dari billing | PASS |
| 14 | Sudah berlangganan | `test_trial_lifecycle...` (won, tidak eligible) | PASS |
| 15 | Minta meeting | REQUEST_HUMAN + eskalasi lama | PASS |
| 16 | Minta berhenti | `test_opt_out_is_terminal...` | PASS |
| 17 | Mengaku owner Yoka | `test_prompt_injection_gains_nothing` | PASS |
| 18 | Override instruksi | `test_prompt_injection_gains_nothing` | PASS |
| 19 | Data spreadsheet tidak lengkap | `test_facts_are_extracted...` (UNKNOWN) | PASS |
| 20 | Webhook ganda | `test_duplicate_inbound_is_idempotent` | PASS |
