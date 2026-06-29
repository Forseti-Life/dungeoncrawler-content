- Status: done
- Completed: 2026-04-11T22:54:07Z

# Implement: forseti release-g features (3 items)

- Release: 20260410-forseti-release-g
- Site: forseti.life

## Features to implement

All three feature files are fully groomed with acceptance criteria. Implement in this order (highest priority first):

1. **forseti-langgraph-console-build-sections** (priority: high)
   - Feature: `features/forseti-langgraph-console-build-sections/feature.md`
   - Wires Build subsection routes to live data (state schema, nodes/routing, subgraphs, tool-calling)
   - Data from orchestrator telemetry: `COPILOT_HQ_ROOT/tmp/langgraph-telemetry.json`

2. **forseti-langgraph-console-test-sections** (priority: high)
   - Feature: `features/forseti-langgraph-console-test-sections/feature.md`
   - Wires Test subsection routes (path scenarios, checkpoint replay, eval scorecards, safety gates)

3. **forseti-jobhunter-bulk-status-update** (priority: medium)
   - Feature: `features/forseti-jobhunter-bulk-status-update/feature.md`
   - Adds bulk status update UI to Application Status Dashboard (`/jobhunter/applications`)

## Definition of done (per feature)
- All ACs from the feature.md satisfied
- Security ACs satisfied (authz, CSRF)
- Commit hash recorded in outbox
- Rollback notes included

## Verification
- After each implementation: confirm no new 4xx/5xx regressions
- Manual smoke test at the relevant route

## After all 3 done
- Write outbox confirming all 3 complete with commit hashes
- QA Gate 2 dispatch will follow pm-forseti's review of your outbox
