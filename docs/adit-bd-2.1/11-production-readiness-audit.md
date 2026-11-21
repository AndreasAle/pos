# 11 — Audit kesiapan produksi (2026-11-22)

Baseline sebelum perubahan, dijalankan ulang sendiri: `phpunit` → **OK (825 tests, 8688 assertions)**.

## Alur pembuka → kirim (terverifikasi)
`ProspectDesk::draftToday` (jadwal 09.00 / tombol) → `createDraft` → `OpeningStrategy::opening` → `PlatformAiTask` `whatsapp_draft` (proposed) → super admin "Setujui & kirim" (`AiTeamController::decide`) → `PlatformWhatsApp::send` → `ProspectDesk::markSent`.

## Temuan
| Temuan | Penyebab | Status |
|---|---|---|
| Dashboard tetap menyarankan tanya cara catat padahal prospek bilang kertas | Prospek mengetik "kerta"; pola `LeadIntelligence::extract` hanya "kertas" → fakta tidak tersimpan. Bukan masalah proyeksi dashboard (dashboard membaca `intelligence.qualification` terbaru) | Diperbaiki: ejaan santai/typo dinormalkan |
| `CONFIDENTIAL_PROBE` di simulasi | False positive: kata "margin"/"modal" berdiri sendiri ("laporan margin") | Diperbaiki: hanya bila menanyakan angka/data internal Yoka atau toko lain |
| Filter balasan bisa meninggalkan kalimat menggantung / kosong | `ResponseQuality` hanya membuang kalimat | Diperbaiki: pembersih akhir kalimat + fallback terverifikasi |
| Jendela gabung pakai `sleep()` di job | Implementasi 2.1 awal | Diganti: tunda lewat queue + lock per nomor |
| Klaim layanan tanpa sumber | Tidak ada kebijakan support | Ditambah `config/yoka_support.php` |
| Tidak ada katalog perangkat | — | Ditambah `config/yoka_hardware.php` (kandidat, belum disetujui) |
| Belum ada uji model sungguhan | Tidak ada kunci AI di lokal | Harness dibuat; eksekusi live **terblokir di lokal** |

Flag & rollback: `config/yoka_sales.php` (`bd21.*`), `config/yoka_support.php`, `config/yoka_hardware.php`; revert commit. Tidak ada migration baru.
