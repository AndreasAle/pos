# Yoka AI Workforce 2.0 — Architecture

## Context layers

Context is assembled in bounded layers:

1. System safety and agent role instructions.
2. Confirmed Owner Context.
3. Active decisions and conversation state.
4. Relevant scoped memories.
5. Recent agent conversation.
6. Fresh tool output and operational source data.

Fresh tool output is authoritative for mutable metrics. Stored context is serialized as untrusted reference data and cannot expand permissions.

## Memory scopes

- `owner`: explicit preferences and decisions belonging to the authenticated Platform owner/admin.
- `agent`: working knowledge private to one agent.
- `shared`: approved organizational knowledge reusable by agents.
- Conversation state: temporary topic, mood signal and stable references for one admin-agent pair.

Memories retain their source message, confidence, importance, confirmation state, version and supersession link. Automatic capture currently accepts only explicit owner-language patterns; casual conversation is not persisted.

## Owner Context

Owner Context is explicitly edited by a Super Admin. Entries are keyed policies such as company priorities, financial constraints, positioning and prohibited actions. Explicit entries are marked confirmed and outrank inferred memory.

## Decision ledger

The decision table supports active, proposed, superseded, revoked and completed lifecycle states, conditions, reasons and links to the deciding admin, agent, message and task. Conversation-to-decision promotion remains server-controlled; casual model output is never treated as an approved decision.

## Authorization boundary

All endpoints are under the `platform` guard, completed platform 2FA middleware and controller-side `isSuperAdmin()` checks. The browser never decides tool or memory permissions.

## Current orchestration

`PlatformAiOperations` owns runtime policy, budgets and task execution. `AgentBrain` owns bounded model/tool turns. `PlatformAiContext` owns scoped retrieval, explicit memory capture and conversation-state updates. Tools remain allow-listed in `AgentBrain`.

## Task and approval boundary

Tasks remain proposed until a Super Admin approves them. Chat approval is parsed on the server. Internal tasks are eligible only for allow-listed types. External messages require the explicit WhatsApp approval path and a configured sender. Model text alone cannot mark execution complete.

## Scheduler

Laravel's scheduler selects active, due agents and emits proactive messages. The next architecture stage adds persisted observations, event fingerprints, cooldowns and severity before creating a notification.

## Planned extensions

- Version-bound approval and execution-attempt records.
- Retryable queue jobs with idempotency keys.
- Observation/event store and proactive deduplication.
- Parent/child delegation workflows and bounded discussion sessions.
- Provider usage, latency and estimated-cost records.

## Observability and recovery

Every model HTTP call records its operation, model, agent, task link, provider token usage, tool-call count, latency, status, sanitized error and estimated cost. A daily request guard and estimated-cost guard stop discretionary calls. Task execution uses a dedicated queue job, stable idempotency key and persisted execution attempt. The scheduler redispatches eligible approved tasks after interruption without duplicating completed work.

Platform health reports 24-hour AI call volume, error rate, average latency, estimated cost, active discussions and open events. Provider credentials and raw prompts are never stored in telemetry.
