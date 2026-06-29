# Feature Brief

- Work item id: forseti-langgraph-console-test-sections
- Website: forseti.life
- Module: copilot_agent_tracker
- Project: PROJ-001
- Group Order: 3
- Group: console-ui
- Group Title: Console Routes & UI
- Group Sort: 2
- Status: done
- Release: 20260411-coordinated-release
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: LangGraph UI roadmap (release-g theme: Console Build + Test Sections) 2026-04-11

## Summary

The LangGraph Console Test section (`/langgraph-console/test`) currently renders stub content. This feature wires the Test subsections to live data: **Path Scenarios** (renders defined test scenarios from the QA suite config), **Checkpoint Replay** (links to or renders last checkpoint state from the orchestrator), **Eval Scorecards** (renders feature-level QA pass/fail scores from the most recent gate verdict), and **Safety Gates** (renders current gate status: open/closed/blocked). Data sources are the QA suite JSON, the orchestrator tick data, and existing gate verdict files in HQ.

## Goal

- The test section gives engineers a live view of QA posture, test coverage, and safety gate status without leaving the Drupal interface.
- All four Test subsections transition from Stub to Live.

## Acceptance criteria

- AC-1: `/langgraph-console/test/path-scenarios` renders a list of defined test scenarios from `qa-suites/products/forseti.life/suite.json`. Each entry shows: id, label, type, required_for_release. Anonymous → 403.
- AC-2: `/langgraph-console/test/checkpoint-replay` renders the most recent orchestrator checkpoint summary. Data source: latest tick file or checkpoint in `tmp/` (via `langgraphPath()`). If absent: "No checkpoint data available."
- AC-3: `/langgraph-console/test/eval-scorecards` renders feature-level QA results: feature id, pass/fail status, source verdict file path. Data is read from the most recent QA gate verdict files in `sessions/qa-forseti/outbox/`. If no verdicts found: "No QA scorecard data available."
- AC-4: `/langgraph-console/test/safety-gates` renders current gate status for the active forseti release: Gate 1 (dev complete), Gate 2 (QA approve), Gate 3 (PM signoff), Gate 4 (regression). Status pulled from `tmp/release-cycle-active/` and `sessions/pm-forseti/artifacts/release-signoffs/`. Anonymous → 403.
- AC-5: All four subsections show 🟢 Live indicator when data is present.
- AC-6: All routes return 403 for anonymous users.
- AC-7: Zero PHP errors for any page load.

## Non-goals

- Interactive test execution from the UI (deferred).
- Modifying QA suite entries from this interface.
- Historical scorecard trend analysis.

## Security acceptance criteria

- Authentication/permission surface: All Test subsection routes require `_permission: 'access copilot agent tracker'`. No anonymous access.
- CSRF expectations: All routes are GET-only (read-only display). No CSRF.
- Input validation: No user-supplied path parameters. All file paths resolved via `langgraphPath()` or HQ root constants.
- PII/logging constraints: QA verdicts and gate files contain no user PII. Log only file-not-found errors at warning level; do not dump file contents to watchdog.

## Implementation notes (to be authored by dev-forseti)

- Extend `LangGraphConsoleStubController` to add data-aware renders for the four Test subsection routes.
- Path scenarios: `json_decode(file_get_contents("$hq_root/qa-suites/products/forseti.life/suite.json"))`.
- Eval scorecards: `glob("$hq_root/sessions/qa-forseti/outbox/*gate*approve*.md")` + parse first heading/status from each file.
- Safety gates: read `$hq_root/tmp/release-cycle-active/forseti.release_id`, check for signoff artifact at `sessions/pm-forseti/artifacts/release-signoffs/<release_id>.md`.
- Update `buildSectionRows()` wired keys for these four subsections.

## Test plan (to be authored by qa-forseti)

- TC-1: Anonymous → all 4 Test subsection routes return 403.
- TC-2: Authenticated admin → all 4 Test subsection routes return 200.
- TC-3: Path scenarios page renders suite entries (id + label visible) when suite.json is populated.
- TC-4: Checkpoint replay page renders latest checkpoint data or graceful "no data" message.
- TC-5: Eval scorecards page renders feature verdicts from outbox gate files, or "no data" message.
- TC-6: Safety gates page renders gate open/closed/blocked status for the active forseti release.
- TC-7: When data files are absent: all pages return 200 with graceful fallback; no PHP errors.
- TC-8: Live indicator 🟢 appears on path-scenarios page when suite.json has entries.

## Journal

- 2026-04-11: Feature created by pm-forseti for release-g scope activation. LangGraph UI roadmap release-g theme.
