# Suite Activation: forseti-langgraph-ui

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-08T14:34:17+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-langgraph-ui"`**  
   This links the test to the living requirements doc at `features/forseti-langgraph-ui/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-langgraph-ui-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-langgraph-ui",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-langgraph-ui"`**  
   Example:
   ```json
   {
     "id": "forseti-langgraph-ui-<route-slug>",
     "feature_id": "forseti-langgraph-ui",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-langgraph-ui",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-langgraph-ui (release-g scope)

- Feature: forseti-langgraph-ui
- Release: 20260408-forseti-release-g
- Module: copilot_agent_tracker
- QA owner: qa-forseti
- PM owner: pm-forseti
- Version: 1.0 — 2026-04-08
- AC source: features/forseti-langgraph-ui/01-acceptance-criteria.md

## Environment notes
- Production server IS the test environment: `https://forseti.life`
- `ALLOW_PROD_QA=1` required for `scripts/site-audit-run.sh`
- Admin credential required for auth-gated routes
- All routes: substitute `BASE_URL` with `https://forseti.life`

## Happy path tests

| Test ID | AC ref | Description | Steps | Expected outcome | Pass/Fail criteria |
|---|---|---|---|---|---|
| TP-BUILD-01 | AC-1 | State Schema panel renders for admin | Log in as admin; GET `BASE_URL/admin/reports/copilot-agent-tracker/langgraph-console/build` | 200; page contains State Schema section; LangGraphDeps fields visible in a table | PASS: 200 + schema table with ≥6 field rows. FAIL: non-200, PHP error, or empty section |
| TP-BUILD-02 | AC-1 | State Schema fields correct | Inspect rendered table | Fields include: `run_cmd`, `dispatch_commands_step`, `release_cycle_step`, `coordinated_push_step`, `prioritized_agents`, `health_check_step` | PASS: all 6 listed. FAIL: missing fields |
| TP-BUILD-03 | AC-2 | Nodes & Routing panel renders | Same page as TP-BUILD-01 | Nodes & Routing subsection visible; orchestrator step names listed | PASS: section present with ≥3 step names. FAIL: section absent or empty |
| TP-TEST-01 | AC-3 | Eval Scorecards placeholder renders for admin | GET `BASE_URL/admin/reports/copilot-agent-tracker/langgraph-console/test` | 200; Eval Scorecards subsection visible with placeholder table or empty-state message | PASS: 200 + scorecard table/placeholder visible. FAIL: non-200 or PHP error |

## Auth / permission tests

| Test ID | AC ref | Description | Steps | Expected outcome | Pass/Fail criteria |
|---|---|---|---|---|---|
| TP-AUTH-01 | AC-1,2,3 | Build console anon denied | `curl -s -o /dev/null -w "%{http_code}" BASE_URL/admin/reports/copilot-agent-tracker/langgraph-console/build` | 403 | PASS: 403. FAIL: 200 |
| TP-AUTH-02 | AC-3 | Test console anon denied | `curl -s -o /dev/null -w "%{http_code}" BASE_URL/admin/reports/copilot-agent-tracker/langgraph-console/test` | 403 | PASS: 403. FAIL: 200 |

## Regression tests

| Test ID | AC ref | Description | Steps | Expected outcome |
|---|---|---|---|---|
| TP-REG-01 | AC-4 | All 7 console routes still return 403 for anonymous | `curl` each of the 7 console routes without auth | 403 on all 7 |
| TP-REG-02 | AC-4 | All 7 telemetry dashboard routes still return 200 for admin | Log in; GET each dashboard route | 200 on all 7 |
| TP-REG-03 | AC-5 | No XSS in rendered field names | Inspect HTML source of Build page for unescaped `<`, `>`, `"` from schema data | All values HTML-escaped |

## Security checks

| Test ID | AC ref | Description | Steps | Expected outcome |
|---|---|---|---|---|
| TP-SEC-01 | AC-5 | No raw PHP variable output in Twig | Review modified template files | All variables use `{{ var|e }}` or equivalent auto-escape |
| TP-SEC-02 | Security AC | engine.py missing/unreadable handled gracefully | (Dev to verify) Remove read permission on engine.py; load Build page | Empty-state message displayed, not PHP fatal or 500 |

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-langgraph-ui (release-g scope)

- Feature: forseti-langgraph-ui
- Release: 20260408-forseti-release-g
- Module: copilot_agent_tracker
- PM owner: pm-forseti
- Version: 1.0 — 2026-04-08
- Theme: Console Build + Test Sections — State schema visualization, node topology, eval scorecards

## KB references
- knowledgebase/lessons/ — none found specific to LangGraph UI console wiring
- Roadmap: features/forseti-langgraph-ui/roadmap.md (authoritative scope reference)

## Scope for this release

Wire the **Build** and **Test** console sections with live data from `engine.py`:
- Build > State Schema: render `LangGraphDeps` fields as a schema table
- Build > Nodes & Routing: list defined functions from `engine.py` that act as nodes/edges
- Test > Eval Scorecards: placeholder scorecard UI linking to future `agent_evaluation` module

## Definition of Done

### AC-1: Build — State Schema panel wired
- Navigating to `/admin/reports/copilot-agent-tracker/langgraph-console/build` shows a "State Schema" subsection
- The panel renders the `LangGraphDeps` TypedDict fields (`run_cmd`, `dispatch_commands_step`, `release_cycle_step`, `coordinated_push_step`, `prioritized_agents`, `health_check_step`, `now_ts`, `kpi_monitor_cmd`) as a table with columns: Field Name, Type, Description
- No PHP errors; page returns 200 for authenticated admin with `administer copilot agent tracker` permission
- Anonymous request returns 403

### AC-2: Build — Nodes & Routing panel wired
- The Build section shows a "Nodes & Routing" subsection listing the orchestrator steps/functions visible in `engine.py`
- At minimum: `run_cmd`, `dispatch_commands_step`, `health_check_step`, `release_cycle_step`, `coordinated_push_step` listed with their callable type
- No PHP errors; 200 for admin, 403 for anonymous

### AC-3: Test — Eval Scorecards placeholder
- Navigating to `/admin/reports/copilot-agent-tracker/langgraph-console/test` shows an "Eval Scorecards" subsection
- The subsection renders a placeholder table with columns: Agent, Task Type, Success Rate, Last Run — populated with static/empty-state messaging explaining that live eval data requires the `agent_evaluation` module (not yet available)
- No PHP errors; 200 for admin, 403 for anonymous

### AC-4: No regression on existing console routes
- All 7 existing console section routes still return 200 (admin) / 403 (anon):
  - `/admin/reports/copilot-agent-tracker/langgraph-console`
  - `/admin/reports/copilot-agent-tracker/langgraph-console/build`
  - `/admin/reports/copilot-agent-tracker/langgraph-console/test`
  - `/admin/reports/copilot-agent-tracker/langgraph-console/run`
  - `/admin/reports/copilot-agent-tracker/langgraph-console/observe`
  - `/admin/reports/copilot-agent-tracker/langgraph-console/release`
  - `/admin/reports/copilot-agent-tracker/langgraph-console/config`
- All 7 existing telemetry dashboard routes (`/admin/reports/copilot-agent-tracker/langgraph`, `/langgraph/session`, etc.) still return 200 for admin

### AC-5: No XSS / output escaping
- All dynamically-rendered field names and values from `engine.py` are HTML-escaped before output
- Verify: PHP Codesniffer clean on modified controller files; no raw variable output in Twig templates

## Security acceptance criteria

### Authentication/permission surface
- All new Build and Test console routes require `administer copilot agent tracker` permission
- Anonymous access must return 403 on all new routes
- Verify: `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/admin/reports/copilot-agent-tracker/langgraph-console/build` → 403

### CSRF expectations
- These are read-only display pages (GET only); no CSRF token required
- If any form/POST is introduced, apply split-route CSRF pattern per KB lesson (forseti-jobhunter-profile CSRF fix)

### Input validation requirements
- No user-supplied input processed on these pages (read-only display of static schema data)
- File parsing (`engine.py` read) must handle missing/unreadable file gracefully (return empty-state message, not 500)

### PII/logging constraints
- No PII rendered or logged
- No `chat_log` or agent conversation content displayed
