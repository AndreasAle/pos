# Yoka AI Workforce 2.0 — Verification Report

Date: 17 November 2026 (Asia/Jakarta)

## Test isolation

Tests use a temporary local MySQL instance on `127.0.0.1:3307` and the dedicated database `fnb_pos_test`. No production hostname, credentials or database is used. Laravel `RefreshDatabase` recreates only the test schema.

## Verified with real MySQL and mocked provider responses

- Owner Context authorization and confirmed precedence.
- Memory extraction, correction, versioning, supersession, expiry and scope isolation.
- Conversation state, temporary mood and correction signals.
- Decision ledger and safe ordinal reference resolution.
- Ambiguous and conditional approval rejection.
- Idempotent task execution and execution-attempt persistence.
- Event observation, change detection and fingerprint deduplication.
- Bounded delegation and persisted multi-agent discussion contributions.
- Provider token usage, tool calls, latency, estimated cost and failed-call telemetry.
- Daily cost budget cutoff and honest fallback on provider failure.
- Burst request-budget contention, timeout retry, queue recovery and sensitive-error redaction.
- Memory and Decision Ledger consistency after fresh model/session retrieval.
- End-to-end four-agent discussion using Ramli, Yusuf, Luna and Yosef.
- Existing chat, attachments, tasks, proactive messages and WhatsApp draft regression behavior.

Provider HTTP behavior is mocked in these tests. Database writes, authorization, migrations, retrieval, state transitions and orchestration are real.

## Verified without a model connection

The four-agent end-to-end discussion was executed in internal-data mode because no local `YOKA_AI_API_KEY` is configured. It proves participant selection, separate execution, persistence and synthesis attribution, but not subjective model response quality.

## Not verified with a real model

No genuine model request was made because the local environment has no AI API key. Persona quality, naturalness and provider-reported usage against the live configured model remain manual staging checks. The system does not claim those checks passed.

## Important remaining risks

- The burst-budget test exercises the atomic cache counter under rapid contention in one PHP process; it is not a multi-host load test. A staging load test with the production cache driver is still required.
- Queue restart recovery is verified through persisted state and after-response dispatch fakes. Killing a real long-running worker mid-request has not been exercised on Hostinger.
- Provider timeout and retry are verified with mocked connection exceptions, not a deliberately delayed live provider.
- Subjective conversational quality and persona consistency require a bounded staging smoke test with the real configured model.
- Cost remains an estimate based on configured prices and provider usage metadata; it is not invoice reconciliation.
- The Vite product-experience chunk remains above 500 kB and should be optimized separately; it does not block AI correctness but affects frontend loading cost.

## Cost interpretation

Token counts come from provider usage metadata when present. Cost is calculated using configurable per-million-token prices and is explicitly marked as an estimate, not a provider invoice.
