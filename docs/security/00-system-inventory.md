# 00 — System inventory (Yoka POS, `yokapos_app`)

Captured 2026-11-18 from the repository at commit `2167a28` (plus uncommitted work). Scope is **Yoka POS only**; the client site `pos.conweb.id` / `~/pos_app` is out of scope and must not be touched (AGENTS.md).

| Area | Finding | Evidence |
|---|---|---|
| Backend | Laravel 12 (12.73 after this program's update), PHP ^8.2 (lock pinned to 8.2 platform) | `composer.json`, `composer.lock` |
| Frontend | Blade + Alpine.js, Tailwind via Vite; build committed to `public/build` | `package.json`, `resources/views` |
| Routes | Single `routes/web.php` (283 routes), no `api.php`; JSON endpoints via `json` middleware | `php artisan route:list` |
| Auth (merchant) | Email+password, Google OAuth (Socialite), email verification, password reset, cashier PIN login on paired tablet (HMAC cookie) | `app/Http/Controllers/Auth/*`, `bootstrap/app.php` |
| Auth (platform staff) | Separate `PlatformAdmin` guard with email 2FA (`platform.2fa`) and impersonation | `routes/web.php:141-190`, `EnsurePlatformTwoFactor` |
| Authorization | `role:` middleware (owner/admin/cashier…), spatie/permission installed, per-controller/service ownership checks | `RoleMiddleware`, `FinanceLedger::own()` |
| Tenancy | Shared MySQL DB, `business_id` column on tenant tables, **no global scope** — isolation is enforced per query/controller/service | see `03-multi-tenant-isolation-audit.md` |
| Database | MySQL (prod `u598194357_yokapos`; tests `fnb_pos_test`) | `phpunit.xml`, AGENTS.md |
| Payments | Midtrans (subscriptions, QRIS), BayarPro (order payments) — card data never touches Yoka (hosted/QR) | `MidtransController`, `BayarProWebhookController` |
| Webhooks | Midtrans (signature + amount check), BayarPro (`verifyWebhook`), Telegram (secret header, `hash_equals`), ShopeeFood inbound (signed, `ShopeeFoodSigner`) | `routes/web.php:66-86,474` |
| Delivery channels | GoFood (GoBiz OAuth), ShopeeFood | `GoBizOAuthController`, `ShopeeFoodInboundController` |
| AI | "Tanya Yoka" assistant (OpenAI API), Intelligence insights + action proposals (approval `SETUJUI`), Platform AI team (Adit/CS etc.), Telegram assistant | `app/Services/YokaAssistantService.php`, `app/Services/Intelligence/ActionService.php`, `app/Services/PlatformAgents/*` |
| Messaging | Fonnte (WhatsApp), Telegram Bot API, SMTP mail | `config/services.php` |
| Scheduler | `schedule:run` via hPanel cron: insights refresh, Telegram summaries, low stock, unclosed shift | `routes/console.php` |
| Uploads | Profile avatar, assistant attachments, AI-team attachments | `ProfileAvatarController`, `AssistantAttachment` |
| Devices | **No Android app in this repo.** Cashier tablets use the browser + PIN pairing; `tools/kasir/Buat-Shortcut-Kasir.bat` creates a Windows kiosk shortcut | `tools/kasir` |
| Printers | Browser print / RawBT receipt intent | `tests/Feature/Orders/RawBtReceiptTest.php` |
| Hosting | Hostinger shared hosting, deploy via GitHub Action SSH → `deploy.sh` (refuses client paths) | `.github/workflows/deploy-production.yml`, `deploy.sh` |
| CI | Deploy workflow; **new** `security.yml` (composer/npm audit, gitleaks, security tests) | `.github/workflows/` |
| Tests | ~PHPUnit feature suite incl. tenant isolation, POS integrity, platform | `tests/` |
| Backups | **None automated in repo.** Only manual guidance in deploy docs; Hostinger's own backups not verified | see `04-backup-disaster-recovery.md` |
| Logging | Laravel log, `ActivityLog`, `PlatformActivityLog`, `IntelligenceAuditEvent`, AI tool runs | `app/Models` |
