# 06 — Trusted proxy audit (SEC-04)

## Current config
`bootstrap/app.php`: `$middleware->trustProxies(at: '*');` with Laravel's default header set (`X-Forwarded-For/Host/Port/Proto`, AWS ELB). Comment says it was added for ngrok/reverse proxies.

## What depends on the client IP
| Use | Location | Impact if spoofed |
|---|---|---|
| Owner login lockout key `email|ip` | `Auth/LoginController::throttleKey` | Unlimited password guessing against one email |
| PIN lockout key `outlet|ip` | `Auth/PinLoginController::throttleKey` | Unlimited PIN guessing on a paired device |
| 36 route `throttle:` middlewares (keyed by user id when logged in, else IP) | `routes/web.php` | Guest endpoints (login, register, forgot-password, self-order, platform login, webhooks) not rate limited in practice |
| `ActivityLog` / `PlatformActivityLog` `ip_address` | models | Audit trail IP can be forged |

## Verified behaviour
`SpoofedIpThrottleTest::test_client_ip_follows_forwarded_header_under_current_proxy_config` shows that a request from `REMOTE_ADDR=203.0.113.10` carrying `X-Forwarded-For: 198.51.100.8` resolves `request()->ip()` to `198.51.100.8`. **The spoof is exploitable at the application layer.** Whether it is exploitable in production depends on what sits in front of PHP:
- If Hostinger's edge (LiteSpeed / CDN) **overwrites** `X-Forwarded-For`, the attacker value is replaced. Unverified.
- If it **appends** (common), Laravel trusting `*` uses the left-most value = attacker-controlled. Exploitable.
- If there is no proxy at all, `*` trusts the client directly. Exploitable.

No infrastructure evidence was available in this program (no server access), so the topology is **NOT VERIFIED**.

## Mitigation applied (topology-independent)
IP-independent lockouts that hold no matter what `ip()` returns:
- Owner login: max 20 failures per email per 15 min (`ACCOUNT_MAX_ATTEMPTS`).
- PIN: max 30 wrong PINs per outlet per 15 min (`OUTLET_MAX_ATTEMPTS`). For a 6-digit PIN, guessing the whole space would take about 500 days.
- Platform login: max 10 failures per email per 15 min.
Tests: `SpoofedIpThrottleTest` (rotating forwarded IP does not bypass).

Trade-off: someone who knows an owner's email can lock that account for 15 minutes. That is accepted as lower risk than credential guessing.

## Required production configuration (to apply after verification)
1. Find what PHP actually receives on yokapos.com: open the site from a known public IP, then in hPanel → Access logs (or `~/logs`) compare the logged client address with your own IP, and check whether an `X-Forwarded-For` header arrives (Hostinger support can confirm the edge behaviour in writing).
2. If `REMOTE_ADDR` equals your own public IP → no proxy → set `trustProxies(at: [])` (trust nothing).
3. If `REMOTE_ADDR` is a Hostinger/CDN address → get the official egress range from Hostinger support (or Cloudflare's published list if Cloudflare is enabled), then
   `trustProxies(at: explode(',', env('TRUSTED_PROXIES', '')))` with `TRUSTED_PROXIES` set in server `.env`.
4. Re-test HTTPS detection (`request()->isSecure()`), redirects, Google OAuth callback and Midtrans callback after the change.

Production is unchanged; `bootstrap/app.php` still trusts `*` until step 1 is done.
