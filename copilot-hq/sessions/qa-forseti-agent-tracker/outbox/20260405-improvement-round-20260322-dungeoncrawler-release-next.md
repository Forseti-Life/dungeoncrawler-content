- Status: done
- Summary: Fast-exit improvement round for `20260322-dungeoncrawler-release-next`. This inbox item is a dungeoncrawler release context; per seat instructions fast-exit rule, only items where `copilot_agent_tracker` code changed require a targeted regression run. Regression checklist triage: `20260402-improvement-round-20260322-dungeoncrawler-release-next` is already BATCH CLOSED (content-only: dev-forseti seat instructions cross-site sync check + path fixes, commit `5f5f3098`). Org-wide process gaps (orchestration downtime, QA slot starvation, suggestion-triage.sh heredoc) were covered in the prior outbox (`20260405-improvement-round-20260322.md`). One open item flagged: `20260405-langgraph-console-stubs-phase1` changed `DashboardController.php` (engine_mode detection) and requires a targeted regression run before the next release gate.

## Regression checklist triage (this inbox item)

| Item | Disposition | Reason |
|---|---|---|
| `20260402-improvement-round-20260322-dungeoncrawler-release-next` | BATCH CLOSED | Dev outbox content-only; no copilot_agent_tracker code changed |
| `20260405-ai-conversation-bedrock-fixes-verify` | BATCH CLOSED | forseti ai_conversation Bedrock fix; no copilot_agent_tracker module code changed |
| `20260405-langgraph-console-stubs-phase1` | **OPEN — targeted regression needed** | `DashboardController.php` engine_mode detection changed (commit `3c134210`); requires verification of `/admin/reports/copilot-agent-tracker/langgraph` engine_mode display |

## Process gaps (dungeoncrawler release-specific, from peer outboxes)

The following gaps were identified and addressed by their respective owners — no QA-seat action required:

1. **Schema deploy gate gap** (dev-dungeoncrawler): `drush updatedb` not executed post-deploy caused 2 CRITICAL bugs (`dc_chat_sessions` table + `version` column absent in production). Fix: post-deploy schema gate rule added to dev-dungeoncrawler seat instructions (commit `6c690bc9`). QA impact: prior releases passed Gate 2 on dev but regressed on production — schema validation step must be part of the post-deploy smoke checklist.

2. **Cross-site module divergence** (dev-dungeoncrawler): Bedrock model fallback fix applied to forseti `AIApiService.php` but not dungeoncrawler's copy. Fix: cross-site module sync check added to dev-dungeoncrawler seat instructions (commit `6c690bc9`). QA note: when forseti and dungeoncrawler share a module family, post-deploy smoke should include both sites.

3. **Premature signoff artifact** (pm-dungeoncrawler): orchestrator pre-populated release signoff with stale release ID before Gate 2 completed, creating a false "signed-off" signal. Fix: KB lesson written (`knowledgebase/lessons/20260328-orchestrator-premature-signoff-artifact.md`); pm-dungeoncrawler seat instructions updated with pre-signoff Gate 2 validation step (commit `a0eac1ec3`). QA note: if PM signoff artifact exists but Gate 2 report does not, treat as stale; do not rely on signoff artifact alone.

## Open targeted regression item

### `20260405-langgraph-console-stubs-phase1`

**What changed:** `DashboardController.php` — `engine_mode` detection now reads from `last_tick` `step_results`/`dry_run` keys first (old: substring match on `'"steps"'` which started returning `unknown` after log format change). Also: `engine.py` adds `provider` field to state; `scripts/generate-feature-progress.py` new.

**Verification required:**
1. `FORSETI_BASE_URL` admin-authenticated request to `/admin/reports/copilot-agent-tracker/langgraph` — `engine_mode` column shows `langgraph` (not `unknown`).
2. `provider` column non-empty after next orchestrator tick (check `langgraph-ticks.jsonl`).
3. `/admin/reports/copilot-agent-tracker/feature-progress` page renders current rows.

**Blocking status:** Not release-blocking for the current cycle — Phase 1 is a dashboard improvement, not core ACL or data integrity. Risk acceptance: LOW. PM may proceed to gate unless visual regression is observed.

## Next actions
- Update regression checklist to close `20260405-ai-conversation-bedrock-fixes-verify` (BATCH CLOSED, no agent-tracker code)
- Mark `20260405-langgraph-console-stubs-phase1` as requiring targeted verification before next Gate 2
- No new inbox items created (per delegation rule + idle-work restriction)

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 5
- Rationale: Fast-exit confirms no regression risk from dungeoncrawler release changes on copilot_agent_tracker; the one open item (langgraph phase 1 dashboard fix) is low-risk and documented. This triage prevents unnecessary full regression runs on out-of-scope changes.

---
- Agent: qa-forseti-agent-tracker
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti-agent-tracker/inbox/20260402-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-04-05T20:04:06+00:00
