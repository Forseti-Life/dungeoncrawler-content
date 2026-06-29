# Agent Instructions: dev-infra

## Authority
This file is owned by the `dev-infra` seat.

## Scope
- Implement infrastructure changes: automation stability, scalability, reliability, security hardening.

## Owned file scope
### HQ repo: /home/ubuntu/forseti.life/copilot-hq
- scripts/**
- sessions/dev-infra/**
- org-chart/agents/instructions/dev-infra.instructions.md

### Forseti repo: /home/ubuntu/forseti.life
- Any path outside `sites/**` (scripts, tooling, configs)

## How to verify
- Syntax check: `bash -n scripts/<file>.sh`
- Lint all scripts: `bash scripts/lint-scripts.sh` (exit 0 = clean, exit 1 = issues found with file:line report)
- QA suite validation: `python3 scripts/qa-suite-validate.py`
- Python syntax: `python3 -m py_compile scripts/<file>.py`
- Pre-commit hook: installed at `.git/hooks/pre-commit` — runs `lint-scripts.sh` automatically on every `git commit` in HQ repo (blocks commit if issues found)
- Release signoff check: `bash scripts/release-signoff-status.sh <release-id>` (exit 0 = all PM signoffs recorded = safe to dispatch post-ship items)

## Inbox close policy (required — implemented 2026-04-10, commit `0606cabc1`)
When `agent-exec-next.sh` writes an outbox artifact with `Status: done`, it prepends
`- Status: done` and `- Completed: <timestamp>` to the source `command.md` (Option A stamp).
The orchestrator (`orchestrator/run.py`) skips any inbox item dir whose `command.md`
contains `^- Status: done` in `_agent_inbox_count`, `_prioritized_agents`, and
`_oldest_unresolved_inbox_seconds`. `ceo-system-health.sh` dead-letter check also skips
items with either the stamp or a matching done outbox entry.

## Recent script changes (reference)
- `scripts/agent-exec-next.sh` — inbox close stamp (commit `0606cabc1`): prepends `- Status: done` + `- Completed:` to `command.md` after done outbox is written
- `orchestrator/run.py` — inbox close guard (commit `0606cabc1`): `_is_inbox_item_done()` helper; all inbox enumeration skips done-stamped items
- `scripts/ceo-system-health.sh` — dead-letter correlation fix (commit `0606cabc1`): skips items with done stamp or matching done outbox entry
- `scripts/ceo-system-health.sh` — orchestrator PID path fix (commit `ab26b18cd`): checks `.orchestrator-loop.pid` not `tmp/orchestrator.pid`
- `scripts/suggestion-intake.sh` — cross-site attribution warning (commit `07c0bfa8f`): detects cross-site keywords in suggestion triage stubs using `product-teams.json`; co-hosted teams excluded to prevent false positives

## Improvement round behavior
- If dispatched with TOPIC `improvement-round-YYYYMMDD-<release>`, first verify release shipped: `bash scripts/release-signoff-status.sh <YYYYMMDD-release>`.
- If exit non-zero → fast-exit with `Status: done`, note release not yet shipped, no gap review possible.
- If exit 0 → proceed with gap review scoped to dev-infra owned scripts/infra changes from that release cycle.

## Synthetic release fast-exit (required)
Inbox item folder names that do NOT start with `YYYYMMDD-improvement-round-` (8 digits then `-improvement-round-`) are synthetic/malformed items dispatched before input validation was added (commits `efe28332`, `977372dc`). Examples: `fake-no-signoff-release-improvement-round`, `fake-no-signoff-release-id-improvement-round`, `stale-test-release-id-999-improvement-round`, `--help-improvement-round`.

Rule: immediately fast-exit with `Status: done` — no gap review work is warranted. Log a one-line note that the item is synthetic and validation now prevents recurrence.

## Executor failure handling
- When `scripts/agent-exec-next.sh` fails to get a valid status-header response, it retries 2× (30s backoff) before writing a failure record to `tmp/executor-failures/<timestamp>-<agent-id>.md` and exiting 0 (inbox preserved).
- If `tmp/executor-failures/` accumulates ≥3 entries in 1 hour, `scripts/release-kpi-monitor.py` flags `EXECUTOR-FAIL` (systemic executor failure).
- To triage executor failures: `ls tmp/executor-failures/ | tail -10`; each file contains the agent ID, inbox item, retry count, and raw response snippet.
- Do NOT manually write stub outboxes for failed executor runs — the stagnation detector needs the failure records to surface the signal correctly.

## Default mode
- If your inbox is empty, do NOT generate your own work items.
- If your inbox is empty, do NOT self-initiate an improvement round or review/refactor pass. Write an outbox status ("inbox empty, awaiting dispatch") and stop. Improvement rounds must be explicitly dispatched via an inbox item from your PM supervisor or the CEO (directive 2026-04-06).
- If you need prioritization or acceptance criteria, escalate to `pm-infra` with `Status: needs-info` and an ROI estimate.

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- If blocked by missing environment context or required access, set `Status: needs-info`/`blocked` and escalate to your supervisor with evidence and an ROI estimate.

## Supervisor
- Supervisor: `pm-infra`
