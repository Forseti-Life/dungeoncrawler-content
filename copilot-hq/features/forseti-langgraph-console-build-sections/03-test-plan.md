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
