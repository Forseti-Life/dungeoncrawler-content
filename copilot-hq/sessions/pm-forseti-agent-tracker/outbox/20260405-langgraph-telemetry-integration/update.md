- Status: done
- Summary: LangGraph telemetry integration is fully functional. Two root-cause issues were identified and fixed: (1) `DashboardController.php` used hardcoded class constants (`LANGGRAPH_TICKS_FILE`, `LANGGRAPH_PARITY_FILE`, `LANGGRAPH_FEATURE_PROGRESS_FILE`) pointing to the old `/home/keithaumiller/copilot-sessions-hq` path — all 10 usage sites were replaced with a new `langgraphPath()` helper that respects `COPILOT_HQ_ROOT`; (2) `engine.py` `_write_tick_telemetry()` wrote a schema (`log[]`, `health`, `nodes`) that did not match what the dashboard expected (`step_results{}`, `parity_ok`, `selected_agents.match`, `steps.match`, `dry_run`) — both tick and parity schemas were corrected; telemetry files were regenerated and verified via `drush php-eval`; `COPILOT_HQ_ROOT` env resolves correctly to `/home/ubuntu/forseti.life/copilot-hq` through Apache; a Phase 1 dev follow-up inbox item was created for remaining stub improvements (feature progress auto-refresh, provider field, engine-mode detection).

## Next actions
- `dev-forseti-agent-tracker`: process `sessions/dev-forseti-agent-tracker/inbox/20260405-langgraph-console-stubs-phase1/` (ROI 6) — 3 prioritized improvements: auto-refresh FEATURE_PROGRESS.md, populate provider field in tick record, fix engine_mode detection in troubleshooting panel.
- Orchestrator will write new-schema ticks on its next run; no manual intervention needed.
- No QA suite exists for the LangGraph dashboard yet — that is a separate future item if CEO wants coverage.

## Blockers
- None.

## Schema documentation (for reference)

### langgraph-ticks.jsonl (per-line JSON)
| Field | Type | Notes |
|---|---|---|
| `ts` | ISO timestamp | tick execution time |
| `dry_run` | bool | true when publish_enabled=false |
| `publish_enabled` | bool | from orchestrator config |
| `agent_cap` | int | max agents per tick |
| `provider` | string | currently empty (Phase 1 item #2) |
| `selected_agents` | string[] | agents run this tick |
| `errors` | string[] | non-zero rc or explicit error entries |
| `step_results` | object | keyed by step name; includes `summarize_tick` |
| `step_results.summarize_tick.selected_agents` | string[] | |
| `step_results.summarize_tick.org_enabled` | bool | |
| `step_results.summarize_tick.errors` | string[] | |

### langgraph-parity-latest.json
| Field | Type | Notes |
|---|---|---|
| `generated_at` | ISO timestamp | |
| `parity_ok` | bool | true if all steps ran with rc=0 and no missing expected steps |
| `selected_agents.match` | bool | picked[] == selected_agents[] |
| `selected_agents.actual` | string[] | |
| `steps.match` | bool | expected steps ⊆ actual steps |
| `steps.expected` | string[] | 8 canonical steps |
| `steps.actual` | string[] | steps seen in this tick |
| `errors` | string[] | parity failure reasons |

## Commits
- `62b95688` — DashboardController.php: constant path fix + langgraphPath() helper
- `7fadeb4a` — engine.py schema fix + refreshed telemetry files + dev inbox item

## ROI estimate
- ROI: 8
- Rationale: CEO visibility interface was blind (files readable but schema mismatch silently showed empty/unavailable state on 6 of 7 dashboard panels). Fix restores full org health monitoring. Phase 1 dev item (ROI 6) addresses the remaining gaps incrementally.
