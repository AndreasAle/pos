# Yoka AI Workforce — Kebijakan Aksi (2.1)

Dokumen ini menjelaskan kapan agen **langsung mengerjakan**, kapan **membuat usulan tugas**, dan kapan **wajib approval**. Kebijakan ditegakkan di kode (lapisan tool dan validasi keluaran), bukan hanya di prompt.

## Ringkasan

| Jenis permintaan | Contoh | Perilaku | Ditegakkan di |
|---|---|---|---|
| Pertanyaan langsung, analisis, hitungan, ide, draf | "Luna, hitung simulasi harga paket", "Yusuf, buat tiga hook video" | Dikerjakan langsung di balasan. `create_task` **tidak ditawarkan** ke model. | `AgentIntent::classify` → `AgentBrain::policy(['allow_tasks' => false])` |
| Pembuatan tugas eksplisit | "Catat ini sebagai tugas", "Jadikan tugas untuk Yusuf", "Buatkan task di dashboard" | Usulan tugas lewat task engine (status `proposed`, `requires_approval`). | `AgentIntent::TASK_CREATION` |
| Aksi eksternal / sensitif | Kirim WA ke pelanggan, pasang/ubah iklan, ubah harga, refund, transfer | Hanya boleh berupa usulan tugas yang menunggu approval. Agen tidak punya tool untuk mengeksekusinya. | `AgentIntent::EXTERNAL_ACTION` + tidak ada tool eksekusi |
| Rujukan ke hasil sebelumnya | "Coba kirim di sini dulu", reply ke pesan agen | Hasil yang sudah ada dikirim apa adanya. Kalau belum ada, agen mengakuinya lalu mengerjakan langsung. | `PlatformTelegramConversation::resolveDeliverable` |
| Kolaborasi | "Yusuf, tanya Luna apakah ini masuk budget" | Luna benar-benar dijalankan, jawabannya dikirim dengan identitas Luna dan disimpan sebagai kontribusi diskusi. Yusuf lalu menjawab berdasarkan jawaban itu. Maksimal satu lompatan, tanpa tugas. | `PlatformTelegramOffice::consult` |
| Pekerjaan panjang | Tugas yang sudah di-approve | Dijalankan queue (`RunPlatformAiTask`). Hasil atau kegagalan dikirim balik ke grup asal. | `PlatformAiOperations::execute` → `PlatformTelegramNotifications::taskOutcome` |

Kata seperti "buat", "analisis", atau "coba" **tidak** otomatis memicu pembuatan tugas.

## Tiga tingkat otonomi (tidak berubah)

1. **Bebas**: baca data lewat tool, analisis, hitungan, draf internal. Hasilnya boleh langsung tampil di grup tanpa approval.
2. **Perlu approval manusia**: kirim pesan keluar, iklan, uang. Hanya berupa usulan tugas. Pesan keluar tetap hanya terkirim lewat tombol "Setujui & kirim".
3. **Dilarang permanen**: refund, ubah harga, scraping, pesan massal.

## Bukti tindakan (no false promises)

`App\Services\PlatformAgents\ResponseGuard::validate` memeriksa setiap kalimat sebelum dipublikasikan:

| Klaim | Sah bila | Jika tidak |
|---|---|---|
| "Sudah aku buat tugasnya" | Ada tugas yang tersimpan di giliran ini (`created_tasks`) | Kalimat dibuang |
| "Nanti aku cek", "besok aku pantau", "aku kabari lagi" | Ada tugas tersimpan **dan** kalimatnya mengaitkan ke approval | Kalimat dibuang. Agen tidak punya penjadwal untuk janji seperti ini. |
| "Sudah aku kirim WA / jalankan campaign / transfer" | Tidak pernah sah (agen tidak punya kemampuannya) | Kalimat dibuang |
| "Tadi aku udah diskusi sama Luna" | Luna benar-benar punya pesan atau kontribusi tersimpan di grup ini dalam 2 jam terakhir | Kalimat dibuang |
| Kalimat pengisi ala CS ("ada yang mau dicek?", "siap bantu", "masih standby") | — | Dibuang, pendek maupun panjang |

Setiap pembuangan dicatat sebagai metrik (`quality.unsupported_claim_removed`, `quality.filler_removed`) **tanpa** menyimpan isi kalimatnya.

## Batas yang ditulis di kode

- `create_task` maksimal 3 per giliran, delegasi maksimal kedalaman 2 (sudah ada).
- Konsultasi antar agen: 1 lompatan, 2 peserta, 1 ronde, tanpa tugas.
- Nimbrung: maksimal 3 agen per pesan Bos, lewat gerbang kontribusi.
- Obrolan spontan: maksimal 10 per grup per hari, tidak di jam tenang, `create_task` diblokir.
- Pesan dari bot tidak pernah diproses (anti-loop). Pesan yang ditahan gerbang kontribusi tidak pernah terkirim, jadi tidak bisa memicu balasan.
- Anggaran AI harian (`YOKA_AI_DAILY_REQUEST_BUDGET`, `YOKA_AI_DAILY_COST_BUDGET_USD`) tetap berlaku untuk semua panggilan, termasuk langkah tulis ulang.
