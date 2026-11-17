# Evidence register

Only real, reproducible evidence. Raw outputs were kept in the session scratchpad (not committed).

| ID | Control / risk | Description | Date | Status | Owner |
|---|---|---|---|---|---|
| E-01 | SEC-03, A.8.8 | `composer audit` before: 45 advisories (1 critical) | 2026-11-18 | VERIFIED | Dev |
| E-02 | SEC-03 | `composer audit` after: 0; `check-platform-reqs --lock` passes on PHP 8.2.12 | 2026-11-18 | VERIFIED | Dev |
| E-03 | R-04b | `npm audit`: 14 → 6 (build-time picomatch only); rebuild OK | 2026-11-18 | VERIFIED (corrects earlier E-03, which used `--omit=dev`) | Dev |
| E-04 | Boot | `route:list` boots on PHP 8.2.12 and 8.4.25 | 2026-11-18 | VERIFIED | Dev |
| E-05 | SEC-01/02/10/11 | `SecurityRegressionTest` 7/7 pass | 2026-11-18 | VERIFIED (local disposable DB) | Dev |
| E-06 | Tenant scan | 86 bound-model actions reviewed; 7 traced to service checks; negative tests pass | 2026-11-18 | VERIFIED | Dev |
| E-07 | A.8.25 | CI `security.yml` | — | PENDING (not pushed) | Dev |
| E-08 | A.8.13 | Backup create/verify/restore drill on disposable data: counts equal source, tamper and wrong key detected, refusal of app DB | 2026-11-18 | VERIFIED locally; production PENDING | Dev / Founder |
| E-09 | Policies | ISMS approval | — | PENDING | Founder |
| E-10 | Regression | Full suite 503 tests / 6913 assertions, 0 failures, PHP 8.4.25 **and** 8.2.12 | 2026-11-18 | VERIFIED (MariaDB 10.4 in MySQL 8 mode, not real MySQL 8) | Dev |
| E-11 | SEC-04 | `SpoofedIpThrottleTest` 3/3: proves `ip()` follows XFF and that lockouts hold under rotation | 2026-11-18 | VERIFIED | Dev |
| E-12 | AI scope | `YokaToolRegistry` takes business from the user, re-authorizes outlets, whitelists args; existing `YokaBusinessToolsTest`/`YokaAgentLlmTest` foreign-outlet tests pass in E-10 | 2026-11-18 | VERIFIED | Dev |
| E-13 | Proxy topology | Hostinger edge behaviour | — | BLOCKED (no infra evidence) | Founder |
