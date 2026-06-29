- Status: done
- Completed: 2026-04-11T03:27:34Z

# Suite Activation: forseti-langgraph-console-build-sections

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
   **CRITICAL: tag every new entry with `"feature_id": "forseti-langgraph-console-build-sections"`**  
   This links the test to the living requirements doc at `features/forseti-langgraph-console-build-sections/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-langgraph-console-build-sections-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-langgraph-console-build-sections",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-langgraph-console-build-sections"`**  
   Example:
   ```json
   {
     "id": "forseti-langgraph-console-build-sections-<route-slug>",
     "feature_id": "forseti-langgraph-console-build-sections",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-langgraph-console-build-sections",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-langgraph-console-build-sections

- Feature: forseti-langgraph-console-build-sections
- Module: copilot_agent_tracker
- Author: pm-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-11

## Scope

Verify that all four Build subsection routes are accessible to authenticated users, return 403 to anonymous, render live data when available, and gracefully degrade when data is absent.

## Test cases

### TC-1: Anonymous access to state-schema → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/langgraph-console/build/state-schema`
- Expected: `403`

### TC-2: Anonymous access to nodes-routing → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/langgraph-console/build/nodes-routing`
- Expected: `403`

### TC-3: Authenticated admin — all 4 Build subsections return 200
- Steps: Log in as admin; visit `/langgraph-console/build/state-schema`, `/nodes-routing`, `/subgraphs`, `/tool-calling`.
- Expected: All return HTTP 200.

### TC-4: State schema — data present → live content rendered
- Steps: Ensure telemetry file exists with `step_results`; load state-schema page.
- Expected: Page renders schema key/type data; Live indicator 🟢.

### TC-5: State schema — no telemetry → graceful fallback
- Steps: Remove or rename telemetry file; load state-schema page.
- Expected: "State schema not yet available" message; no PHP error; HTTP 200.

### TC-6: Nodes/routing — workflow registry populated → node table rendered
- Steps: Load nodes-routing page with active orchestrator (registry populated).
- Expected: Table with at least one node row (e.g., `start`); Live indicator 🟢.

### TC-7: Tool calling — tool manifest present → tools listed
- Steps: Load tool-calling page when telemetry/manifest is available.
- Expected: Tool names and descriptions rendered; no PHP error.

### TC-8: All pages — no PHP errors in log
- Steps: Load all 4 Build subsection pages; check `/var/log/apache2/forseti_error.log`.
- Expected: Zero PHP errors or exceptions logged for these page loads.

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-langgraph-console-build-sections

- Feature: forseti-langgraph-console-build-sections
- Module: copilot_agent_tracker
- Author: pm-forseti
- Date: 2026-04-11

## Summary

Wire the four Build subsection pages of the LangGraph Console to live orchestrator/telemetry data: state schema, node/routing topology, subgraph detection, and tool manifest. Transitions these pages from Stub to Live status.

## Acceptance criteria

### AC-1: State schema page
- `GET /langgraph-console/build/state-schema` returns 200 for authenticated users.
- Anonymous → 403.
- When telemetry is available: renders state schema keys and types in a readable format (table or definition list).
- When telemetry is absent: renders message "State schema not yet available — run a workflow first." No PHP error.

### AC-2: Nodes/routing page
- `GET /langgraph-console/build/nodes-routing` returns 200 for authenticated users.
- Renders a table of registered workflow nodes: name, type/role, outgoing edges/conditions.
- Data pulled from the same WorkflowRegistry source as the Dashboard home.
- When registry is empty: "Node registry not yet available." No PHP error.

### AC-3: Subgraphs page
- `GET /langgraph-console/build/subgraphs` returns 200 for authenticated users.
- Renders identified subgraph boundaries from workflow registry `subgraph` field.
- When no subgraphs: "No subgraphs detected in the active workflow."

### AC-4: Tool calling page
- `GET /langgraph-console/build/tool-calling` returns 200 for authenticated users.
- Renders tool manifest: tool name, description, owning node (if available).
- When absent: "Tool manifest not yet available."

### AC-5: Live/Stub status indicators
- All four subsections show 🟢 Live indicator when their data source is populated.
- Show ⬜ Stub when data source is absent (no 500 error — graceful degradation).

### AC-6: Access control
- All Build subsection routes require `_permission: 'access copilot agent tracker'`.
- Authenticated users without this permission → 403.

### AC-7: Error-free operation
- Zero PHP errors in Apache error log for any of the four page loads.
- File-not-found for telemetry files → graceful "not available" message, not an exception.
- Agent: qa-forseti
- Status: pending
