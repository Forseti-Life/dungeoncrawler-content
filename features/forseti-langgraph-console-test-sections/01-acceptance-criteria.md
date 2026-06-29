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
