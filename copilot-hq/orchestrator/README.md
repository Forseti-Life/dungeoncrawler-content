# orchestrator/ — Consolidated HQ Orchestrator

The single master loop for all HQ automation. Replaces the legacy split of
`ceo-inbox-loop`, `inbox-loop`, `ceo-health-loop`, and `2-ceo-opsloop`.

Uses the HQ filesystem queue model as its source of truth:

- `sessions/<agent>/inbox/<item-id>/...`
- `sessions/<agent>/outbox/<item-id>.md`
- execution pipeline: `scripts/agent-exec-next.sh <agent>`

## Why this exists

- One process instead of five: eliminates duplicated loop management and race conditions.
- CEO included in scheduling (level=500, highest priority) — the CEO is a GenAI participant, not a separate special-cased loop.
- `dispatch_commands` routes `inbox/commands/` items to PM inboxes or CEO inbox for GenAI triage.
- `release_cycle` step automatically starts and advances release cycles — no human needed to kick off new cycles.
- LangGraph state graph is the authoritative tick engine; `orchestrator/run.py` wires repository-specific dependencies into that graph.

## Tick pipeline (in order)

1. **consume_replies** — pull Board/Drupal replies into agent inboxes (`scripts/consume-forseti-replies.sh`)
2. **dispatch_commands** — route `inbox/commands/*.md` to PM inboxes or CEO inbox
3. **release_cycle** *(interval-gated, default 5 min)* — ensure each team has an active release cycle; start or advance as needed
4. **pick_agents** — all agents with inbox items, CEO first (level=500), sorted by role weight + ROI
5. **exec_agents** — `scripts/agent-exec-next.sh <agent>` per picked agent (GenAI call)
6. **health_check** — detect stalled agents, auto-remediate with 2-min cooldown
7. **kpi_monitor** *(interval-gated, default 5 min)* — `scripts/release-kpi-monitor.py --auto-remediate`
8. **publish** — `scripts/publish-forseti-agent-tracker.sh` → Drupal dashboard telemetry

## Run (managed — normal operation)

```bash
./scripts/hq-automation.sh start    # starts orchestrator-loop in background
./scripts/hq-automation.sh status   # check running processes
./scripts/hq-automation.sh stop     # stop all loops
```

## Run (single tick — debug/test)

```bash
python3 orchestrator/run.py --once --no-publish
```

## Run (loop — manual)

```bash
./scripts/orchestrator-loop.sh start 60
./scripts/orchestrator-loop.sh status
```

## Key CLI flags

| Flag | Default | Purpose |
|------|---------|---------|
| `--agent-cap N` | 6 | Max agents executed per tick |
| `--kpi-interval N` | 300 | Seconds between KPI monitor runs |
| `--release-cycle-interval N` | 300 | Seconds between release cycle checks |
| `--no-publish` | off | Skip Drupal publish step |
| `--once` | off | Run one tick and exit |

## Release cycle automation

The `release_cycle` step manages the full cycle lifecycle automatically:

- **No active cycle** → calls `scripts/release-cycle-start.sh <team> <current-id> <next-id>`
  - creates QA preflight inbox item in `sessions/qa-<team>/inbox/`
  - creates PM grooming inbox item in `sessions/pm-<team>/inbox/` (next release, parallel)
  - records IDs in `tmp/release-cycle-active/<team>.release_id` and `.next_release_id`
- **Cycle signed off** → detects `sessions/pm-<team>/artifacts/release-signoffs/<id>.md`, advances next→current
- **Cycle active** → no-op (idempotent; `release-cycle-start.sh` also guards against double-start)

## Runtime agent provider

Default provider is `shell` which delegates to `scripts/agent-exec-next.sh`.

Preserved behaviors:
- per-agent session handling + inbox locking
- local LLM routing (`llm/routing.yaml` + `llm/runner.py`)
- Copilot CLI fallback (`gh copilot --resume`)
- stale detached Copilot worker reaping during `health_check`
- ROI bump behavior on completion

A placeholder `cline` provider exists as a future hook.

## Notes

- The orchestrator does **not** create arbitrary new work items — work creation flows through CEO/PM GenAI calls or the release cycle step.
- Dashboard publishing is handled by the orchestrator tick; do not run a separate publisher loop in normal operation.
