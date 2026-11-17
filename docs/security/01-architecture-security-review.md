# 01 — Architecture security review & findings register

Method: manual code review of routes, middleware, webhook controllers, finance/POS/AI services; automated scan of all 86 controller methods taking a tenant model via route binding (script logic described in `03`); `composer audit`, `npm audit`. Dynamic testing against production was **not** performed (forbidden).

Severity = internal scale (Critical/High/Medium/Low). "Confirmed" means reproduced by reading code paths end to end; "Potential" means not exploit-verified.

| ID | Component | Location | Issue | Status | Severity | Fix / validation |
|---|---|---|---|---|---|---|
| SEC-01 | HTTP responses | `bootstrap/app.php` | No security headers (clickjacking possible on approve/void buttons, no nosniff/HSTS) | Confirmed → **Fixed** | Medium | `app/Http/Middleware/SecurityHeaders.php`; test `SecurityRegressionTest::test_responses_carry_baseline_security_headers` |
| SEC-02 | POS checkout integrity | `app/Services/PosOrderService.php` (~line 177) | Cash / split sale with `paid_amount` < total was stored as `paid`; only the browser prevented it. A cashier (or crafted request) could record a short-paid sale as fully paid | Confirmed → **Fixed** | Medium | Server rejects with 422; 3 tests in `SecurityRegressionTest` |
| SEC-03 | Dependencies | `composer.lock` | 45 advisories incl. Laravel **critical** (TokenGuard bypass — app does not use TokenGuard, so not exploitable here), high: guzzle host check, phpspreadsheet XXE/SSRF, commonmark XSS/DoS, maatwebsite/excel path, symfony | Confirmed → **Fixed** | High | `composer update` with `platform.php=8.2.29`; `composer audit` = 0 advisories. npm audit = 0 |
| SEC-04 | Proxy trust (lockouts VERIFIED FIXED, proxy list OPEN — see 06) | `bootstrap/app.php:25` `trustProxies(at: '*')` | Any client can set `X-Forwarded-For`, so IP-based throttles (login, PIN, webhook) can be evaded by rotating the header, and logs record spoofable IPs | Potential (depends on Hostinger front proxy) | Medium | **Open** — needs Hostinger proxy IP range; then set explicit `at:` list. Not changed blind because a wrong list breaks HTTPS detection |
| SEC-05 | Debug default | `.env.example` | `APP_DEBUG=true` template default risks copying into prod | Confirmed → **Fixed** (template) | Low | Prod `.env` value **not verified** (no server access in this program) |
| SEC-06 | Session | `config/session.php` | `SESSION_ENCRYPT=false`; secure cookie auto from https APP_URL; http_only; lax | Acceptable | Low | Optional: enable encryption |
| SEC-07 | CSP | views | No Content-Security-Policy; inline scripts everywhere | Open | Low/Medium | Roll out CSP in report-only first |
| SEC-08 | Tenancy model | all tenant models | No global `business_id` scope; isolation depends on each query. All 86 bound-model actions checked: 79 check in controller, 7 in service (`FinanceLedger::own`, `ActionService::authorizeProposal`) | Design weakness | Medium | Regression tests SEC-10/11; recommend a `BelongsToBusiness` trait/policy in future |
| SEC-09 | Webhooks | Midtrans/BayarPro/Telegram/ShopeeFood | Signature or secret verification present; Midtrans checks amount and locks row | Reviewed, no issue | — | Existing tests in `tests/Feature/Channels`, `tests/Unit` |
| SEC-10 | Finance BOLA | `FinanceLedgerController` expense/purchase/receivable actions | Controller lacks check but service `own()` enforces → **not vulnerable** | Verified safe | — | New negative tests |
| SEC-11 | AI action proposals | `IntelligenceController::approveAction/executeAction` | Service enforces business match + explicit `SETUJUI` confirmation + expiry + idempotency key | Verified safe | — | New negative test |
| SEC-12 | Prices | `PosOrderService` | Prices, variants, bundles resolved from DB scoped to business; client price ignored | Verified safe | — | Existing `CartIntegrityTest` |
| SEC-13 | Backups | infra | No automated, verified, off-site DB backup | Confirmed gap | High | See `04`; needs owner action |
| SEC-14 | Local test DB | dev machine | XAMPP MySQL system tables corrupted → test suite cannot run locally | Blocker | — | Repair/reinstall local MySQL, or rely on CI job |
