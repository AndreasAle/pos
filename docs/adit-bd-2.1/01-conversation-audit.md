# 01 — Audit percakapan (simulasi Andopos, 2026-11-22)

## Alur satu pesan masuk → pesan keluar
1. Gateway/Fonnte → `FonnteWebhookController` menyimpan `PlatformAiWhatsAppMessage` → `DraftWhatsAppReply::dispatchAfterResponse($id)`.
2. `DraftWhatsAppReply::handle`: `Cache::add` (sekali proses), lewati bila ada pesan masuk lebih baru dari nomor yang sama (pesan beruntun dijawab sekali), deteksi pelanggan vs prospek, `ProspectDesk::inbound`, `SalesOrchestrator::onInbound` (BD 2.0), eskalasi, `think()`.
3. `think()` membangun prompt (persona, FAKTA PRODUK `AgentBrain::productFacts`, PAKET AKTIF dari `subscription_plans`, 16 pesan terakhir) → model → JSON.
4. `bubbles()` memecah balasan per paragraf menjadi **maksimal 3 pesan**.
5. Loop `PlatformWhatsApp::send` per bubble dengan jeda 2–7 detik.

## Akar masalah yang terlihat di percakapan nyata
| Masalah | Penyebab di kode |
|---|---|
| "Halo kak Andopos" | `ProspectDesk::openingMessage` memakai `contact_name` mentah; kolom kontak diisi nama bisnis |
| Link trial di pesan pertama | Teks pembuka ditulis permanen di `openingMessage` |
| 2–3 pesan per giliran | Prompt "Pecah jadi 1-3 bubble" + `bubbles()` + loop kirim per bubble. Bukan dari queue, retry, atau gateway |
| Selalu ada pertanyaan/jualan | Prompt umum menyuruh "SATU pertanyaan kualifikasi" tiap peluang closing; tidak ada keputusan tujuan balasan |
| Empati generik + klaim "servis tim Indonesia langsung" | Tidak ada fakta layanan support di `productFacts`; tidak ada penyaring klaim support |
| "oke kak, siap." saat prospek pamit | Tidak ada kebijakan penutupan; penyaring `CLOSER` membuang basa-basi |
| Printer hanya dijawab teknis | Tidak ada pencatatan minat perangkat |

Yang sudah benar: harga sesuai tabel, link panduan printer **ada** di `GuideController::guides()` (`menyambungkan-printer-struk`), RawBT/print browser sesuai `productFacts`, laba rugi/estimasi profit memang ada di fitur laporan.

## Baseline
`php vendor/bin/phpunit` sebelum 2.1: OK (807 tests) setelah BD 2.0 + nomor uji.
