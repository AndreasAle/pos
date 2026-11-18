# Yoka AI Workforce 3.0 — Arsitektur

Tidak ada mesin AI kedua. 3.0 menambah dua komponen deterministik di depan loop yang sudah ada.

```
Telegram webhook -> dedup (pengirim+waktu+isi) -> PlatformTelegramConversation::process
  -> Router (siapa yang dipanggil)
  -> AgentIntent::classify            izin: create_task terbuka/tertutup (TASK_ALLOWED)
  -> ConversationDecision::decide     bentuk jawaban + nada + domain bisnis (BUKAN izin)
  -> BusinessReasoning::facts         cek domain hanya bila relevan
  -> WorkingMemory::asFact            tugas, hasil, kontribusi, koreksi (sumber otoritatif)
  -> AgentBrain (persona: voice + EXPERTISE + JUDGMENT + PLAYBOOK)  loop LLM + tool
  -> ResponseGuard::validate          klaim vs bukti, istilah, kehidupan karangan, filler
  -> PlatformTelegramStyle            bubble, batas panjang
  -> Gateway::sendHuman               satu klaim per (interaction, agen)
```

## Komponen baru

- `app/Services/PlatformAgents/ConversationDecision.php`: objek bertipe (`action`, `intent`, `mood`, `correction`, `needsData`, `domains`). Aksi: ANSWER_DIRECTLY, ANALYZE_WITH_TOOLS, GENERATE_DELIVERABLE, RETRIEVE_PREVIOUS_WORK, CREATE_TASK_PROPOSAL, REQUEST_APPROVAL, CONSULT_ANOTHER_AGENT, SUMMARIZE_DISCUSSION, CORRECT_COURSE, NO_RESPONSE. `asFact()` menjadi arahan sistem untuk model.
- `app/Services/PlatformAgents/BusinessReasoning.php`: domain `ads_readiness`, `funnel_terms`, `finance_terms`; `terminologyViolation()` dipakai ResponseGuard.

## Perubahan komponen lama

- `AgentBrain::JUDGMENT` + blok "CARA MENILAI" di prompt sistem.
- `PlatformAiContext::mood` menjadi publik statis, ditambah pola frustrasi dan mode `celebratory`.
- `ResponseGuard`: `claimed_check_without_tool`, `fabricated_human_life`, `terminology_trial_as_churn`.
- `PlatformAiTelemetry::QUALITY_METRICS`: `terminology_corrected`, `correction_handled`, `business_check_applied`.

Tidak ada migrasi baru. Tidak ada panggilan LLM tambahan.
