# Orchestration — Single Master Loop

## Scope

Orchestration is the automated loop that:
- keeps every agent's inbox draining by executing one item per agent per tick
- manages the release cycle lifecycle (start, advance, signoff detection)
- monitors KPI stagnation and routes escalations to the CEO for GenAI action
- publishes telemetry to the Drupal dashboard

For release cycle stages and handoff rules, see `runbooks/release-cycle-process-flow.md`.
For shipping gates, see `runbooks/shipping-gates.md`.

## Architecture

One process runs everything. Legacy split processes (`ceo-inbox-loop`, `inbox-loop`,
`ceo-health-loop`, `2-ceo-opsloop`) have been eliminated.

```
orchestrator-loop.sh  (background daemon, every 60s)
  └─ python3 orchestrator/run.py --once  [tick, file loaded fresh each run]
       ├─ 1. consume_replies        scripts/consume-forseti-replies.sh
       │      Board/Drupal replies → agent inboxes
       │
       ├─ 2. dispatch_commands      inbox/commands/*.md routing
       │      ├─ has pm: field      → scripts/dispatch-pm-request.sh <pm>
       │      ├─ has work_item: + matching features/<wi>/feature.md → PM owner lookup
       │      └─ anything else      → sessions/<active-ceo-seat>/inbox/<item>/ (CEO GenAI triage)
       │
       ├─ 3. release_cycle  ────────────────────────────────────────── interval-gated (5 min)
       │      for each team (coordinated_release_default=true):
       │        no active cycle?  → scripts/release-cycle-start.sh <team> <cur> <next>
       │        signed off?       → advance (next→current, gen new next, restart)
       │        active?           → no-op
       │
       ├─ 4. pick_agents            all agents with inbox, CEO first (level=500)
       │
       ├─ 5. exec_agents            scripts/agent-exec-next.sh <agent>  [GenAI call]
       │      ├─ local LLM (llm/runner.py) if model assigned in llm/routing.yaml
     │      ├─ selected backend for Copilot-routed seats:
     │      │    - Copilot CLI (`copilot --resume ...`) when available/default
     │      │    - Bedrock assistant wrapper (`scripts/bedrock-assist.sh`) when configured
       │      └─ on blocked/needs-info → creates sessions/<supervisor>/inbox/<escalation>/
       │           └─ if supervisor=board → inbox/commands/ via ceo-queue.sh
       │
       ├─ 6. health_check           scripts/hq-status.sh + scripts/hq-blockers.sh
       │      stalled agents → auto re-exec (2-min cooldown)
       │
       ├─ 7. kpi_monitor ───────────────────────────────────────────── interval-gated (5 min)
       │      scripts/release-kpi-monitor.py --auto-remediate
       │      stagnation detected → ceo-queue.sh → inbox/commands/ → CEO inbox
       │
       └─ 8. publish                scripts/publish-forseti-agent-tracker.sh
              → Drupal copilot_agent_tracker tables + "Todo for Keith" dashboard

auto-checkpoint-loop                (independent daemon, every 2h)
improvement-round-loop              (independent daemon, PM+CEO process review dispatch)
```

## Release cycle trigger path

```
orchestrator release_cycle step (every 5 min)
  └─ scripts/release-cycle-start.sh <team_id> <current_release_id> <next_release_id>
       ├─ sessions/qa-<team>/inbox/<preflight-item>/    ← QA Gate 1 (test suite review)
       └─ sessions/pm-<team>/inbox/<groom-next-item>/   ← PM grooms next release in parallel

→ orchestrator picks these up on next tick → exec_agents runs qa-<team> and pm-<team>

QA → Dev → QA repair loop (Stages 3-4)
  └─ Dev fixes → QA re-verifies → PM signs off
       └─ scripts/release-signoff.sh <team> <release-id>
            → sessions/pm-<team>/artifacts/release-signoffs/<id>.md

PM signoff marks the release ready, but runtime stays on that release until:
  └─ pm-forseti completes coordinated push
        └─ scripts/post-coordinated-push.sh
             → advances release_id to next_release_id

After post-push + post-release QA, CEO runs:
  └─ python3 scripts/project-progress-audit.py
       └─ verifies every active `PROJ-*` on `dashboards/PROJECTS.md` has
          last scoped release, next step, queue status, and is within the
          7-day progression SLA
```

## Scheduling rules

- CEO agents: level=500 (highest weight), run first when inbox is non-empty
- PM agents: level=400
- Dev/QA/BA/Sec: levels 100–300
- Paused agents skipped (`scripts/is-agent-paused.sh`)
- Agents with empty inbox skipped
- Within same level: sorted by top effective ROI (base ROI + org priority bonus)
- `--agent-cap` (default 6): max agents executed per tick

## CEO escalation path

When an agent is blocked 3 times on the same item:
1. `scripts/agent-exec-next.sh` calls `scripts/supervisor-for.sh <agent>` to find supervisor
2. Creates `sessions/<supervisor>/inbox/<escalation>/`
3. If supervisor=`board` → `ceo-queue.sh` writes to `inbox/commands/` → `dispatch_commands` routes to CEO inbox
4. CEO (GenAI) picks it up next tick, actions what it can, writes to `sessions/<active-ceo-seat>/inbox/<needs-board>/` for Board items requiring human action
5. `publish-forseti-agent-tracker.sh` surfaces CEO inbox items matching `YYYYMMDD-needs-*` in the "Todo for Keith" dashboard

**Supervisor chain terminator:** `board` returns empty string in `scripts/supervisor-for.sh` — prevents infinite escalation loops.

## Operations

### Start/stop all loops
```bash
./scripts/hq-automation.sh start
./scripts/hq-automation.sh stop
./scripts/hq-automation.sh status
```

### Production runtime verification
Run this after deploy/bootstrap to confirm the live control plane and loops are healthy:
```bash
./scripts/verify-hq-runtime.sh --strict
```

What it verifies:
- org automation is enabled
- orchestrator runtime is active (systemd service or loop wrapper)
- publish + auto-checkpoint loops are running
- release-cycle automation is enabled
- `inbox/responses/orchestrator-latest.log` is fresh

### Pause release-cycle automation
```bash
./scripts/release-cycle-control.sh disable --reason "Pause release automation"
./scripts/release-cycle-control.sh status
```

When paused, the orchestrator still runs, but it skips the `release_cycle` step, coordinated-push release automation, and health-check release dispatchers.

### One-shot tick (debug)
```bash
python3 orchestrator/run.py --once --no-publish
```

### Bedrock assistant (production-safe, grounded)
Use HQ script wrapper to run assistant prompts against live Drupal `ai_conversation` on Bedrock.

```bash
./scripts/bedrock-assist.sh forseti "Summarize production suggestion pipeline health and next actions."
./scripts/bedrock-assist.sh dungeoncrawler "List top 3 release blockers with owner suggestions."
```

Behavior:
- Resolves Drupal root automatically (prefers `/var/www/html/<site>` on production hosts).
- Runs as `www-data` for consistent Drupal permissions.
- Injects the configured Forseti/DungeonCrawler system prompt (`PromptManager->getSystemPrompt(10)`) to keep responses on-domain.
- Uses Bedrock direct invocation with `skip_cache=true` so outputs reflect current runtime state.

Optional overrides:
```bash
BEDROCK_MAX_TOKENS=1200 ./scripts/bedrock-assist.sh forseti "Deep-dive on current release risks."
BEDROCK_OPERATION=prod_ops_assistant ./scripts/bedrock-assist.sh forseti "Generate rollback checklist."
DRUPAL_ROOT=/var/www/html/forseti ./scripts/bedrock-assist.sh forseti "Validate Bedrock path."
```

### LangGraph agent backend selection
`scripts/agent-exec-next.sh` now supports selecting the generative backend for Copilot-routed agents.

```bash
# default behavior: prefer Copilot; fall back to Bedrock if Copilot is unavailable
export HQ_AGENTIC_BACKEND=auto

# force Bedrock for agent execution path (local LLM seats still use local models first)
export HQ_AGENTIC_BACKEND=bedrock

# force Copilot only
export HQ_AGENTIC_BACKEND=copilot
```

Notes:
- Local LLM seats remain unchanged: local model first, then selected backend fallback.
- Bedrock path uses `scripts/bedrock-assist.sh` with site-aware routing and system-prompt grounding.

### Production validation and troubleshooting scripts
Use these scripts after deploy and during incidents:

```bash
# End-to-end Bedrock validation for production runtime
./scripts/validate-production-bedrock.sh forseti

# Gather diagnostics and ask Bedrock assistant for troubleshooting guidance
./scripts/prod-troubleshoot-assistant.sh forseti "Why are suggestions not moving from new to under_review?"

# Same, but allow execution of strict allowlisted commands emitted by assistant
./scripts/prod-troubleshoot-assistant.sh forseti "Investigate runtime drift" --execute-approved

# Non-interactive execution of allowlisted commands only
./scripts/prod-troubleshoot-assistant.sh forseti "Investigate runtime drift" --execute-approved --yes
```

Important:
- Default mode is advisory (no command execution).
- `--execute-approved` runs only commands emitted as `APPROVED_CMD: ...` and only if they match the script allowlist.
- Any command with chaining/pipes/redirection/subshells is blocked.
- For system/file mutations beyond allowlisted checks, use explicit operator workflows.

### Check dashboard "Todo for Keith"
Items appear at: `/admin/reports/waitingonkeith`
CEO inbox items must match `YYYYMMDD-(needs|needs-escalated)-` pattern in the active CEO seat inbox (`sessions/ceo-copilot*/inbox/`).

### Force a release cycle check now
```bash
python3 -c "
import sys; sys.path.insert(0, '.')
from orchestrator.run import _release_cycle_step
log = []
_release_cycle_step(log)
import json; print(json.dumps(log, indent=2))
"
```

### Release cycle state
```bash
ls tmp/release-cycle-active/
cat tmp/release-cycle-active/<team>.release_id
cat tmp/release-cycle-active/<team>.next_release_id
scripts/release-signoff-status.sh <release-id>
```

## Troubleshooting

| Symptom | Check |
|---------|-------|
| No agents executing | `ls sessions/*/inbox/` — confirm non-empty inboxes; check `hq-automation.sh status` |
| Agent repeatedly blocked | `scripts/agent-exec-next.sh <agent>` manually; inspect outbox for error notes |
| CEO inbox not draining | Confirm active CEO seat is not paused (for example: `scripts/is-agent-paused.sh ceo-copilot-2`) |
| "Todo for Keith" empty | Confirm CEO inbox items match `YYYYMMDD-needs-*` pattern; run publish manually |
| Release cycle not starting | Check `tmp/release-cycle-active/` — if missing, release_cycle step will start it on next tick |
| KPI stagnation loop | Check `inbox/commands/` for unprocessed stagnation items; check CEO inbox for actionable items |

## Key file locations

| Purpose | Path |
|---------|------|
| Orchestrator entry point | `orchestrator/run.py` |
| Daemon wrapper | `scripts/orchestrator-loop.sh` |
| Process manager | `scripts/hq-automation.sh` |
| Agent executor | `scripts/agent-exec-next.sh` |
| Supervisor chain | `scripts/supervisor-for.sh` |
| Release cycle start | `scripts/release-cycle-start.sh` |
| KPI monitor | `scripts/release-kpi-monitor.py` |
| Telemetry publisher | `scripts/publish-forseti-agent-tracker.sh` |
| Active release state | `tmp/release-cycle-active/` |
| Org on/off switch | `tmp/org-control.json` / `scripts/is-org-enabled.sh` |

## Pre-merge safety gate (required for workspace snapshot merges)

Workspace snapshot merges can silently delete `sessions/` files for multiple agents,
requiring 3-5 cycles of manual recovery. Three such events have occurred (commits
`7b8d1070`, `557f924f`, `389b604c7`). **Always use `scripts/workspace-merge-safe.sh`
instead of bare `git merge` for any merge that may overwrite session artifacts.**

### Procedure

```bash
# Instead of: git merge <ref>
./scripts/workspace-merge-safe.sh <ref>

# Dry-run (backup + integrity check only, no merge):
./scripts/workspace-merge-safe.sh --dry-run
```

### What the script does

1. **Backup**: copies `sessions/` to `/tmp/workspace-merge-backup-<timestamp>/sessions/`
2. **Pre-merge manifest**: records all file paths; lists unprocessed inbox items
3. **Merge**: runs `git merge --no-edit <ref>`
4. **Integrity check**: diffs pre/post disk state; outputs `WARNING` + `DELETED:` paths
   for any sessions/ files removed by the merge

### Exit codes

- `0` — merge succeeded, no sessions/ files deleted (or `--dry-run`)
- `1` — git merge itself failed; backup preserved at `/tmp/workspace-merge-backup-<ts>/`
- `2` — merge completed but sessions/ files were deleted; backup preserved, restore command printed

### Recovery (if sessions/ files were lost)

```bash
# Restore from most recent backup
cp -r /tmp/workspace-merge-backup-<ts>/sessions/ .
git add -f sessions/ && git commit -m "restore: recover sessions/ files lost in workspace merge"
```

### Pre-merge checklist (manual)

- [ ] Confirm all high-ROI inbox items are processed or content copied elsewhere
- [ ] Run `./scripts/workspace-merge-safe.sh --dry-run` and review unprocessed inbox list
- [ ] Only proceed with merge if unprocessed item count is acceptable
