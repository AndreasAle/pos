# Indonesian privacy (UU No. 27/2022 PDP) — gap assessment, processing inventory, remediation

Not legal advice. Items marked ⚖ need review by an Indonesian lawyer.

## Roles
| Processing | Data subjects | Yoka role |
|---|---|---|
| Merchant accounts, billing, onboarding, support (Adit/CS agent) | Owners, staff | **Controller** |
| Merchant's sales, customers, loyalty, receipts by WhatsApp | Merchant's customers | **Processor** for the merchant |
| Platform analytics/AI insights over merchant data | Merchants (and indirectly customers) | Processor; controller if used for Yoka's own purposes ⚖ |

## Processing inventory & sub-processors
| Provider | Data | Location | DPA |
|---|---|---|---|
| Hostinger | All DB, files, logs | Provider-chosen DC ⚖ | Unknown |
| OpenAI | Assistant questions, aggregated sales, possibly names | USA (cross-border) | Not in place |
| Google | Login identity | Global | Standard terms |
| Midtrans / BayarPro | Order ID, amount | Indonesia | Standard terms |
| Fonnte | Customer phone, receipt | Indonesia | Unknown |
| Telegram | Daily summaries to linked chats | Global | N/A (consumer) |
| GoTo (GoFood), Shopee | Menu, orders | Indonesia | Partner terms |

## Gaps
1. No privacy notice/policy describing purposes, basis, retention, rights.
2. No processor agreement between Yoka and merchants (Art. 51 obligations).
3. Cross-border transfer to OpenAI without documented adequacy/safeguards/consent (Art. 56) ⚖.
4. No data-subject request process (access, correction, deletion, withdrawal).
5. No retention schedule; no account deletion procedure (soft states only).
6. Breach notification 3×24 h not yet operationalised (contacts, templates).
7. DPO appointment applicability (Art. 53) ⚖.
8. PSE Lingkup Privat registration with Komdigi — likely required for a SaaS serving Indonesian users; status unknown ⚖.

## Remediation plan
| # | Action | Owner | Target |
|---|---|---|---|
| P1 | Publish privacy notice + merchant DPA (Terms) | F + lawyer | 60 days |
| P2 | Check/obtain PSE registration | F | 30 days |
| P3 | Minimise AI prompts (no customer names/phones), document transfer basis | D | 60 days |
| P4 | Account & customer deletion/export procedure | D | 90 days |
| P5 | Retention: logs 90 days, AI messages 12 months (proposal) | F | 90 days |
| P6 | Breach notification templates + authority contact | F | 30 days |
