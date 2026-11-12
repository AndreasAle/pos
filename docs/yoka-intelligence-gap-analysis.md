# Yoka Intelligence — Repository Audit & Gap Analysis

Baseline audited: `cfab013` on `main`, 12 November 2026. Source specification: *Yoka Intelligence — Toast IQ Benchmark & Codex Master Blueprint v1.0* (26 pages).

## Current architecture

| Area | Existing verified | Gap / risk |
|---|---|---|
| Access | Intelligence routes require authenticated business and owner/admin role. Conversation and insight mutations check `business_id`. | Tool queries do not yet carry an explicit authorized outlet scope. |
| Conversation | Conversations/messages persist; social turns avoid tools; recent follow-ups carry the last tool arguments. | No structured/versioned state, summary, referent pointer, or evidence pointer. Fallback keyword routing is still too influential. |
| OpenAI | Chat Completions calls are server-side; model has no arbitrary SQL/HTTP tool. | Router schema is JSON mode rather than a versioned closed contract; no request/token/duration trace or per-tenant budget. |
| Data tools | Sales, product, inventory, peak hours, payments, outlets, customers and open shifts exist. | Results lack a common evidence envelope; payment tool ignores split-payment rows; date semantics and outlet scope are incomplete. |
| Metrics | Paid orders and item cost snapshots are available. | `grand_total` is payment-inclusive; gross/net/payment definitions are not centralized. Refund/discount coverage must be explicit. |
| Renderer | Text, KPI, bar chart and table render conditionally. | No typed blocks, evidence chips, line-chart semantics, suggestions, uncertainty, copy/retry or accessible data source details. |
| Insights | Deterministic sales, AOV, stock, margin, reversal, peak-hour and outlet detectors exist. | Evidence/confidence/sample/dedup lifecycle is incomplete; refresh happens on page load. |
| Actions | Existing domain controllers/services mutate products and inventory with tenant checks. | No immutable prepare/approve/execute proposal, expiry, payload hash, optimistic concurrency or replay protection. |
| Agents | Laravel scheduler, database queue and notification jobs already exist. | No per-tenant agent definitions, timezone-aware run keys, run audit, suppression or pause controls. |
| Deployment | Push to `main` runs Hostinger deploy and verifies `/up` on both production domains. | No staging environment is configured; migrations therefore require additive schema and rollback-safe releases. |

## Metric dictionary and data readiness

| Metric/domain | Status | Source and limitation |
|---|---|---|
| Gross item sales | Ready | `order_items.subtotal` on paid orders; excludes cancelled/refunded orders. |
| Discounts | Ready | `orders.discount_amount`; attribution to individual items is unavailable. |
| Net sales | Partial | Can be defined deterministically as paid order subtotal minus discount. Refund rows are excluded by status, but partial refunds do not exist. |
| Payment collected | Ready | Non-split `orders.grand_total`; split orders must aggregate `order_payments.amount`. This is not gateway settlement. |
| Transactions/AOV | Ready | Eligible paid orders; AOV denominator is explicit. |
| Gross profit estimate | Partial | Item price/subtotal and cost snapshot exist. Completeness depends on non-zero `cost_price`; it is never called net profit. |
| Inventory | Partial | Current theoretical stock and stock movement ledger exist. Physical stock counts/lots/FIFO do not. |
| Recipe cost | Ready with coverage | Recipes and ingredient average cost exist; missing recipes/costs must lower completeness. |
| Shift/cashier | Ready | Shifts and sales by cashier exist. Wage, breaks, payroll and overtime rules do not. |
| Purchasing/suppliers | Blocked by data | No supplier, PO or invoice schema. |
| Gateway fees/settlement | Blocked by data | POS records payment methods, not settlement or processor fee. |
| Weather/delivery integrations | Blocked | No authorized external source/integration. |

## Milestone capability matrix

| Milestone | Baseline | Target / gate |
|---|---|---|
| M0 Audit | Partial | This report, feature flags, rollback and risks documented. |
| M1 Chat engine | Partial | Structured context, primary LLM planner, bounded recent history, closed answer contract, 50+ eval cases. |
| M2 Business tools | Partial | Trusted tenant/outlet scope, strict args, metric dictionary, split payments, evidence envelope and deterministic fixtures. |
| M3 Renderer | Partial | Typed conditional blocks, evidence/as-of, uncertainty, mobile/accessibility and no fake empty charts. |
| M4 Insight feed | Partial | Evidence/confidence/sample guards, dedup lifecycle and no page-load computation. |
| M5 Safe actions | Missing | Product availability prepare/approve/execute via domain service, audit, expiry, version and replay tests. |
| M6 Agents | Missing | Four feasible read-only agents with timezone/idempotent runs/suppression. |
| M7 Advanced | Blocked/partial | Forecast may be feasible; files, suppliers, payroll, weather and auto-marketing remain off until data/security readiness. |

## Production risks and controls

- Cross-tenant/outlet access: derive IDs only from authenticated user/business and validate every requested outlet.
- Financial hallucination: backend metrics and evidence are authoritative; prose cannot create numbers.
- Split payments: aggregate `order_payments`, never assign a whole split bill to its first method.
- Time boundaries: resolve local calendar ranges in the business timezone using `[start, end)` semantics.
- Prompt injection: product/customer text is untrusted data and never changes system/tool policy.
- Mutations: immutable proposal, explicit UI approval, re-authorization, version check, transaction, idempotency and audit.
- Agent spam: unique run keys, suppression window and empty/sample guards.
- Rollback: all schema changes are additive with `down()` support; intelligence is feature-flagged and existing POS paths remain untouched.

## Rollout

Owner/admin read-only beta → evidence-backed insights → product-availability action beta → four read-only agents. Advanced features stay disabled until their data and operational gates are met.
