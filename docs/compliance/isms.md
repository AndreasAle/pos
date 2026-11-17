# ISMS core — DRAFT (no section is approved)

## Scope
Development and operation of the Yoka POS SaaS (yokapos.com, `~/yokapos_app`, DB `u598194357_yokapos`), its source repository, CI, and the people who run it. **Excluded:** client site pos.conweb.id / `~/pos_app`.

## Information security policy (short)
1. Merchant data is confidential; every access is scoped to the merchant (business) server-side.
2. Secrets live only in server `.env` / CI secrets, never in the repo.
3. Changes go through git, automated tests, and the deploy workflow; production is changed only via `deploy.sh`.
4. Security-relevant dependencies are audited weekly and on every push.
5. Backups are taken daily and restore-tested quarterly.
6. Incidents follow `docs/security/05-incident-response-plan.md`.
7. AI agents operate under AGENTS.md autonomy tiers; outbound messages and money require human approval.

## Classification
Public · Internal · Confidential (merchant business data, staff data) · Restricted (secrets, customer phone numbers, payment references).

## Risk methodology
Likelihood 1–5 × Impact 1–5; ≥12 must be treated within 30 days; acceptance requires founder sign-off recorded in `docs/security/risk-register.md`.

## Access control
Least privilege; unique accounts; 2FA on GitHub, Hostinger, Google Workspace, platform admin; quarterly access review; immediate revocation on leaving.

## Secure development / vulnerability / change management
- Every security fix ships with a regression test (`tests/Feature/Security`).
- Advisories: critical/high patched ≤7 days, medium ≤30 days.
- Backup before every production `migrate`.

## Internal audit plan
Annual, first by 2027-02: sample 10 SoA controls, verify evidence register entries, check access list.

## Management review template
Date · attendees · risk register changes · incidents · audit results · objectives · decisions · approvals.

## Corrective action register
| ID | Source | Action | Owner | Due | Status |
|---|---|---|---|---|---|
| CA-01 | SEC-14 | Repair local MySQL so test suite runs | D | 2026-11-25 | Open |
| CA-02 | SEC-13 | Daily backup + first restore test | F | 2026-12-01 | Open |
| CA-03 | SEC-04 | Restrict trusted proxies | D | 2026-12-15 | Open |

Not yet written (next phase): business continuity plan, supplier assessments per vendor, awareness program, BCP test.
