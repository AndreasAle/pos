# 04 — Backup & disaster recovery

## Mechanism (in repo, added 2026-11-18)
| Part | Where |
|---|---|
| Dump → gzip → encrypt (libsodium secretstream XChaCha20-Poly1305, tamper-evident) | `app/Services/Backup/DatabaseBackup.php` |
| `php artisan yoka:backup` — create, verify (decrypt + gunzip + `-- Dump completed` trailer + sha256), prune by age, log + email alert on failure, exit code 1 on failure | `app/Console/Commands/BackupDatabase.php` |
| `php artisan yoka:backup --verify-only=FILE` | same |
| `php artisan yoka:backup-restore-test FILE restoretest_x [--drop]` — restores **only** into a DB whose name contains `restoretest` and differs from the app DB; prints row counts and duration | `app/Console/Commands/RestoreBackupTest.php` |
| Daily 02:30 schedule, only when `YOKA_BACKUP_ENABLED=true` | `routes/console.php` |
| Settings | `config/backup.php`, `.env.example` (`YOKA_BACKUP_*`) |

Security properties: DB password passed through a 0600 temp `--defaults-extra-file` (never on the command line); backup dir 0700, files 0600; plaintext temp file deleted even on failure; key only in server `.env`.

## Local verification (disposable data only)
Environment: disposable MariaDB 10.4.32 on port 3307 with a fresh data dir in the session scratchpad; source DB `yoka_backup_src` = all migrations + `SubscriptionPlanSeeder` + `DemoBusinessSeeder`. No production or client data involved.

| Check | Result |
|---|---|
| Backup generated | PASS — 13,058 bytes encrypted, 0.9 s |
| Integrity verify | PASS — decrypts to 118,831 bytes of complete SQL |
| Restore into `restoretest_drill1` | PASS — 2 s; row counts equal source (migrations 40, businesses 1, users 4, orders 5, products 10); DB dropped after |
| Restore into the app DB name | REFUSED, as designed |
| 1 flipped byte | Detected: "Decryption failed (wrong key or tampered file)" |
| Wrong key | Detected, same message |
| mysqldump missing | Exit 1, `backup.failed` logged |

## Production status
| Item | Status |
|---|---|
| Deployed | NO (nothing committed or deployed) |
| `mysqldump` available on Hostinger | NOT VERIFIED |
| Scheduled run on server | NOT VERIFIED |
| Restore of a production backup | NOT TESTED |
| Off-site copy | NOT IMPLEMENTED — backups sit on the same server; copy weekly to another location |

**Production backups are not operational until the steps below are done and a restore drill is recorded.**

## Enabling on the server (owner, yokapos_app only)
1. Generate the key **once**: `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"` and store a copy in a password manager. Losing it makes every backup unreadable.
2. In `~/yokapos_app/.env`: `YOKA_BACKUP_ENABLED=true`, `YOKA_BACKUP_KEY=…`, `YOKA_BACKUP_PATH=/home/u598194357/backups/yokapos` (outside `public_html`), `YOKA_BACKUP_ALERT_EMAIL=…`.
3. `php artisan config:cache && php artisan yoka:backup` → expect `OK: … sha256 …`.
4. Restore drill on a separate DB created in hPanel whose name contains `restoretest` (for example `u598194357_restoretest`):
   `php artisan yoka:backup-restore-test /home/u598194357/backups/yokapos/<file>.sql.gz.enc u598194357_restoretest --drop`
   On shared hosting the DB user may lack `CREATE DATABASE`, so create the DB in hPanel first. Record the output in `evidence-register.md`.
5. Repeat the drill quarterly.

## Targets (proposed, not approved)
RPO 24 h · RTO 4 h · retention 14 days on server + weekly off-site.
