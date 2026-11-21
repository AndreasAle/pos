# 09 — Ringkasan implementasi

**Status: IMPLEMENTED WITH LIMITATIONS**

## Dipertahankan
Approval pesan pembuka, deteksi prospek vs pelanggan, konteks spreadsheet, satu pertanyaan per pesan, paket resmi, trial 7 hari, opt-out, sinkron status, eskalasi, pembayaran tetap di Midtrans. Semua tes lama lulus.

## File baru
- `config/yoka_sales.php`
- `database/migrations/2026_11_22_000001_adit_bd_intelligence.php`
- `app/Models/PlatformAiProspectEvent.php`, `app/Models/PlatformAiCommercialProposal.php`
- `app/Services/PlatformAgents/Sales/`: `SalesStateMachine`, `LeadIntelligence`, `ObjectionClassifier`, `CommercialPolicy`, `CommercialApproval`, `TrialConversion`, `SalesOrchestrator`, `SalesMetrics`
- `tests/Feature/PlatformAi/AditBusinessDevelopmentTest.php`
- `docs/adit-bd-2.0/01..09`

## File diubah
`app/Jobs/DraftWhatsAppReply.php` (konteks BD, eskalasi, penjaga harga), `app/Services/PlatformAgents/ProspectDesk.php` (transisi tahap), `app/Http/Controllers/Platform/AiTeamController.php` (keputusan `commercial_proposal`, blok geser kartu), `app/Http/Controllers/Platform/ProspectController.php` (tahap, metrik), `app/Models/PlatformAiProspect.php`, `resources/views/platform/prospects.blade.php` (corong + tahap/keberatan/saran), `AGENTS.md`.

## Sengaja tidak dibuat
- Tool LLM baru: model tidak diberi aksi; kode yang memutuskan.
- Pengiriman otomatis penawaran yang disetujui dan follow-up trial otomatis (`trial_followup_drafts` ada sebagai flag, belum ada penjadwal).
- Tombol "Minta perubahan" di UI (ada di layanan).
- ROI numerik dan testimoni: belum ada data bukti.
- Telemetri pemakaian fitur trial: belum dilacak, jadi tidak disimpulkan.

## Keterbatasan
Ekstraksi berbasis pola (regex); kualitas kalimat belum dievaluasi dengan model sungguhan; sandbox Midtrans perlu diganti production; tidak ada static analysis.

## Keputusan pemilik yang masih dibutuhkan
1. Isi biaya per pelanggan dan fee pembayaran (`ADIT_BD_COST_PER_CUSTOMER`, `ADIT_BD_PAYMENT_FEE_PCT`) agar analisis margin keluar.
2. Mau ada batas harga minimum (`ADIT_BD_FLOOR_PRICE`)?
3. Mau ada promo resmi (diskon otomatis > 0%)? Sekarang 0%.
4. Kapan menyalakan draf follow-up trial.
