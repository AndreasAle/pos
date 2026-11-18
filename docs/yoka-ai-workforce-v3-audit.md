# Yoka AI Workforce 3.0 — Audit kode

Audit atas `yokapos_app` (branch `feat/telegram-office-human`, setelah 2.1). Hanya data platform Yoka; `pos_app` tidak disentuh.

## Yang sudah ada (tidak dibangun ulang)

| Kemampuan | Lokasi |
|---|---|
| Loop LLM + tool, anggaran harian, telemetri biaya | `AgentBrain::converse`, `PlatformAiTelemetry` |
| Klasifikasi niat deterministik, `create_task` ditutup untuk kerja langsung | `AgentIntent`, `AgentBrain::policy` |
| Validasi klaim terhadap bukti (tugas, aksi eksternal, konsultasi, janji) | `ResponseGuard::validate` |
| Gerbang kontribusi (setuju-saja, gema, duplikat) | `ResponseGuard::lowValue` |
| Memori kerja per agen dari catatan otoritatif | `WorkingMemory` |
| Sinyal nada sementara + panduan koreksi | `PlatformAiContext::conversationSignal` |
| Owner context, decision ledger, conversation state per kanal | `PlatformAiContext` |
| Reply-to resolution, rujukan "yang tadi" | `PlatformTelegramConversation::repliedMessage/resolveDeliverable` |
| Konsultasi nyata antar agen, rapat, absen, obrolan spontan terbatas | `PlatformTelegramOffice` |
| Dedup webhook (basic group: pengirim+waktu+isi), ledger notifikasi, klaim satu balasan per agen | `PlatformTelegramWebhookController`, `PlatformAiTelegramNotification` |
| Hasil tugas kembali ke grup asal, tepat sekali | `PlatformTelegramNotifications::taskOutcome` |
| Pemisahan Telegram merchant vs Platform AI | tabel dan controller terpisah (tidak diubah) |

## Akar masalah perilaku yang diamati

| Gejala | Akar di kode | Perbaikan |
|---|---|---|
| Agen terlalu gampang setuju, Ramli cuma mengiyakan | Prompt hanya mengatur *gaya* (`voice`, `EXPERTISE`), tidak ada *cara menilai* per agen | `AgentBrain::JUDGMENT` (7 gaya penilaian berbeda) + aturan "setuju hanya kalau setuju" |
| Janji masa depan, klaim kerja tanpa bukti | Sudah ditangani 2.1; celah tersisa: "udah aku cek laporannya" tanpa tool, kehidupan manusia karangan | `ResponseGuard::CHECKED`, `HUMAN_LIFE` |
| Tugas dibuat tanpa perlu, deliverable tidak inline | Ditangani 2.1 di lapisan tool | Tetap; ditambah keputusan `GENERATE_DELIVERABLE` |
| Trial gagal aktif disebut churn | Tidak ada definisi istilah di konteks; model menebak | `BusinessReasoning::FUNNEL` (fakta istilah) + validasi `terminologyViolation` |
| Saran iklan tanpa cek kesiapan bisnis | Tidak ada cek domain | `BusinessReasoning::ADS` disuntikkan hanya bila topiknya iklan |
| Lupa obrolan barusan / "bukan itu maksudku" | Rujukan hanya mengenali "kirim di sini / yang tadi"; koreksi tidak memengaruhi bentuk jawaban | `ConversationDecision`: `RETRIEVE_PREVIOUS_WORK` (tadi/kemarin/lanjutkan), `CORRECT_COURSE` |
| Nada frustrasi tidak terbaca | `mood()` hanya kenal "kesal/kecewa" | Tambah "masih double", "kenapa masih", huruf kapital semua, mode `celebratory` |
| Jawaban dobel | Basic group memberi message_id beda per bot | Diperbaiki di `7b20c62` |
| Prompt menyuruh typo dan menyangkal identitas AI | `AgentBrain` channelGuidance | Diganti: tanpa typo buatan, jujur bila ditanya apakah AI |
