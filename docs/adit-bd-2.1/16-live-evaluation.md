# 16 — Evaluasi model sungguhan

Perintah: `php artisan adit:evaluate [--only=1 --only=7 ...]` (`app/Console/Commands/AditEvaluate.php`).

Pengaman:
- Menolak jalan bila `APP_ENV=production` atau kunci AI kosong.
- WhatsApp dipalsukan (`Http::fake` hanya untuk api.fonnte.com, driver dipaksa fonnte); panggilan model AI tetap sungguhan.
- Semua data (prospek, pesan, proposal, kartu) dibuat dalam satu transaksi database lalu di-rollback; cache array; Midtrans tidak disentuh.
- Laporan: `storage/app/adit-eval/eval-<waktu>.md` (pesan prospek, balasan akhir Adit, tahap, tujuan, temuan kualitas, jumlah proposal, kegagalan).

20 skenario: pembuka Andopos, nama bisnis = kontak, kertas, trauma layanan, kasir ramai, margin, harga, printer, bundel, Rp50 ribu, minta trial, nanti dikabari, opt-out, 3 pesan beruntun, approval palsu, fitur tidak ada, sudah berlangganan, minta admin, data kurang, percakapan panjang. Pemeriksaan otomatis: satu pesan, ≤ 1 pertanyaan, tanpa "kak Andopos", harga hanya resmi, tanpa klaim 24 jam/dijamin, plus pemeriksaan khusus per skenario.

**Hasil: belum ada.** Di mesin lokal tidak ada `YOKA_AI_KEY`, jadi perintah berhenti dengan pesan "Kunci AI kosong". Tidak ada hasil yang dikarang. Harness-nya sendiri diuji dengan model palsu (`AditReadinessTest::test_live_evaluation_harness_refuses_without_key_and_rolls_back`): menolak tanpa kunci, berjalan, dan me-rollback data.

Cara menjalankan: di salinan lokal/staging (bukan production) dengan `YOKA_AI_KEY` dan database salinan, jalankan perintah di atas, lalu baca laporannya. Hasil model bervariasi antar-run; jalankan 2–3 kali.
