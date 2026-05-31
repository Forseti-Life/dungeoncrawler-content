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
