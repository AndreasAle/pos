# Yoka Telegram Office — Kualitas Percakapan (2.1)

Melengkapi `yoka-telegram-office-architecture.md`. Fokus dokumen ini: kenapa agen dulu menghindari kerja nyata, apa yang diubah, dan cara memverifikasinya.

## 1. Analisis akar masalah (fase 0)

Ditelusuri dari pesan nyata: **Bos: "Luna, coba kirim di sini dulu."** Luna membalas dengan deskripsi tugas dan link approval, bukan analisis keuangan.

| Masalah yang terlihat | Akar masalah di kode (sebelum 2.1) |
|---|---|
| Agen membuat tugas, bukan langsung mengerjakan | `AgentBrain::systemPrompt` menyuruh "tawarkan menyimpannya sebagai tugas" dan "panggil create_task bila dia setuju". `create_task` selalu ditawarkan di setiap giliran Telegram. Tidak ada klasifikasi niat. |
| "Kirim di sini" dijawab dengan judul/deskripsi tugas | Webhook membuang `reply_to_message`. `respond()` tidak punya konsep "hasil kerja". Satu-satunya jejak di riwayat adalah pesan Luna yang berisi rangkuman tugas, jadi model mengulanginya. Tugasnya sendiri masih `proposed` (belum ada hasil). |
| Link approval berulang | `respond()` menempelkan "udah aku taruh di Operations Center … <link>" di **setiap** balasan yang membuat tugas, tanpa catatan apa yang sudah diumumkan. Ramli membuat dua tugas di dua giliran, jadi muncul dua link identik. |
| "Nanti aku cek" tanpa jadwal | Tidak ada validasi keluaran. Agen tidak punya mekanisme menjadwalkan pekerjaan sendiri, tapi prompt grup malah memberi contoh "bentar aku cek dulu". |
| Nimbrung bernilai rendah ("siap, nanti aku cek") | `maybeChimeIn` mengirim apa pun selain `SKIP`, tanpa gerbang kontribusi. |
| "Belum ada update" padahal baru ikut diskusi | Agen tidak menerima memori kerja. Riwayat grup dibatasi 16 pesan dan kontribusi diskusi tidak dibaca. |
| Ramli menjawab sebagai "Yusuf: …" | "suf" tidak dikenali router (sudah diperbaiki di 2.0.x). Label dibersihkan sebelum Markdown dibuang. |

Kesimpulan: sebagian besar masalahnya **struktural** (routing, kebijakan tool, tidak ada bukti, tidak ada ledger), bukan semata-mata kualitas model.

## 2. Alur baru satu pesan Bos

```
webhook ──► PlatformAiTelegramUpdate (reply_to_message.message_id disimpan, isinya tidak)
   └─► PlatformTelegramConversation::process
         ├─ repliedMessage()            : reply ke pesan agen → rute ke agen itu
         ├─ PlatformTelegramRouter      : nama/panggilan pendek/@mention/reply/topik
         └─ respond()
              ├─ AgentIntent::classify  : niat + boleh tugas? + rujukan? + konsultasi?
              ├─ resolveDeliverable()   : hasil tugas selesai → kirim apa adanya (tanpa model)
              ├─ PlatformTelegramOffice::consult() : rekan benar-benar dijalankan (opsional)
              ├─ WorkingMemory::asFact  : tugas, hasil, diskusi, ucapan di grup ini, koreksi Bos
              ├─ PlatformAiOperations::reply(policy: allow_tasks, origin)
              │     └─ AgentBrain       : create_task hanya bila policy mengizinkan; catat toolsUsed
              ├─ ResponseGuard::validate: buang klaim tanpa bukti + kalimat pengisi
              ├─ humanize (chat santai) / clean (hasil kerja dibiarkan utuh)
              ├─ PlatformTelegramNotifications::proposalNotice : 1x per tugas/versi, link dengan cooldown
              └─ gateway->sendHuman     : bubble + typing
```

## 3. Gerbang kontribusi (nimbrung & obrolan spontan)

`ResponseGuard::lowValue`, deterministik dan tanpa biaya model:

- **Ditahan**: hanya setuju/siap/oke/noted tanpa angka atau pertanyaan (≤14 kata), sekadar "benar kata Luna", atau ≥85% mirip salah satu dari 6 pesan terakhir.
- **Tidak pernah ditahan**: kalimat yang memuat risiko/peringatan (risiko, rugi, jangan, hati-hati, telat, …).
- Jawaban langsung untuk Bos **tidak** lewat gerbang ini, hanya lewat validasi klaim.
- Pesan yang ditahan tidak disimpan dan tidak dikirim, jadi tidak bisa memicu bot lain.

## 4. Dedup notifikasi

Tabel `platform_ai_telegram_notifications` dengan `dedupe_key` unik = `type:group:thread:subject`:

| type | subject | Efek |
|---|---|---|
| `task_proposed` | `taskId:vVersi` | Tugas yang sama diumumkan sekali per grup/topik. Versi approval baru = boleh lagi. |
| `dashboard_link` | ember waktu 30 menit | Link dashboard tidak diulang ke grup yang sama dalam 30 menit. |
| `pending_alert` | `jumlah:tanggal` | Pengingat keputusan tertunda, sekali per keadaan per hari (tetap tunduk `max_alerts_per_day`). |
| `task_completed` / `task_failed` | `taskId:vVersi:ok` / `…:fail:n` | Hasil tugas dikirim sekali. Worker paralel dan retry tidak menggandakan. |

Klaim memakai `INSERT` + unique constraint (atomik), bukan pola cek-lalu-tulis.

## 5. Hasil tugas kembali ke Telegram

Tugas yang dibuat dari grup menyimpan `payload.telegram_origin = {group_id, admin_id, chat_id, thread_id, message_id, interaction_id}`. Setelah `PlatformAiOperations::execute`:

- **Selesai** (`status=completed`, `executed_at` terisi): bot agen penanggung jawab mengirim "bos, \"judul\" udah selesai." + ringkasan hasil (maksimal 1.500 karakter) + link dashboard.
- **Gagal**: pesan kegagalan yang jujur ("belum ada hasil apa pun dari tugas ini") + link. Pesan sukses tidak pernah dikirim untuk tugas yang tidak `completed`.
- Hanya dikirim ke grup asal yang masih aktif dan masih milik admin yang sama.

## 6. Observabilitas

Metrik dicatat sebagai `platform_ai_runs.operation = quality.<metrik>` (biaya 0, metadata tanpa isi percakapan):

`direct_answer`, `direct_answer_failed`, `task_created_explicit`, `task_blocked_by_policy`, `approval_notice_deduplicated`, `unsupported_claim_removed`, `filler_removed`, `low_value_suppressed`, `reference_resolved`, `reference_unresolved`, `deliverable_missing`, `task_result_delivered`, `task_failure_reported`, `consultation`, `useful_interaction`.

```bash
php artisan yoka:ai-quality --days=7
php artisan yoka:ai-quality --json
```

Biaya per interaksi berguna = total biaya model berkanal Telegram ÷ `useful_interaction`.

## 7. Pengujian

`tests/Feature/Platform/PlatformAiActionPolicyTest.php` (fixture regresi dari kegagalan nyata yang sudah disamarkan) dan `PlatformTelegramOfficeTest.php`. Semua panggilan model dan Telegram **di-mock**. Tes membuktikan routing, kebijakan tool, validasi bukti, dedup, dan pengiriman hasil. Tes **tidak** membuktikan kualitas bahasa model asli. Itu harus dicek manual (bagian 8).

Menjalankan secara lokal butuh MySQL/MariaDB dengan `explicit_defaults_for_timestamp=1` (sama seperti produksi).

## 8. Verifikasi manual di Telegram

Setelah deploy (`./deploy.sh` menjalankan migrasi tabel ledger):

1. `Luna, hitung simulasi harga paket 99rb kalau 20 toko bayar`: angka dan asumsi langsung muncul. Tidak ada link Operations Center.
2. `Ramli, catat ini sebagai tugas: kalender konten 7 hari`: satu kalimat "masuk papan buat di-approve" + link. Ulangi untuk tugas lain dalam 30 menit: link tidak muncul lagi.
3. Approve tugas itu di dashboard: setelah dijalankan, Ramli mengirim hasilnya ke grup.
4. `Luna, coba kirim di sini dulu`: kalau ada hasil, isi aslinya dikirim. Kalau belum, Luna bilang belum ada lalu langsung menghitung.
5. Reply ke pesan Yusuf dengan `lengkapnya dong`: yang menjawab Yusuf.
6. `Yusuf, tanya Luna apakah campaign 2 juta masuk budget`: Luna menjawab dulu dengan bot Luna, lalu Yusuf merekomendasikan.
7. `Yusuf, tadi gimana hasil diskusi trial?`: Yusuf merujuk ucapannya sendiri, bukan "belum ada update".
8. `php artisan yoka:ai-quality`: angka metrik bertambah.

## 9. Risiko yang tersisa

- Klasifikasi niat berbasis pola. Kalimat yang tidak lazim bisa salah golongan. Kesalahan ke arah "kerja langsung" lebih aman daripada ke arah "buat tugas". Pantau `direct_answer_failed` dan `reference_unresolved`.
- `ResponseGuard` bekerja per kalimat. Klaim yang dirumuskan sangat tidak biasa masih bisa lolos. Validasi berbasis model belum dipakai (sengaja, demi biaya).
- Langkah tulis ulang (`humanize`) adalah satu panggilan model tambahan untuk jawaban santai yang panjang.
- Gerbang kontribusi hanya untuk nimbrung/obrolan spontan. Jawaban langsung yang terlalu panjang tetap mengandalkan prompt + tulis ulang.
