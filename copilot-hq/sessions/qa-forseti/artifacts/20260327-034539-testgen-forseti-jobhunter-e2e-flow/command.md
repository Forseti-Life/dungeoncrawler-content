# Test Plan Design: forseti-jobhunter-e2e-flow

- Agent: qa-forseti
- Status: pending

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-03-27T03:45:39-04:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-jobhunter-e2e-flow/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-jobhunter-e2e-flow "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/forseti/suite.json`
- Do NOT edit `org-chart/sites/forseti.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

# Acceptance Criteria — forseti-jobhunter-e2e-flow

- Feature: JobHunter End-to-End Flow (job discovery → save → apply → track)
- Release target: 20260226-forseti-release-b
- PM owner: pm-forseti
- Date groomed: 2026-02-26

## Knowledgebase check
- Related: features/forseti-jobhunter-e2e-flow/feature.md
- Related test: testing/jobhunter-workflow-step1-6-data-engineer.mjs

## Happy Path
- [ ] `[REQ-ID: REQ-04.1]` A logged-in user can navigate the `/jobhunter` dashboard step flow from step 1 to step 6 without errors.
- [ ] `[REQ-ID: REQ-02.6]` A J&J data-role job (or any manually added job) can be saved to the user's job list.
- [ ] `[REQ-ID: REQ-04.2]` User can mark a job as "ready-to-apply" and then "applied/submitted"; date + link + status are persisted.
- [ ] `[REQ-ID: REQ-04.2]` Job status is visible in the job list and job detail view.
- [ ] `[REQ-ID: REQ-06.1]` Queues/background processes run without manual intervention (no stuck steps on the dashboard).

## Stage break (hard constraint — must not be violated)
- [ ] `[REQ-ID: REQ-08.5]` The system does NOT create an account on any external portal (e.g., J&J careers portal).
- [ ] `[REQ-ID: REQ-08.5]` "Apply" action only opens the external link / records intent — no automated external account creation.

## Edge Cases
- [ ] `[REQ-ID: REQ-02.7]` Duplicate job submission (same URL or NID) is handled gracefully (deduplicated or user-notified).
- [ ] `[REQ-ID: REQ-04.1]` Missing external link does not prevent saving a job with manual entry.
- [ ] `[REQ-ID: REQ-04.1]` Step flow handles a skipped/incomplete step with a clear prompt rather than a blank page.

## Failure Modes
- [ ] `[REQ-ID: REQ-06.9]` Queue failure or external scrape failure shows a recoverable error state (no silent data loss).
- [ ] `[REQ-ID: REQ-08.7]` Error messages are clear and actionable; no raw stack traces exposed to users.

## Permissions / Access Control
- [ ] `[REQ-ID: REQ-08.5]` Anonymous user: redirected to login; no job data exposed.
- [ ] `[REQ-ID: REQ-08.4, REQ-01.8]` Authenticated user: can only see/manage their own jobs.
- [ ] `[REQ-ID: REQ-08.5]` Admin: access per existing permission model (document any exceptions).

## Data Integrity
- [ ] `[REQ-ID: REQ-04.3]` Job status transitions are logged and reversible (history visible).
- [ ] `[REQ-ID: REQ-04.1]` Rollback path: if step flow crashes mid-sequence, the last valid state is preserved.

## Verification method
- Run (or adapt) existing Playwright workflow: `testing/jobhunter-workflow-step1-6-data-engineer.mjs`.
- Accept: all 6 steps complete without assertion failure; final job status recorded in DB.
- Stage break check: confirm no external account creation call is made during the run (log review).
