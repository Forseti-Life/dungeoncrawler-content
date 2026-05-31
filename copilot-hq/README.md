# Copilot Sessions HQ

`copilot-hq` is the **operations and orchestration layer** behind the Forseti autonomous development platform.

It exists to make multi-agent software delivery understandable and repeatable: who owns what, how work moves, how releases are gated, and how runtime automation is started, monitored, and repaired.

If you are new here, start with:

1. `QUICKSTART.md`
2. `runbooks/public-repo-positioning.md`
3. `runbooks/orchestration.md`

## What this repo does

This repository is the canonical control plane for:

- HQ agent execution
- release-cycle orchestration
- role and ownership governance
- dashboards and project tracking
- session/runbook-driven operational continuity
- local-LLM and Bedrock-backed execution paths

## What this repo is not

- It is not the full Forseti product runtime by itself
- It is not a public dump of live operational history
- It is not the only code repo in the platform; product/runtime code also lives under the wider monorepo

## Principles
- **Local sessions are not automatically committed here.** Only add/export what you intend to persist.
- **No secrets.** Sanitize exports before committing.

## Repo layout
- `org-chart/` — organizational model (CEO → departments → delegated sessions)
- `sessions/` — per-session folders (exports, summaries, artifacts)
- `runbooks/` — how we run/close sessions consistently
- `templates/` — standard templates for session summaries and handoffs
- `scripts/` — helper scripts for exporting/sanitizing (optional)
- `orchestrator/` — LangGraph runtime and execution graph
- `dashboards/` — project, release, finance, and integration dashboards
- `llm/` — local model catalog, routing, and download tooling

## Purpose and public positioning

For a clearer public-facing explanation of why this repo exists and how it relates to `forseti.life`, read:

- `runbooks/public-repo-positioning.md`
- `runbooks/private-public-dual-repo.md`

## Quickstart

For a practical first-read path, startup script map, and full-stack orientation, read:

- `QUICKSTART.md`

## Orchestration
See `runbooks/orchestration.md` for the current end-to-end process flow (LangGraph orchestrator + systemd runtime + publishing).

Production-master to local-worker control-plane:

- `runbooks/production-master-dev-worker.md` — protocol, routing rules, mermaid diagrams, and implementation plan for production-dispatched local development work.

## Technology stack
See `runbooks/technology-stack.md` for a full map of the agentic system stack (queues, executors, local LLM layer, publishing, control plane, and observability).

## Documentation index
### Process flows
- `runbooks/release-cycle-process-flow.md` — release-cycle stages and progression rules.
- `runbooks/product-team-onboarding.md` — standard onboarding process for adding new product teams.
- `runbooks/orchestration.md` — end-to-end orchestration process.
- `runbooks/session-lifecycle.md` — how sessions are started, managed, and closed.
- `runbooks/session-monitoring.md` — session health and monitoring workflow.
- `runbooks/coordinated-release.md` — coordinated release execution model.

### Dashboards
- `dashboards/FEATURE_PROGRESS.md` — feature progress dashboard definitions and usage.
- `dashboards/SESSION_MONITORING.md` — session monitoring dashboard definitions and usage.

## Monitoring + control path
The org automation control path is deterministic at the control layer and agentic at the troubleshooting layer.

### 1) Source of truth: org enable/disable
- State toggle: `scripts/org-control.sh`
- State read gate: `scripts/is-org-enabled.sh`
- State file default: `/var/tmp/copilot-sessions-hq/org-control.json` (legacy fallback supported)

### 1b) Source of truth: release-cycle enable/disable
- State toggle: `scripts/release-cycle-control.sh`
- State read gate: `scripts/is-release-cycle-enabled.sh`
- State file default: `/var/tmp/copilot-sessions-hq/release-cycle-control.json` (legacy fallback supported)

### 2) Process convergence (start/stop loops)
- Converger: `scripts/hq-automation.sh converge`
- Behavior:
	- enabled=true → starts required loops
	- enabled=false → stops required loops

### 3) Watchdog enforcement
- Watchdog runner: `scripts/hq-automation-watchdog.sh`
- Installed by: `scripts/install-cron-hq-automation.sh`
- Cadence:
	- `@reboot` converge
	- every minute watchdog converge

### 4) Runtime loops and cadence
- `scripts/orchestrator-loop.sh` — every 60s (primary LangGraph execution loop)
- `scripts/auto-checkpoint-loop.sh` — every 7200s
- `scripts/site-audit-loop.sh` — every 300s (optional, only when enabled)
- `scripts/hq-automation-watchdog.sh` — every minute via cron (convergence + suggestion intake)

Legacy loops (`ceo-inbox-loop`, `inbox-loop`, `ceo-health-loop`, `2-ceo-opsloop`) are not part of the default production runtime and should remain stopped.

## Production setup essentials
- Deploy and start runtime using `.github/workflows/deploy.yml` (branch: `master`).
- Production deploy uses a full repository checkout at `$HOME/forseti.life` by default (override with `REPO_DEPLOY_DIR`), and runs HQ from `$REPO_DEPLOY_DIR/copilot-hq` (override with `HQ_DEPLOY_DIR`).
- Deploy workflow behavior is idempotent: first deploy runs `scripts/setup.sh`; existing deploys run `scripts/verify-hq-runtime.sh --strict` and auto-run `scripts/setup.sh` only if verification fails.
- Run `./scripts/verify-hq-runtime.sh --strict` after deploy.
- Select agentic backend via `HQ_AGENTIC_BACKEND`:
	- `auto` (default): prefer chat-capable Copilot CLI (`--resume` support); fallback to Bedrock assistant script when Copilot is missing or incompatible
	- `copilot`: require chat-capable Copilot CLI (`--resume` support)
	- `bedrock`: require Bedrock assistant script
- Ensure `scripts/bedrock-assist.sh` is executable if using Bedrock path.
- `scripts/1-copilot.sh` supports production fallback via `COPILOT_BEDROCK_FALLBACK=1` (default) when Copilot CLI is missing/incompatible.
- `scripts/1-copilot.sh` now defaults Bedrock fallback to `scripts/hq-bedrock-chat.sh`, which injects internal HQ/CEO instructions context before model invocation.
- Bedrock fallback chat continuity is enabled by default in `scripts/1-copilot.sh` using a dedicated rolling memory file per session and passing it to `scripts/hq-bedrock-chat.sh` (`HQ_BEDROCK_HISTORY_FILE`, `HQ_BEDROCK_HISTORY_LINES`).
- In Bedrock fallback mode, persisted transcript/memory entries now strip wrapper banner/debug lines (for example `[hq-bedrock-chat]` and `[bedrock-assist]`) to keep saved session history clean.
- Validate org state with `./scripts/org-control.sh status --one-line` and runtime state with `./scripts/hq-automation.sh status`.

## How incidents are handled
### Deterministic control-plane recovery
- If loops drift from desired state, watchdog runs converge and repairs start/stop state.
- If org is disabled, loops either stop or skip work at each cycle gate.
- If release-cycle automation is disabled, orchestrator skips release-cycle advancement, coordinated-push release automation, and release-support dispatchers while the rest of HQ stays online.

### Agentic troubleshooting path
- For stalls/uncertain states, health checks and queue loops generate actionable inbox work items.
- Orchestrator/executor then runs agent seats to investigate, diagnose, and propose or apply fixes.
- In short: control-plane recovery is rule-based; root-cause troubleshooting is handled by agentic execution.

## Operations quick-check
- Snapshot: `./scripts/hq-status.sh`
- Org state: `./scripts/org-control.sh status --one-line`
- Force converge: `./scripts/hq-automation.sh converge`
- Watchdog log: `inbox/responses/hq-automation-watchdog.log`

### Production research scripts
- `./scripts/prod-assumptions-audit.sh [hq_dir]` — host context, runtime assumptions, release-cycle files, cron, and recent logs.
- `./scripts/prod-writeability-check.sh [hq_dir]` — write-permission checks for HQ runtime directories (current user + optional `www-data`).
- `./scripts/prod-release-cycle-flow-check.sh [hq_dir]` — validates release-cycle paths/state and runs one dry release-cycle step.
- If `hq_dir` is omitted, scripts default to `HQ_DEPLOY_DIR` or `${REPO_DEPLOY_DIR:-$HOME/forseti.life}/copilot-hq`.

## Preparing for public release
Start with `PUBLIC_REPO_PREP.md` for a staged publication checklist (security scrub, history review, docs/legal, and release steps).

Public metadata and policies:
- `LICENSE`
- `CODE_OF_CONDUCT.md`
- `CONTRIBUTING.md`
- `SECURITY.md`

Current release-prep artifacts:
- `runbooks/publication-readiness-20260308.md`
- `runbooks/history-secret-scan-20260308.txt`
- `runbooks/public-release-gate-20260308.md`

For a clear public-facing explanation of value proposition, purpose, and platform boundaries with `forseti.life`, see:
- `runbooks/public-repo-positioning.md`

For a practical private/public split, see:
- `runbooks/private-public-dual-repo.md`

Automation scripts:
- `scripts/setup-public-mirror.sh` (one-time mirror setup)
- `scripts/export-public-mirror.sh` (repeatable private -> public sync)
