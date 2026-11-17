# Executive security report — Yoka POS (updated 2026-11-18, run 2)

Internal assessment only. Not an ISO certification, and not a legal compliance opinion.

## Decision: **CONDITIONAL GO** for deploying this change set to yokapos.com
**Evidence for:**
- 503/503 tests pass on PHP 8.2.12 and 8.4.25.
- 0 PHP advisories.
- Bundled frontend criticals fixed.
- Every fix has a passing regression test.
- No migrations added.
- The client site is untouched.

**Conditions (all required before or at deploy):**
1. Owner accepts the business-rule change: short **cash** payments are now rejected (HTTP 422); credit sales go through receivables.
2. Take a database backup before deploying (manual `mysqldump`, or `yoka:backup` once its key is set).
3. Confirm production `APP_DEBUG=false`. The new `deploy.sh` guard aborts otherwise; it runs after migrate, so the site stays up but the deploy is marked failed.
4. Let CI `security.yml` run once green (the first real MySQL 8.0 run).
5. Accept the build-time-only picomatch advisory.

**Not a GO for "production is secure":** backups are not operational on the server, the proxy topology is unverified, and organisational controls (R-06, R-08) are open.

## Status by issue
| ID | Issue | Status |
|---|---|---|
| SEC-01 | Security headers / clickjacking | VERIFIED FIXED |
| SEC-02 | Short-paid cash sale saved as paid | VERIFIED FIXED |
| SEC-03 | PHP dependency advisories | VERIFIED FIXED |
| SEC-03b | Bundled axios critical/high | VERIFIED FIXED (picomatch build-time OPEN, accepted pending sign-off) |
| SEC-04a | Login/PIN/platform lockout bypass via XFF | VERIFIED FIXED |
| SEC-04b | Trusted proxy list `*` | OPEN / BLOCKED (needs Hostinger evidence; `06-proxy-configuration-audit.md`) |
| SEC-04c | Guest route throttles (register, forgot-password, self-order, webhooks) still per spoofable IP | OPEN |
| SEC-05 | APP_DEBUG | VERIFIED FIXED in template and deploy guard; production NOT TESTED |
| SEC-06 | Session config / logout invalidation | VERIFIED SAFE |
| SEC-07 | No CSP | OPEN |
| SEC-08 | No global tenant scope | OPEN (design); audited paths VERIFIED SAFE |
| SEC-09 | Webhook signatures | VERIFIED SAFE (existing tests pass) |
| SEC-10/11 | Finance and AI proposal cross-tenant | VERIFIED SAFE |
| SEC-12 | Server-side pricing / stock (existing POS integrity suites) | VERIFIED SAFE |
| SEC-13 | Backups | Mechanism VERIFIED locally; production OPEN |
| SEC-14 | Local test DB | Resolved via disposable instance; XAMPP 3306 data still broken (left untouched) |
| R-13 | MariaDB `explicit_defaults_for_timestamp=OFF` breaks fresh migrate | OPEN |
| AI tools | Merchant assistant tool scoping | VERIFIED SAFE; data sent to OpenAI includes customer names (privacy OPEN) |

## Next 30 days
1. Enable `yoka:backup` on the server, run a restore drill, record it as E-08.
2. Get Hostinger's proxy/edge behaviour in writing, then set the explicit `TRUSTED_PROXIES`.
3. Add per-email/per-target caps to guest routes (forgot-password, register).
4. Turn on 2FA on GitHub, Hostinger and Google, and add a second admin.

60/90-day items and external-audit prerequisites are unchanged from run 1. See `docs/compliance/`.
