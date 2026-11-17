# Risk register & treatment plan

Scoring: Likelihood (1–5) × Impact (1–5). This is an internal metric, not an ISO score. Status uses: VERIFIED FIXED (fixed and test-proven in this repo, not yet deployed) · VERIFIED SAFE · OPEN · BLOCKED · NOT TESTED.

| ID | Risk | L×I | Treatment | Owner | Status (2026-11-18) |
|---|---|---|---|---|---|
| R-01 | Data loss: no verified production backup/restore (SEC-13) | 3×5=15 | `yoka:backup` + restore drill command built and proven on disposable data | Founder | **OPEN** in production (not enabled/drilled on server) |
| R-02 | Cross-tenant access from a missed `business_id` filter (SEC-08) | 2×5=10 | Negative tests; future global scope | Dev | VERIFIED SAFE for audited paths; design weakness remains |
| R-03 | Lockout bypass via spoofed `X-Forwarded-For` (SEC-04) | 3×4=12 → 3×2=6 | IP-independent lockouts (login, PIN, platform) | Dev | VERIFIED FIXED for lockouts; proxy list **OPEN** (needs Hostinger evidence); guest route throttles still per-IP |
| R-04 | Vulnerable PHP dependencies (SEC-03) | 3×4=12 → 1×4=4 | Updated, pinned to PHP 8.2, weekly CI | Dev | VERIFIED FIXED |
| R-04b | Vulnerable frontend deps bundled in `public/build` (axios, critical/high) | 3×3=9 → 1×3=3 | `npm audit fix` + rebuild | Dev | VERIFIED FIXED; picomatch ReDoS (build-time only) accepted, needs sign-off |
| R-05 | Short-paid cash sale recorded as paid (SEC-02) | 3×3=9 → 1×3=3 | Server-side 422 | Dev | VERIFIED FIXED (business-rule change: merchants must use receivables for credit sales) |
| R-06 | Merchant/customer data sent to OpenAI without DPA/notice (customer names via `top_customers`) | 4×3=12 | Notice, DPA, minimisation | Founder | OPEN |
| R-07 | Secrets leak | 2×5=10 | gitleaks CI | Founder | OPEN until first CI run |
| R-08 | Single person holds all admin/server access | 4×4=16 | Second admin, 2FA everywhere, access review | Founder | OPEN |
| R-09 | Clickjacking / MIME sniffing (SEC-01) | 2×3=6 → 1×3=3 | Headers middleware | Dev | VERIFIED FIXED |
| R-10 | Lost/stolen paired tablet | 3×2=6 | PIN lockout per outlet, unpair | Merchant | Accepted pending sign-off |
| R-11 | Breach not notified within 3×24 h | 2×4=8 | IR plan | Founder | OPEN (not exercised) |
| R-12 | Prod running with `APP_DEBUG=true` | 2×4=8 | `deploy.sh` now aborts when debug is on | Dev | NOT TESTED in production |
| R-13 | Migrations fail on MariaDB with `explicit_defaults_for_timestamp=OFF` (e.g. `intelligence_agent_runs`) | 2×3=6 | Confirm server setting before next fresh install; prod already migrated | Dev | OPEN (environment-dependent) |
