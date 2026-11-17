# Yoka AI Workforce 2.0 — Codebase Audit

## Scope and runtime

- Backend: Laravel 12 on PHP 8.2, Eloquent ORM, MySQL in the supported test and production path.
- Frontend: Blade, Alpine.js, Tailwind CSS and Vite. AI Team is rendered by `resources/views/platform/ai-team.blade.php`.
- Authentication: a separate `platform` guard. Platform routes require authentication and completed 2FA; AI Team controllers additionally require `super_admin`.
- Deployment: Hostinger runs `schedule:run` every minute for `~/yokapos_app`. The live client application in `~/pos_app` is outside this project's deployment scope.

## Existing data flow

Chat flow:

`AI Team Blade UI → platform.ai-team.chat → AiTeamController → PlatformAiOperations → AgentBrain → tool registry/Eloquent → model provider → PlatformAiMessage + PlatformActivityLog → JSON response`

Scheduled flow:

`Laravel scheduler → active/due PlatformAiAgent → PlatformAiOperations::run → internal analysis + optional model reasoning → PlatformAiTask + proactive PlatformAiMessage`

Approval flow:

`proposed task → server-side authorization/validation → approved → after-response execution for internal tasks or explicit WhatsApp send → result/error + activity log`

## Existing storage

- `platform_ai_agents`: identity, status, autonomy, permissions, schedules and budget.
- `platform_ai_messages`: separate conversation history per agent, admin attribution, mode metadata and read state.
- `platform_ai_attachments`: bounded local attachment metadata and extracted model parts.
- `platform_ai_records`: manually entered content, campaign, lead, ticket and finance facts.
- `platform_ai_tasks`: proposal, approval and execution lifecycle.
- `platform_activity_logs`: security and operational audit trail.

## Existing controls

- Controller-side Super Admin checks on every AI Team action.
- Route throttling for chat, runs, records and read state.
- Server-side phrase matching for chat approval.
- Task approval and action type checks.
- Daily request counters, tool/task limits, timeouts and execution locks.
- External actions require explicit approval and configured integration.
- Tools are allow-listed; no arbitrary SQL, shell or general network tool is exposed.

## Gaps found

1. Only the latest conversation messages were provided to an agent; no durable memory or summaries existed.
2. No structured owner policy/preferences existed outside prompts.
3. References such as “yang kedua” had no persisted conversation state.
4. Decisions were embedded in chat/tasks, not represented as versioned business constraints.
5. Delegation created a task but did not persist a parent/child workflow or result hand-off.
6. Proactivity was schedule-based and stale-task based, without observation fingerprints or change detection.
7. Provider usage, latency, token estimates and per-workflow costs were not persisted.
8. Internal execution used an after-response closure rather than a dedicated, retryable job record.
9. A failed WhatsApp send remained approved without a first-class retry action.
10. Channel readiness displayed Meta WhatsApp credentials while the implemented sender used Fonnte.

## Security risks and decisions

- Memory, attachments and records are untrusted data. They must never be inserted as higher-priority instructions.
- Current verified tool output must override remembered numeric facts.
- Inferred memories must not overwrite confirmed owner context.
- Natural-language approval must stay deterministic and tied to eligible tasks.
- Platform data must remain separate from tenant-facing assistants and must not disclose one tenant's data to another.
- Long AI work should move to bounded, idempotent queue jobs before multi-agent discussions are enabled.

## Implementation direction

Relational indexed retrieval is sufficient for the current scale. Vector infrastructure is intentionally deferred until measured retrieval quality justifies its operational cost. New context tables are additive and do not rewrite existing AI Team data.
