# Feature Brief

- Work item id: forseti-langgraph-console-build-sections
- Website: forseti.life
- Module: copilot_agent_tracker
- Status: in_progress
- Release: 20260411-coordinated-release
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: LangGraph UI roadmap (release-g theme: Console Build + Test Sections) 2026-04-11

## Summary

The LangGraph Console Build section (`/langgraph-console/build`) currently renders stub content with Live/Stub status indicators. This feature wires the four Build subsections to live data: **State Schema** (renders the JSON schema of the active graph's `AgentState` TypedDict from telemetry), **Nodes/Routing** (renders the registered node list and their conditional edge definitions from the orchestrator's routing registry), **Subgraphs** (renders detected subgraph boundaries), and **Tool Calling** (renders the tool manifest available to each node). All data is read from files already produced by the orchestrator and LangGraph telemetry.

## Goal

- Engineers can view the live state schema, node topology, and tool manifest for the active LangGraph workflow directly in the Drupal console — no SSH required.
- The Build section transitions from all-Stub to fully-Live status for these four subsections.

## Acceptance criteria

- AC-1: `/langgraph-console/build/state-schema` renders a formatted display of the active graph's state schema keys and types. Data is loaded from the orchestrator telemetry file (`COPILOT_HQ_ROOT/tmp/langgraph-telemetry.json` or equivalent path from `DashboardController::langgraphPath()`). If no data: "State schema not yet available — run a workflow first."
- AC-2: `/langgraph-console/build/nodes-routing` renders a table of registered nodes (name, type/role, edges/conditions). Data source: orchestrator workflow registry (already surfaced on the dashboard home). If no data: "Node registry not yet available."
- AC-3: `/langgraph-console/build/subgraphs` renders detected subgraph boundaries. For this release, subgraphs are identified by the `subgraph` key in workflow registry entries; if none present: "No subgraphs detected in the active workflow."
- AC-4: `/langgraph-console/build/tool-calling` renders the tool manifest: tool names, descriptions, owning node. Data source: `tool_manifest` field in telemetry or registry. If no data: "Tool manifest not yet available."
- AC-5: All four subsections update their Live/Stub status indicator to 🟢 Live when data is present.
- AC-6: All four routes return 403 for anonymous users (consistent with existing console access control).
- AC-7: No PHP errors or exceptions on any subsection page when data files are present or absent.

## Non-goals

- Interactive graph editing or schema modification (deferred).
- Real-time streaming updates (deferred to a later release).
- Subgraph drill-down detail pages.

## Security acceptance criteria

- Authentication/permission surface: All Build subsection routes require `_permission: 'access copilot agent tracker'` (same as existing console routes). Anonymous access → 403. No role other than authenticated admins/editors should see live telemetry.
- CSRF expectations: All subsection routes are GET-only (read-only display). No CSRF token required.
- Input validation: No user-supplied path parameters accepted; subsection routes are static paths. Telemetry file path is resolved via `DashboardController::langgraphPath()` — not user-controlled.
- PII/logging constraints: State schema and node registry contain no user PII. Do not log telemetry file contents to watchdog. Log only file path and read errors at warning level.

## Implementation notes (to be authored by dev-forseti)

- `LangGraphConsoleStubController` already has route entries and stub renders for all Build subsections. Replace stub `renderSection()` calls with data-aware renders that read from telemetry/registry files.
- Use `DashboardController::langgraphPath()` helper for all file path resolution — do not hardcode paths.
- For state schema: parse `AgentState` schema from `step_results` structure in telemetry; or expose a dedicated `state_schema.json` from the orchestrator if it exists.
- For node topology: reuse `WorkflowRegistry` data structure already rendered on the Dashboard home.
- Live/Stub status is driven by `buildSectionRows()` in `LangGraphConsoleStubController`; add the four new wired keys.

## Test plan (to be authored by qa-forseti)

- TC-1: Anonymous → `/langgraph-console/build/state-schema` returns 403.
- TC-2: Authenticated admin → all 4 Build subsection routes return 200.
- TC-3: When telemetry data is present: state schema page renders key/type data (not stub placeholder).
- TC-4: When telemetry data is absent: state schema page renders "not yet available" message (no PHP error).
- TC-5: Nodes/routing page renders node table with at least the `start` node entry when registry is populated.
- TC-6: Tool calling page renders tool manifest or "not yet available" message.
- TC-7: Live/Stub indicators for state-schema and nodes-routing show 🟢 Live when data present.
- TC-8: No PHP errors in Apache error log for any Build subsection page load.

## Journal

- 2026-04-11: Feature created by pm-forseti for release-g scope activation. LangGraph UI roadmap release-g theme.
