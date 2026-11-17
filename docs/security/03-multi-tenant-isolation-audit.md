# 03 — Multi-tenant isolation audit

**Model:** one shared MySQL database; tenant key `business_id`; sub-scope `outlet_id`. No Eloquent global scope. `EnsureBusinessIsSet` guarantees an authenticated merchant user has an active business; each controller/service must filter by `$user->business_id`.

## Method
1. Automated scan of every controller method (excluding `Platform/`) that receives a tenant model through route-model binding: 86 actions found.
2. Each was checked for an ownership check in the method body (`business_id`, `authorize*`, `abort_unless`, etc.). 7 lacked an in-controller check.
3. The 7 were traced into their services:

| Action | Enforced by | Result |
|---|---|---|
| `expenses.void` | `FinanceLedger::voidExpense → own()` (404) | Safe |
| `purchases.pay` / `purchases.cancel` | `FinanceLedger` under row lock → `own()` | Safe |
| `receivables.pay` / `receivables.void` | `FinanceLedger::own()` | Safe |
| `intelligence.actions.approve/execute` | `ActionService::authorizeProposal` (403) | Safe |

4. Foreign IDs in request bodies: `customer_id` (orders, receivables), `outlet_id` (finance), `expense_category_id`, `promotion_id`, `bundle_id`, product IDs are all re-queried with `business_id` (`ownCustomerId`, `FinanceLedger::outlet`, `forBusiness` scopes).

## Negative tests
| Test | Covers |
|---|---|
| `tests/Feature/TenantIsolationCustomerTest.php` (existing) | foreign customer on order |
| `SecurityRegressionTest::test_owner_cannot_void_another_business_expense` | HTTP + service |
| `...::test_owner_cannot_pay_or_void_another_business_receivable` | HTTP, state unchanged |
| `...::test_ai_action_proposal_of_another_business_cannot_be_approved_or_executed` | AI actions |

**Execution status: VERIFIED 2026-11-18** — all pass in the full run (503/503, PHP 8.2 and 8.4) on a disposable DB; also wired into CI `security.yml`.

## Not yet covered (gaps)
- Scheduled jobs: iterate `Business` explicitly — reviewed by reading, no test.
- AI assistant read tools (`YokaAssistantService`, `TelegramAssistant`): read by code, business id taken from the authenticated user / linked chat; no dedicated cross-tenant test yet.
- Exports/reports: guarded by `role:owner,admin` and business filter in queries; no per-report negative test.
- Platform impersonation: legitimate cross-tenant access; needs audit-trail review.

## Recommendation
Add a `BelongsToBusiness` trait (global scope + creating hook) incrementally, starting with finance and order models, so a forgotten `where` fails closed.
