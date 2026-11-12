# Yoka Intelligence Operations & Release Runbook

## Feature flags

- `FEATURE_INTELLIGENCE=true` — owner/admin read-only chat, tools, renderer and insights.
- `FEATURE_INTELLIGENCE_ACTIONS=false` — product-availability action preview/approval/execute. Keep false until beta tenant review.
- `FEATURE_INTELLIGENCE_AGENTS=false` — scheduled read-only agents. Requires working scheduler and queue worker.
- `YOKA_AI_API_KEY` — optional; without it deterministic safe fallback remains available.
- `YOKA_AI_MODEL`, `YOKA_AI_TIMEOUT`, `YOKA_AI_MAX_HISTORY`, `YOKA_AI_DAILY_REQUEST_BUDGET` — model routing and budget controls.

No secret value belongs in source control.

## Runtime requirements

- Run `php artisan schedule:run` every minute from cron.
- Keep `php artisan queue:work --tries=3` supervised when agents are enabled.
- Monitor `failed_jobs`, `assistant_tool_runs`, `intelligence_agent_runs`, response duration/token usage and application logs.
- Agent defaults are disabled. Owner/admin must explicitly enable each agent in Tanya Yoka.

## Release gates

1. Migrations execute on the MySQL test database and have additive `down()` paths.
2. Intelligence tests include 50 multi-turn scenarios, metric fixtures, split payment, tenant boundaries, action replay/staleness/expiry and agent idempotency/timezone.
3. Full repository tests, targeted Pint, Blade compilation and Vite production build pass.
4. Push only after clean diff review; GitHub deployment must complete both domain health checks.
5. Enable actions/agents only after the read-only beta is reviewed with a real tenant.

## Rollback

1. Disable `FEATURE_INTELLIGENCE_ACTIONS` and `FEATURE_INTELLIGENCE_AGENTS`; run `php artisan config:cache`.
2. If needed, disable `FEATURE_INTELLIGENCE` to remove the navigation and return 404 from intelligence endpoints.
3. Revert the application commit and deploy. Additive tables/columns may safely remain during code rollback.
4. Only after data retention review, roll back the latest migration batch. This deletes proposal/agent/tool-run history and is therefore not the first rollback choice.

## Known data limitations

- Gross profit is an estimate whose coverage depends on item HPP snapshots; no net-profit claim.
- POS QRIS/cash values are recorded collections, not settlement or gateway fees.
- Inventory is theoretical; no physical count, lot or FIFO basis exists.
- Supplier/PO/invoice analysis, payroll/overtime/break agents, weather, delivery watchdog and secure document ingestion remain disabled because the required schemas/integrations are absent.
