# Session Monitoring (CEO)

## Goal
Ensure agent "seats" are active, responding, and following the operating system (daily reviews + knowledgebase + artifacts).

System architecture reference: `runbooks/technology-stack.md`.

## What to monitor
### 1) Activity freshness
- Each active agent should have a `session_updates` entry at least once per day.
- Each active agent should have a daily review feedback file created (and eventually filled).

### 2) Protocol compliance
- PM work requests include required artifacts.
- Dev work includes Implementation Notes with KB references.
- QA work includes Test Plan + Verification Report with APPROVE/BLOCK.

### 3) Risk signals
- Recurring failure modes show up repeatedly in daily reviews.
- Blocked testing due to environment happens repeatedly without a mitigation playbook.

## Daily checklist
- [ ] Run `scripts/monitor-sessions.sh`
- [ ] Confirm todays daily review folder exists
- [ ] Identify agents with no update in 24h
- [ ] Create action items (owner + due) for any protocol breaches

## LangGraph orchestrator loop
The default production scheduler is the LangGraph orchestrator:

Recommended runtime: systemd user service

- Install/start: `./scripts/install-systemd-orchestrator.sh`
- Status: `systemctl --user status copilot-sessions-hq-orchestrator.service`
- Logs: `journalctl --user -u copilot-sessions-hq-orchestrator.service -n 200 --no-pager`

Manual/legacy runtime (wrapper loop):

- Start: `./scripts/orchestrator-loop.sh start 60`
- Status: `./scripts/orchestrator-loop.sh status`
- Verify: `./scripts/orchestrator-loop.sh verify`
- Logs: `inbox/responses/orchestrator-latest.log`

Note: do not run `scripts/agent-exec-loop.sh` at the same time as the LangGraph orchestrator.

See `runbooks/orchestration.md` for the full process flow.

## Escalation
- If an agent is stale or blocked: CEO assigns a concrete next action.
- If conflicting directives exist: CEO applies conflict-resolution runbook and/or escalates to human owner.

## Release monitor protocol (no-progress recovery)
- Trigger: if a release team has open issues and no dev outbox progress for 15 minutes.
- Action: `scripts/release-kpi-monitor.py --auto-remediate` performs transcript-level conversation analysis for the dev seat session.
- Health signals checked: recent `session.error`, `tool_use_id` / `tool_result` mismatch errors, and compaction `413` failures.
- Recovery step: if conversation health is unhealthy, rotate the seat session id and run one bounded `agent-exec-next.sh` attempt.
- Retry guardrail: after 3 failed session-reset attempts, automatic resets stop and a CEO queue escalation item is created (`release-session-reset-escalation`).
- Resumption rule: automatic session resets resume only after manual intervention clears the failure condition.

### Handoff-gap control
- Trigger: `open_issues > 0` while Dev status is `complete/done`, Dev has no active findings inbox item, and no fresh progress for the handoff window.
- Action: monitor auto-queues a QA full-audit rerun inbox item and triggers one bounded QA execution.
- Investigation: monitor also queues a dedicated full investigation item (`release-handoff-full-investigation`) on its own cooldown, independent of generic stagnation cooldown.
- Escalation: if monitor cannot queue the QA rerun item, it raises a CEO queue item with topic `release-handoff-gap` for PM triage.
