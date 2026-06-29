# Release Change List: 20260410-forseti-release-g

- Release: 20260410-forseti-release-g
- Site: forseti.life
- PM: pm-forseti
- Date scoped: 2026-04-11
- Status: in_progress

## Scope (3 features)

| Feature ID | Module | Priority | Theme |
|---|---|---|---|
| forseti-langgraph-console-build-sections | copilot_agent_tracker | High | LangGraph Console Build section wiring |
| forseti-langgraph-console-test-sections | copilot_agent_tracker | High | LangGraph Console Test section wiring |
| forseti-jobhunter-bulk-status-update | job_hunter | Medium | JobHunter bulk application status update |

## Theme

**LangGraph Console — Build + Test sections** (roadmap release-g) plus a high-value JobHunter usability improvement.

## Feature summaries

### forseti-langgraph-console-build-sections
Wire the Build section subsections (state-schema, nodes-routing, subgraphs, tool-calling) to live orchestrator/telemetry data. Transitions these pages from Stub to Live.

### forseti-langgraph-console-test-sections
Wire the Test section subsections (path-scenarios, checkpoint-replay, eval-scorecards, safety-gates) to live QA suite / gate state data. Provides engineers a live QA posture view in the Drupal console.

### forseti-jobhunter-bulk-status-update
Add bulk status update to the Application Status Dashboard: per-row checkboxes, "Select all", selected-count indicator, and a CSRF-guarded POST endpoint. Reduces friction for users managing many applications.

## Gate checkpoints

- [ ] Gate 1: Dev complete — dev-forseti outbox DONE for all 3 features
- [ ] Gate 2: QA approve — qa-forseti consolidated gate2-approve
- [ ] Gate 3: PM signoff — pm-forseti `release-signoff.sh forseti 20260410-forseti-release-g`
- [ ] Gate 4: Coordinated push (forseti + dungeoncrawler)
