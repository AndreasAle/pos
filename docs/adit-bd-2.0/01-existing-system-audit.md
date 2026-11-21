# 01 — Audit sistem yang sudah ada (2026-11-22)

Stack: Laravel 12, PHP 8.2 (`composer.json`), MySQL/MariaDB, queue database, hosting Hostinger shared.

## Peta arsitektur (terverifikasi)

| Komponen | Lokasi |
|---|---|
| Webhook WA masuk (Fonnte + gateway sendiri) | `routes/web.php` `platform-ai/whatsapp/webhook/{secret}` → `PlatformWhatsAppWebhookController` → job `app/Jobs/DraftWhatsAppReply.php` |
| Gateway WA (Baileys) | `wa-gateway/src/server.js`, guard batas di `wa-gateway/src/guard.js`, watchdog `wa-gateway/watchdog.sh` |
| Kirim WA | `app/Services/PlatformWhatsApp.php::send` |
| Orkestrasi balasan Adit | `DraftWhatsAppReply::handle/think` (prompt, JSON output, bubble) |
| Persona, fakta produk | `app/Services/PlatformAgents/AgentBrain.php`, `CustomerService/{ProductKnowledge,MenuKnowledge,ServicePlaybook,CustomerSignals}.php` |
| Penjaga kalimat | `DraftWhatsAppReply::FORBIDDEN_SENTENCE`, `ResponseGuard.php` |
| Prospek (spreadsheet) | `ProspectDesk.php`, `Platform/ProspectController.php`, tabel `platform_ai_prospects` |
| Kanban / approval | `PlatformAiTask` (`platform_ai_tasks`), `AiTeamController::decide` (Setujui & kirim), `moveTask` (geser kartu) |
| Harga resmi | `SubscriptionPlan` (`subscription_plans`, kolom `price`, `is_active`) |
| Trial & langganan | `BusinessSubscription` (`status` trial/active/pending/cancelled, `ends_at`, `paid_at`, `amount`), `MidtransController`, `config/midtrans.php` (`is_production`) |
| Idempotensi pesan | `Cache::add('platform-wa-processed:{id}')` di job; batas per nomor `withinLimits` |
| Eskalasi | ditentukan kode di `DraftWhatsAppReply::handle` (`$escalation`), dicatat ke kartu `whatsapp_conversation` |
| Audit | `PlatformActivityLog::record` |
| Tes | PHPUnit (`tests/Feature/...`), MariaDB lokal |

## Klasifikasi kebutuhan spesifikasi

| Kebutuhan | Status sebelum 2.0 |
|---|---|
| Approval pesan pembuka | ALREADY IMPLEMENTED (`whatsapp_draft` + Setujui & kirim) |
| Bedakan prospek vs pelanggan | ALREADY IMPLEMENTED (`DraftWhatsAppReply::business`, `ProspectDesk::check`) |
| Konteks spreadsheet ke Adit | ALREADY IMPLEMENTED (`ProspectDesk::context`) |
| Satu pertanyaan per pesan | PARTIALLY (hanya instruksi prompt) |
| Fakta & kualifikasi terstruktur | NOT IMPLEMENTED |
| Taksonomi keberatan | PARTIALLY (`CustomerSignals`: PRICE_OBJECTION, COMPETITOR, DISCOUNT) |
| Otoritas harga di backend | PARTIALLY (kalimat diskon dibuang regex; tidak ada evaluasi harga) |
| Proposal harga + approval berversi | NOT IMPLEMENTED |
| State machine penjualan | NOT IMPLEMENTED (hanya `status` spreadsheet) |
| Status trial dari billing | PARTIALLY (`refreshConversions` hanya trial/bayar) |
| Follow-up trial draft-only | NOT IMPLEMENTED |
| Metrik corong | PARTIALLY (`ProspectDesk::stats`) |
| Multi-tenant prospek | NOT APPLICABLE: prospek adalah data platform Yoka sendiri (satu tenant), hanya super admin |
| Payment sandbox vs production | NEEDS VERIFICATION di server (`MIDTRANS_IS_PRODUCTION`) |

## Baseline tes

`DB_HOST=127.0.0.1 DB_PORT=3307 DB_USERNAME=root DB_PASSWORD= php vendor/bin/phpunit` → **OK (789 tests, 8506 assertions)** sebelum perubahan.
Keterbatasan: tidak ada static analysis (PHPStan/Pint) terpasang di repo; tes tidak menyentuh WhatsApp/Midtrans sungguhan (Http::fake).
