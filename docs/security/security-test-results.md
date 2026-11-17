# Security test results — 2026-11-18 (run 2)

## Test environment
- **DB:** disposable MariaDB 10.4.32 (XAMPP binaries) with a **fresh data dir** in the session scratchpad on port 3307. The broken XAMPP data dir on 3306 was not touched.
- Server flags set to MySQL 8 behaviour: `--explicit-defaults-for-timestamp=ON`, `sql_mode=ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`.
- **Why not MySQL 8 itself:** Docker is not installed. CI `security.yml` uses a real `mysql:8.0` service but has not run yet (nothing pushed).
- **Run command:** `DB_HOST=127.0.0.1 DB_PORT=3307 DB_USERNAME=root DB_PASSWORD= vendor/bin/phpunit` with DB `fnb_pos_test` (`RefreshDatabase` runs all 40 migrations).

## Results
| Run | Runtime | Tests | Assertions | Failed | Errors | Skipped |
|---|---|---|---|---|---|---|
| First full run (before fixes) | PHP 8.4.25 | 503 | 6912 | 0 | **1** | 0 |
| Final full run | PHP 8.4.25 | 503 | 6913 | 0 | 0 | 0 |
| Final full run | **PHP 8.2.12** | 503 | 6913 | 0 | 0 | 0 |
| `tests/Feature/Security` (10 tests) | PHP 8.4.25 | 10 | 31 | 0 | 0 | 0 |

The final runs include the 7 security regression tests from run 1 and the 3 new spoofed-IP tests.

## Failures found and fixed
1. **Migrations failed** on default MariaDB 10.4 (`1067 Invalid default value for 'started_at'` in `intelligence_agent_runs`). Cause: `explicit_defaults_for_timestamp=OFF`. The test server was configured like MySQL 8; recorded as R-13.
2. **`SecurityRegressionTest`** AI proposal fixture lacked the required `expected_version` → test fixture fixed.
3. **`Pos\OrderTotalsTest::test_change_is_never_negative_on_underpayment`** asserted that a short **cash** sale is accepted, which directly conflicts with SEC-02. Changed to a non-cash (QRIS) underpayment so the change-clamp invariant is still covered. **This is a business-rule change** that needs owner sign-off.

## Other verification
| Check | Result |
|---|---|
| `composer audit` | 0 advisories |
| `composer check-platform-reqs --lock` on PHP 8.2.12 | all satisfied |
| `php artisan route:list` on 8.2 and 8.4 | boots |
| `npm audit` (all deps) before | 14 (2 critical, 12 high) — **earlier "0" result was with `--omit=dev`, which hid bundled axios** |
| `npm audit fix` + `npm run build` | axios 1.21.1; 6 high remain, all `picomatch` ReDoS in vite build tooling (not shipped) |
| CSS diff after rebuild | additions only (classes already used in views) |
| `bash -n deploy.sh` | OK |
| Backup/restore drill | see `04-backup-disaster-recovery.md` (all PASS on disposable data) |

## Not tested
- Real MySQL 8.0, and Hostinger's actual DB version and settings.
- Browser and UI flows (no end-to-end tests), the printer/RawBT path on a device, and production behind Hostinger's proxy.
- CI workflow (first run happens after a push).
