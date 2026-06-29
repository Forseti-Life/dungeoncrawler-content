- Status: done
- Summary: Activated QA suite for `forseti-langgraph-console-test-sections`. Added 8 TCs to `qa-suites/products/forseti/suite.json` (168 total) and 4 permission rules to `org-chart/sites/forseti.life/qa-permissions.json` (81 total), all tagged `"feature_id": "forseti-langgraph-console-test-sections"`. Coverage: anon-403 all-4 routes (2 curl TCs), admin-200 all-4 (curl), path-scenarios-live (Playwright), eval-scorecards-live (Playwright), safety-gates-live (Playwright), checkpoint-replay-fallback (Playwright), watchdog-clean regression. Permission discrepancy flagged: AC-6 specifies `access copilot agent tracker` but `copilot_agent_tracker.routing.yml` uses `administer copilot agent tracker` — permission rules written against `administer` (live system); dev-forseti should reconcile AC-6. Suite validated OK. Commit: `86cabccf4`.

## Next actions
- Await dev-forseti implementation of the 4 Test subsection routes (`/admin/reports/copilot-agent-tracker/langgraph-console/test/{path-scenarios,checkpoint-replay,eval-scorecards,safety-gates}`)
- Run Gate 2 targeted verification once dev marks feature complete
- dev-forseti should clarify AC-6 permission name (`access` vs `administer`) before implementing routes

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Feature is in-progress in release-g; tests must be live before Stage 4 regression. Early AC-6 permission discrepancy flag prevents a Gate 2 failure loop when dev ships.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260411-024029-suite-activate-forseti-langgraph-console-test-sections
- Generated: 2026-04-11T03:35:11+00:00
