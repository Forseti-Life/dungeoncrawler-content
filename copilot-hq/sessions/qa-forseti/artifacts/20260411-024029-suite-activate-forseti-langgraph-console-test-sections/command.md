- Status: done
- Completed: 2026-04-11T03:35:11Z

# Suite Activation: forseti-langgraph-console-test-sections

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-11T02:40:29+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-langgraph-console-test-sections"`**  
   This links the test to the living requirements doc at `features/forseti-langgraph-console-test-sections/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-langgraph-console-test-sections-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-langgraph-console-test-sections",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-langgraph-console-test-sections"`**  
   Example:
   ```json
   {
     "id": "forseti-langgraph-console-test-sections-<route-slug>",
     "feature_id": "forseti-langgraph-console-test-sections",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-langgraph-console-test-sections",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-langgraph-console-test-sections

- Feature: forseti-langgraph-console-test-sections
- Module: copilot_agent_tracker
- Author: pm-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-11

## Scope

Verify that the four Test subsection pages are accessible to authenticated admins, return 403 to anonymous, render live data from suite.json / checkpoint files / QA outbox / gate state files, and degrade gracefully when data is absent.

## Test cases

### TC-1: Anonymous access — all 4 Test routes return 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/langgraph-console/test/path-scenarios`; repeat for `/checkpoint-replay`, `/eval-scorecards`, `/safety-gates`.
- Expected: All return `403`.

### TC-2: Authenticated admin — all 4 Test routes return 200
- Steps: Log in as admin; visit each of the 4 routes.
- Expected: All return HTTP 200.

### TC-3: Path scenarios — suite.json populated → entries rendered
- Steps: Ensure `qa-suites/products/forseti.life/suite.json` has entries; load path-scenarios page.
- Expected: Each suite entry's id and label are visible; Live indicator 🟢.

### TC-4: Eval scorecards — outbox gate files present → verdicts rendered
- Steps: Ensure `sessions/qa-forseti/outbox/` contains gate2-approve files; load eval-scorecards page.
- Expected: Feature id and APPROVE/BLOCK status visible per verdict file.

### TC-5: Safety gates — active release present → gate status rendered
- Steps: Ensure `tmp/release-cycle-active/forseti.release_id` is set; load safety-gates page.
- Expected: Gate 2 shows closed/approved status (matching signoff artifact state); no PHP error.

### TC-6: Checkpoint replay — no checkpoint data → graceful fallback
- Steps: Remove/rename checkpoint file; load checkpoint-replay page.
- Expected: "No checkpoint data available." message; HTTP 200; no PHP error.

### TC-7: All pages — no data → graceful fallback (not 500)
- Steps: Simulate all data sources absent; load all 4 pages.
- Expected: Each page returns 200 with appropriate "no data" message; zero PHP errors in error log.

### TC-8: Error log clean
- Steps: Load all 4 Test subsection pages; check `/var/log/apache2/forseti_error.log`.
- Expected: Zero PHP errors for these page loads.

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-langgraph-console-test-sections

- Feature: forseti-langgraph-console-test-sections
- Module: copilot_agent_tracker
- Author: pm-forseti
- Date: 2026-04-11

## Summary

Wire the four Test subsection pages of the LangGraph Console to live data: QA test scenario list, orchestrator checkpoint, feature eval scorecards, and safety gate status.

## Acceptance criteria

### AC-1: Path scenarios page
- `GET /langgraph-console/test/path-scenarios` returns 200 for authenticated users.
- Anonymous → 403.
- Renders a list of test entries from `qa-suites/products/forseti.life/suite.json`: id, label, type, required_for_release fields visible.
- If suite.json is empty/missing: "No test scenarios defined." No PHP error.

### AC-2: Checkpoint replay page
- `GET /langgraph-console/test/checkpoint-replay` returns 200 for authenticated users.
- Renders the most recent orchestrator checkpoint/tick summary.
- If no checkpoint data: "No checkpoint data available." No PHP error.

### AC-3: Eval scorecards page
- `GET /langgraph-console/test/eval-scorecards` returns 200 for authenticated users.
- Renders QA gate verdict summary: feature id and APPROVE/BLOCK status for the most recent gate verdicts found in `sessions/qa-forseti/outbox/`.
- If no verdict files: "No QA scorecard data available." No PHP error.

### AC-4: Safety gates page
- `GET /langgraph-console/test/safety-gates` returns 200 for authenticated users.
- Renders gate status for the active forseti release: Gate 1 (dev complete), Gate 2 (QA approve), Gate 3 (PM signoff), Gate 4 (regression). Each gate shows open/closed/blocked.
- Data pulled from `tmp/release-cycle-active/forseti.release_id` and `sessions/pm-forseti/artifacts/release-signoffs/`.
- Anonymous → 403.

### AC-5: Live/Stub status indicators
- Path-scenarios and safety-gates show 🟢 Live when data is present.
- ⬜ Stub when data source is absent (graceful; no 500 error).

### AC-6: Access control
- All 4 routes: `_permission: 'access copilot agent tracker'`. Unauthenticated → 403.

### AC-7: Error-free operation
- Zero PHP errors in Apache error log for all four page loads (data present or absent).
- Agent: qa-forseti
- Status: pending
