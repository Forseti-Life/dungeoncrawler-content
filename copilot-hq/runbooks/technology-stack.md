# Agentic System Technology Stack

This document describes the full end-to-end technology stack for the HQ agentic system: how work is represented, how it is executed, what runs on timers, what state exists, and how we observe/operate it.

## System overview
The system is a set of cooperating layers:

1) **Work model (filesystem queues)**
- Inbox items live on disk under `sessions/<agent-id>/inbox/<item-id>/`
- Agent responses live under `sessions/<agent-id>/outbox/<item-id>.md`
- Optional evidence and outputs live under `sessions/<agent-id>/artifacts/...`

2) **Schedulers/orchestrators (pick what runs next)**
- Python LangGraph orchestrator: `orchestrator/run.py`
- Shell loop wrapper: `scripts/orchestrator-loop.sh`
- Legacy shell executor loop: `scripts/agent-exec-loop.sh` (kept OFF by default)

3) **Executor pipeline (runs exactly one unit of work for one agent)**
- Entry point: `scripts/agent-exec-next.sh <agent-id>`
- Uses locks to ensure “one agent, one item at a time”
- Reads the agent’s instruction stack and the next inbox item, executes via runtime provider (local LLM, Copilot CLI, or Bedrock wrapper), and writes outbox/artifacts.

4) **Publishing/visibility (dashboards)**
- Publisher: `scripts/publish-forseti-agent-tracker.sh`
- Runtime owner: orchestrator publish step
- Legacy loop wrapper (disabled in normal operation): `scripts/publish-forseti-agent-tracker-loop.sh`
- Destination: Drupal custom module (tracker dashboards)

5) **Quality automation (QA audits and routing findings)**
- Site audit runner: `scripts/site-audit-run.sh`
- Produces artifacts under QA seats and dispatches inbox items based on findings.

6) **Operational control plane (on/off, convergence, watchdogs)**
- Org kill-switch state file: `tmp/org-control.json` (gitignored)
- CLI control: `scripts/org-control.sh`
- Converge loop set: `scripts/hq-automation.sh`
- 1-minute watchdog: `scripts/hq-automation-watchdog.sh` (+ cron installer)

## Repositories and major components

### HQ repo (this repo)
Primary responsibilities:
- Owns the org model, instruction stack, and filesystem queues.
- Runs the schedulers/executors.
- Stores audit trails (sessions/outbox/artifacts).

Key directories:
- `org-chart/` — org structure, roles, seats, scopes, site instructions
- `sessions/` — per-seat inbox/outbox/artifacts (the source of truth)
- `runbooks/` — operations + process documentation
- `scripts/` — orchestration, exec pipeline, watchdogs, publishing
- `orchestrator/` — LangGraph scheduling logic
- `llm/` — local LLM routing + inference runner

### Drupal (Forseti dashboards)
Primary responsibilities:
- Renders the “Waiting on Keith” dashboard and per-agent views.
- Hosts the UI control for org automation (writes org-control state via HQ script).
- Stores published agent metadata and recent activity.

HQ-to-Drupal interface:
- `scripts/publish-forseti-agent-tracker.sh` posts metadata/state to Drupal.

## Work representation (filesystem queue model)

### Inbox item
Path:
- `sessions/<agent-id>/inbox/<item-id>/`

Typical contents:
- `command.md` — the task payload
- `roi.txt` — integer ROI used for ordering

### Outbox reply
Path:
- `sessions/<agent-id>/outbox/<item-id>.md`

Contract:
- The first lines follow the org status template (Status + Summary), then Next actions / Blockers / Needs.

### Artifacts
Path:
- `sessions/<agent-id>/artifacts/...`

Used for:
- QA evidence, release candidates, audit outputs, and other durable outputs.

## Orchestration / scheduling layer

### LangGraph orchestrator
Code:
- `orchestrator/run.py`

Behavior:
- On each tick, selects eligible agents using role weight + inbox ROI heuristics.
- Executes a bounded number of seats per tick (`--agent-cap`, CEO included).
- Delegates one unit of work per selected seat by calling `scripts/agent-exec-next.sh`.
- Publishing can be enabled/disabled (`--no-publish`).

Runtimes:
- Recommended unattended: systemd user service (see `runbooks/orchestration.md`).
- Also supported: `scripts/orchestrator-loop.sh` background loop.

### Legacy executor loop
Script:
- `scripts/agent-exec-loop.sh`

Notes:
- Kept for compatibility and ad-hoc use.
- Competes for the same locks as the orchestrator; do not run concurrently unless explicitly desired.

## Execution layer (agent runner)

Entry point:
- `scripts/agent-exec-next.sh <agent-id>`

What it does:
- Determines the next inbox item for the seat.
- Loads the seat’s instruction stack (org-wide → role → site → seat).
- Selects a runtime provider:
  - local LLM (if configured and model available)
  - otherwise selected backend for Copilot-routed seats:
    - Copilot CLI (default/auto when available)
    - Bedrock via `scripts/bedrock-assist.sh` (when `HQ_AGENTIC_BACKEND=bedrock` or Copilot is unavailable in `auto` mode)
- Writes the outbox reply and any artifacts.

## Local LLM layer
Directory:
- `llm/`

Key files:
- `llm/routing.yaml` — role/agent → model mapping
- `llm/runner.py` — inference shim
- `llm/model-manifest.yaml` — model catalog

Dependencies:
- `llama-cpp-python` for CPU inference
- `huggingface_hub` for downloads
- `pyyaml` for config

Fallback:
- If a model is assigned but missing, the executor falls back to the selected backend (`HQ_AGENTIC_BACKEND`: `auto|copilot|bedrock`).

## Quality automation (QA audits)

Runner:
- `scripts/site-audit-run.sh`

What it does:
- Crawls/validates a site (local/dev by default).
- Produces artifacts under the QA seat.
- Classifies findings and dispatches inbox items to Dev/PM as needed.

Safety defaults:
- Production auditing is blocked unless explicitly enabled (`ALLOW_PROD_QA=1`).

Org control:
- `site-audit-run.sh` respects `tmp/org-control.json` via `scripts/is-org-enabled.sh` (it exits early if org automation is disabled).

## Publishing / dashboards

Publisher:
- `scripts/publish-forseti-agent-tracker.sh`

Runtime owner:
- orchestrator publish step

Legacy loop wrapper:
- `scripts/publish-forseti-agent-tracker-loop.sh` (disabled in normal operation)

Purpose:
- Publishes HQ agent state, inbox summary counts, and key artifacts/evidence pointers to Drupal for monitoring.

## Control plane: enable/disable that actually converges

### Org kill-switch
State file:
- `tmp/org-control.json` (gitignored)

CLI:
- `scripts/org-control.sh status|enable|disable`

Semantics:
- `enabled=false` means all automation loops should stop, and all remaining long-running processes should “idle” (do no work) if they remain alive.
- `enabled=true` means loops should be running and executing.

### Convergence
Converge script:
- `scripts/hq-automation.sh converge`

Watchdog:
- `scripts/hq-automation-watchdog.sh` (intended every minute via cron)

Design goal:
- “Enable” is not just a flag: it converges process state.
- “Disable” reliably stops work even if something restarts (watchdogs respect org enabled state).

Web safety:
- If `org-control.sh` is invoked by a web user (`www-data`), it defaults to not spawning loops; cron/systemd should do convergence.

## Observability

### Status command
- `scripts/hq-status.sh` prints:
  - org enabled flag
  - loop pid statuses
  - queue counts
- repo merge/integration health (`MERGE_HEAD`, rebase/cherry-pick/revert state, unmerged files, dirty tracked changes)
  - per-agent inbox/exec/last activity snapshot

### Logs
- `inbox/responses/` contains “latest” logs and date-stamped logs for loops:
  - `orchestrator-*.log`, `agent-exec-*.log`, `ceo-health-*.log`, publisher logs, etc.

## Operational norms

### Single executor rule
Default configuration prefers a single executor:
- `orchestrator-loop` is the default.
- `agent-exec-loop` is treated as legacy and stopped by converge unless explicitly enabled.

### Cron vs systemd
We can run unattended via:
- **systemd user service** for orchestrator (preferred), and/or
- **cron** watchdogs for persistence and restart.

The convergence watchdog exists to reduce “flag enabled but nothing running” and “flag disabled but something still executing” failure modes.
