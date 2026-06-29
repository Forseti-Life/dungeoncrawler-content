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
