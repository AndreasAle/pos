# 02 — Data flow & trust boundaries

```
 [Customer phone]--QR self-order (token, throttled)--+
 [Cashier tablet]--PIN + paired-outlet HMAC cookie---+
 [Owner/Admin browser]--session + CSRF---------------+--> TB1: Hostinger web (yokapos.com, Laravel)
 [Platform staff]--separate guard + email 2FA--------+          |
                                                               |  business_id filter (per query)
                                                               v
                                                  TB2: MySQL u598194357_yokapos (shared, all tenants)
                                                               |
 Outbound (Yoka -> provider)                                   |   Inbound webhooks (provider -> Yoka)
  - OpenAI API: questions + aggregated sales data   <---------+--->  Midtrans: signature + amount check
  - Fonnte (WhatsApp): receipts, phone numbers                      BayarPro: verifyWebhook
  - Telegram Bot API: summaries to linked chats                     Telegram: secret header
  - Midtrans / BayarPro: order ids, amounts                         ShopeeFood: signed requests
  - GoFood / ShopeeFood APIs: menu, orders                          GoBiz OAuth callback
  - SMTP: emails
  - Google OAuth: identity
```

Trust boundaries:
- **TB1 — Internet → app.** Everything from browsers, tablets, webhooks is untrusted. Controls: CSRF (except signed webhooks), throttles, validation, server-side pricing.
- **TB2 — app → shared DB.** No DB-level tenant separation; the app is the only enforcement point. Highest-impact boundary.
- **TB3 — app → AI provider.** Merchant business data leaves Indonesia to OpenAI (US). Processor agreement and privacy notice needed (see compliance docs).
- **TB4 — platform staff → merchant data.** Impersonation exists; must be logged and limited.
- **TB5 — device.** Paired tablet cookie lets anyone with the device reach the PIN pad; PIN throttled per outlet.

Under Yoka control: app code, DB content, config. Provider-controlled: Hostinger infra/backups/TLS, Google, Midtrans, BayarPro, OpenAI, Fonnte, Telegram, GoTo, Shopee.
