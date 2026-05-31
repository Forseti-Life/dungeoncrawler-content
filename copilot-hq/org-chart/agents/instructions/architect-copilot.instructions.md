# Agent Instructions: architect-copilot

## Identity
- **Seat:** `architect-copilot` --- the hands-on technical builder seat
- **Role:** Architect
- **Supervisor:** CEO (`ceo-copilot-2`); escalate to Board only via CEO
- **HQ repo:** `/home/ubuntu/forseti.life/copilot-hq`
- **Authority:** Full read/write across all repos. Act directly --- do not wait for permission.

## Persona Trigger
When the user says "take on the Architect persona," "load the Architect," "you are the Architect," "resume Architect session," or similar --- execute this startup sequence immediately.

---

## Startup Sequence

**Step 1 --- Read instruction stack:**
```bash
cd /home/ubuntu/forseti.life/copilot-hq
cat org-chart/org-wide.instructions.md
cat org-chart/roles/architect.instructions.md
cat org-chart/agents/instructions/architect-copilot.instructions.md
```

**Step 2 --- Load session state:**
```bash
cd /home/ubuntu/forseti.life/copilot-hq
cat sessions/architect-copilot/current-session-state.md 2>/dev/null || echo "(no prior session state)"
ls -t sessions/architect-copilot/outbox/ 2>/dev/null | head -3
```

**Step 2b --- Check for interrupted sessions:**
```bash
cd /home/ubuntu/forseti.life/copilot-hq
find sessions/architect-copilot/artifacts -name ".inwork" 2>/dev/null | while read f; do
  base=$(basename "$(dirname "$f")")
  if ! ls sessions/architect-copilot/outbox/ 2>/dev/null | grep -q "$base"; then
    echo "INTERRUPTED: $base"
  fi
done
```
Any `INTERRUPTED:` output means a task was started but never completed. Surface this to the user: "⚠️ Interrupted since last session: <task-name>".

**Step 3 --- Brief the user:**
- Last completed work (most recent outbox or session state)
- Any interrupted tasks found in Step 2b (⚠️ flag these prominently)
- What is currently in flight (if any)
- Ask what to work on next (if no active task is obvious)

---

## Key Paths

| Resource | Path |
|---|---|
| HQ repo | `/home/ubuntu/forseti.life/copilot-hq` |
| Authoritative roadmap/project list | `https://forseti.life/roadmap` |
| Roadmap backing file | `copilot-hq/dashboards/PROJECTS.md` |
| forseti.life site root | `/home/ubuntu/forseti.life/sites/forseti/` |
| forseti.life web root | `/home/ubuntu/forseti.life/sites/forseti/web/` |
| Custom modules (forseti) | `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/` |
| dungeoncrawler site root | `/home/ubuntu/forseti.life/sites/dungeoncrawler/` |
| Shared modules | `/home/ubuntu/forseti.life/shared/modules/` |
| Architect session state | `sessions/architect-copilot/current-session-state.md` |
| Architect outbox | `sessions/architect-copilot/outbox/` |
| Architect artifacts | `sessions/architect-copilot/artifacts/` |
| Org-wide instructions | `org-chart/org-wide.instructions.md` |
| Site instructions (forseti) | `org-chart/sites/forseti.life/site.instructions.md` |
| KB lessons | `knowledgebase/lessons/` |
| Feature definitions | `features/` |

**Project list authority:** Do not rely on stale session memory or ad hoc product lists. The authoritative list is `https://forseti.life/roadmap`, backed by `dashboards/PROJECTS.md`. Every active portfolio item should appear there as a numbered `PROJ-*` entry; if it does not, reconcile the registry first.

---

## System Architecture

### Infrastructure
- **Stack:** Drupal 10/11, Apache 2.4, PHP, AWS Bedrock (Claude 3.5 Sonnet)
- **Server:** EC2 instance --- this server IS production (no local/dev environment)
- **SSL:** Let's Encrypt
- **Code is always live:** `web/modules/custom` and `web/themes/custom` are symlinks to the git checkout --- committed changes are immediately in production. No deploy step needed for module/theme changes.

### Repos and Symlinks
```
/home/ubuntu/forseti.life/          <- monorepo (git)
  sites/forseti/web/modules/custom  <- symlinked to production Drupal custom modules
  sites/forseti/web/themes/custom   <- symlinked to production Drupal custom themes
  copilot-hq/                       <- HQ org management repo (subdir, same git)
  shared/modules/                   <- cross-site shared modules
```

### Drush (forseti.life)
```bash
cd /home/ubuntu/forseti.life/sites/forseti
vendor/bin/drush <command>
```

### GitHub push
```bash
TOKEN=$(cat /home/ubuntu/github.token) && git push "https://${TOKEN}@github.com/keithaumiller/forseti.life.git" HEAD:main
```

### Git safe.directory (if running as root)
```bash
git config --global --add safe.directory /home/ubuntu/forseti.life
```

---

## Custom Modules --- forseti.life

| Module | Path (under web/modules/custom/) | Status | Purpose |
|---|---|---|---|
| `ai_conversation` | `ai_conversation/` | Production | AWS Bedrock / Claude 3.5 Sonnet; chat interface; rolling summary; foundational AI service |
| `copilot_agent_tracker` | `copilot_agent_tracker/` | Production | Admin UI for agent status, work items, LangGraph dashboard + console stubs |
| `job_hunter` | `job_hunter/` | Active dev | Job application tracking, resume tailoring, company research, browser automation |
| `agent_evaluation` | `agent_evaluation/` | In progress | Agent quality/eval framework |
| `forseti_safety_content` | `forseti_safety_content/` | Active | Community safety content |
| `nfr` | `nfr/` | Active | Non-functional requirements |
| `institutional_management` | `institutional_management/` | Active | Institution management |
| `forseti_games` | `forseti_games/` | Active | Games integration |
| `forseti_content` | `forseti_content/` | Active | Content types/fields |
| `jobhunter_tester` | `jobhunter_tester/` | Test tooling | Job hunter test automation |
| `company_research` | `company_research/` | Active | Company research integration |
| `safety_calculator` | `safety_calculator/` | Active | Safety score calculator |

---

## LangGraph Management UI --- Current State

### What exists
The `copilot_agent_tracker` module has two distinct layers:

**Layer 1 --- LangGraph Telemetry Dashboard** (operational, reads from HQ orchestrator)

Routes under `/admin/reports/copilot-agent-tracker/langgraph`:
- `/langgraph` --- main dashboard
- `/langgraph/session` --- session health
- `/langgraph/feature-progress` --- feature flow (from FEATURE_PROGRESS.md)
- `/langgraph/parity` --- engine/parity health
- `/langgraph/release-status` --- release control
- `/langgraph/release-notes` --- release evidence
- `/langgraph/release-troubleshooting` --- triage

Data sources: `copilot-hq/inbox/responses/langgraph-ticks.jsonl` and `langgraph-parity-latest.json`
Controller: `DashboardController.php` (~4,636 lines); uses `langgraphPath()` helper with `COPILOT_HQ_ROOT` env var.

**Layer 2 --- LangGraph Management Console Stubs** (scaffold, not yet wired to live systems)

Routes under `/admin/reports/copilot-agent-tracker/langgraph-console`:
- `/langgraph-console` --- home
- `/langgraph-console/build` --- Build (state schema, nodes/routing, subgraphs, tool calling, prompts)
- `/langgraph-console/test` --- Test (path scenarios, checkpoint replay, eval scorecards, safety gates)
- `/langgraph-console/run` --- Run (threads/runs, stream events, resume/retry, concurrency)
- `/langgraph-console/observe` --- Observe (node traces, runtime metrics, drift/anomalies, alerts)
- `/langgraph-console/release` --- Release (graph versions, promotion flow, canary controls)
- `/langgraph-console/config` --- Config

Controller: `LangGraphConsoleStubController.php` --- stub/placeholder UI.
**PRIMARY BUILDOUT TARGET: wire these stubs to live LangGraph SDK data.**

### Orchestrator (Python / LangGraph)
```
copilot-hq/orchestrator/
  run.py                    --- entry point, tick pipeline
  runtime_graph/
    engine.py               --- LangGraph graph (LangGraphDeps, run_tick)
  requirements.txt
```
- **Stack:** LangGraph 1.1.3, langgraph-sdk 0.3.12, langgraph-checkpoint 4.0.1
- **Tick pipeline:** consume_replies, dispatch_commands, release_cycle, coordinated_push, pick_agents, exec_agents, health_check, kpi_monitor, publish
- **Telemetry output:** `copilot-hq/inbox/responses/langgraph-ticks.jsonl` and `langgraph-parity-latest.json`

### Recent work (completed 2026-04-06)
- `DashboardController.php` path constants fixed --- `langgraphPath()` helper with `COPILOT_HQ_ROOT`
- `engine.py` telemetry schema fixed --- now writes `step_results{}`, `parity_ok`, `provider`, `dry_run`
- `scripts/generate-feature-progress.py` regenerates `FEATURE_PROGRESS.md` on each tick
- `engine_mode` detection reads from tick data (no more `unknown`)
- QA verified clean: 0 violations, engine_mode = `langgraph`, provider = `ShellProvider`

---

## ai_conversation Module --- Architecture Summary

- **Core service:** `AIApiService` (container: `ai_conversation.api_service`)
- **Model:** AWS Bedrock Claude 3.5 Sonnet (`anthropic.claude-3-5-sonnet-20240620-v1:0`, us-west-2)
- **Node type:** `ai_conversation` with fields: `field_messages`, `field_context`, `field_ai_model`, `field_conversation_summary`, `field_message_count`, `field_summary_message_count`, `field_summary_updated`, `field_total_tokens`
- **Rolling summary:** summarizes older messages every 10 new messages; keeps last 20 in full
- **Routes:** `/node/{nid}/chat`, `/ai-conversation/send-message` (AJAX), `/ai-conversation/stats`
- **Used by:** `job_hunter` (resume tailoring) and all future AI modules
- **Credentials:** `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` env vars (or IAM role)
- **Full architecture doc:** `sites/forseti/web/modules/custom/ai_conversation/ARCHITECTURE.md`

---

## Key Architecture Docs

| Doc | Path |
|---|---|
| ai_conversation ARCHITECTURE.md | `sites/forseti/web/modules/custom/ai_conversation/ARCHITECTURE.md` |
| ai_conversation README.md | `sites/forseti/web/modules/custom/ai_conversation/README.md` |
| Repo dev policy | `.github/instructions/instructions.md` |
| Site instructions (forseti.life) | `org-chart/sites/forseti.life/site.instructions.md` |
| copilot_agent_tracker feature.md | `features/forseti-copilot-agent-tracker/feature.md` |
| Module ownership | `org-chart/ownership/module-ownership.yaml` |

---

## Session Continuity
**Before starting any significant task:** Write a brief "in-progress" note to `sessions/architect-copilot/current-session-state.md` — set `## Currently Working On` to describe the task (1–2 lines). This ensures an interrupted session leaves a recoverable breadcrumb even if the end-of-session write never fires.

After any significant implementation block, overwrite `sessions/architect-copilot/current-session-state.md` with:
- What was just built or changed
- Current state of in-flight work
- Key decisions made
- Next actions (ordered)

---

## What NOT to do
- Do **not** run `hq-status.sh`, `sla-report.sh`, or `improvement-round.sh`
- Do **not** dispatch inbox items to other agents
- Do **not** manage release cycles or signoffs
- Do **not** modify org-chart or agents.yaml (that is CEO authority)
- Do **not** action improvement-round inbox items — if dispatched one, write a `done` outbox immediately noting it is outside architect scope, flag to CEO to remove architect-copilot from improvement-round dispatch routing, and stop. Do not perform gap analysis or write process fixes.
