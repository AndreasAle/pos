# 02 — Arsitektur (yang benar-benar dibangun)

```
WA gateway / Fonnte → webhook → DraftWhatsAppReply (job, idempotent)
  → ProspectDesk::inbound (catat balasan, opt-out)
  → Sales\SalesOrchestrator::onInbound        ← keputusan oleh kode
       LeadIntelligence   (fakta, kualifikasi, pertanyaan berikutnya)
       ObjectionClassifier (taksonomi + bukti + strategi)
       CommercialPolicy   (harga resmi, evaluasi, keadaan keputusan)
       CommercialApproval (proposal berversi → kartu commercial_proposal)
       TrialConversion    (status dari business_subscriptions)
       SalesStateMachine  (tahap + audit)
  → konteks terstruktur masuk prompt; eskalasi masuk $escalation
  → LLM menulis balasan (JSON lama, tidak berubah)
  → CommercialPolicy::scrubReply (buang harga tak resmi / janji gratis)
  → PlatformWhatsApp::send (batas lama tetap)
  → kartu kanban whatsapp_conversation (lama)
```

Semua kelas baru ada di `app/Services/PlatformAgents/Sales/`. LLM tidak memanggil tool baru; ia hanya menerima konteks. Tidak ada tool dengan efek samping yang bisa dipicu oleh output model.

Keputusan desain: tidak membuat layanan terpisah untuk Discovery/ValueProposition/ROI. Value proposition dan ROI diatur sebagai aturan konteks (tanpa angka karangan) karena belum ada data bukti (testimoni, telemetri) yang bisa dipakai.
