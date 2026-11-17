# 05 — Incident response plan (draft, not approved)

Owner: Yoka founder (incident lead) until a team role exists.

1. **Detect** — sources: Laravel log errors, failed-login spikes, Midtrans/BayarPro "invalid signature" warnings, merchant report, GitHub security alerts, CI `security.yml` failure.
2. **Triage (≤1 h)** — classify: P1 data exposure / payment manipulation / takeover; P2 outage; P3 other.
3. **Contain** — `php artisan down --secret=…` on yokapos_app only; rotate affected keys in server `.env` (OpenAI, Fonnte, Telegram, Midtrans, BayarPro, GoBiz, Google); revoke sessions (`php artisan session:flush`/clear sessions table); disable affected integration.
4. **Preserve evidence** — copy logs and DB snapshot before changes; record timeline.
5. **Eradicate & recover** — patch, test in CI, deploy via `./deploy.sh`; restore from backup if data corrupted (see `04`).
6. **Notify** — UU PDP Art. 46: written notice to data subjects and the authority **within 3×24 hours** of a personal-data breach (content: data involved, when/how, handling & recovery). Notify affected merchants. Legal review required for exact obligations.
7. **Post-incident** — root cause, corrective action in `docs/compliance/corrective-action-register.md`, add regression test.

Client site `pos.conweb.id` incidents are handled separately by its owner; do not act on `~/pos_app` without explicit instruction.

Tabletop exercise: NOT YET CONDUCTED.
