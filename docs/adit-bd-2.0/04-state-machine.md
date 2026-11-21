# 04 — State machine penjualan

Kolom `platform_ai_prospects.sales_stage` (+ `stage_version`), terpisah dari:
- `status` (status kontak di spreadsheet, lama),
- `intelligence.qualification` (kualifikasi),
- `platform_ai_commercial_proposals.status` (penawaran/approval),
- `business_subscriptions` (trial/langganan, sumber kebenaran),
- `opted_out_at` (izin kontak).

Tahap: new_lead, outreach_pending_approval, outreach_sent, engaged, discovery, qualified, objection_handling, negotiation_pending_approval, approved_offer_ready, trial_invited, trial_active, trial_conversion, won, lost, opted_out, human_handoff.

Aturan (`SalesStateMachine::TRANSITIONS`):
- Setiap transisi dalam transaksi + `lockForUpdate`; opsional `expectedVersion` menolak update basi.
- Transisi tidak sah tidak dijalankan dan dicatat sebagai `policy_denial`.
- `approved_offer_ready` hanya bisa dicapai dari `negotiation_pending_approval` (dipanggil `CommercialApproval::decide`).
- Dari tahap mana pun → `opted_out`. Dari `opted_out` tidak ada transisi; hanya `optIn()` oleh admin dengan alasan tertulis.
- `trial_active` / `won` / `trial_conversion` disetel dari data billing (`SalesOrchestrator::syncTrial`), bukan dari obrolan.

Pemicu:
| Peristiwa | Transisi | Aktor |
|---|---|---|
| Draf pembuka/follow-up disusun | → outreach_pending_approval | adit |
| Setujui & kirim berhasil | → outreach_sent (kunci `sent:{task}`) | admin |
| Draf ditolak | → new_lead / outreach_sent | admin |
| Prospek membalas | → engaged → discovery / qualified / objection_handling | prospek / adit |
| Permintaan harga di luar paket | → negotiation_pending_approval | adit |
| Proposal disetujui / ditolak | → approved_offer_ready / objection_handling | admin |
| "stop" / ditandai berhenti | → opted_out | prospek / admin |
| Ditandai tidak minat | → lost | admin |

Semua transisi tercatat di `platform_ai_prospect_events` (from, to, aktor, alasan, data).
