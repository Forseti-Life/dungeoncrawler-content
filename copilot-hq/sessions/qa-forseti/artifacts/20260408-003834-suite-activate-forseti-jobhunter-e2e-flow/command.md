# Suite Activation: forseti-jobhunter-e2e-flow

**From:** pm-forseti.life  
**To:** qa-forseti.life  
**Date:** 2026-04-08T00:38:34+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti.life/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-e2e-flow"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-e2e-flow/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-e2e-flow-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-e2e-flow",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-e2e-flow"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-e2e-flow-<route-slug>",
     "feature_id": "forseti-jobhunter-e2e-flow",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-e2e-flow",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — forseti-jobhunter-e2e-flow

- Feature: JobHunter End-to-End Flow (job discovery → save → apply → track)
- QA owner: qa-forseti
- Date: 2026-04-05 (updated; originally 2026-02-26)
- Release target: 20260402-forseti-release-b (grooming; NOT added to suite.json yet)
- AC source: features/forseti-jobhunter-e2e-flow/01-acceptance-criteria.md

## Knowledgebase references
- KB lesson: `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — job_seeker_id vs uid; verify data operations in step 1 (profile save) and step 3 (apply) use the correct IDs.

## Suite assignments
All E2E flow test cases → `jobhunter-e2e` suite (Playwright)
All route/ACL test cases → `role-url-audit` suite

## Test cases

### TC-01: Dashboard step navigation (Steps 1–6)
- Description: Authenticated user can navigate the /jobhunter dashboard step flow from step 1 to step 6 without errors.
- Suite: `jobhunter-e2e`
- Script: `testing/jobhunter-workflow-step1-6-data-engineer.mjs`
- Steps: Login via ULI → /jobhunter → click each phase button (My Profile → Job Discovery → View Submissions → Manage Pipeline → View Analytics) → each reaches correct URL
- Expected: All 6 steps load with HTTP 200; no console errors flagged in report.consoleErrors; report.json written
- Roles: authenticated
- Exit criteria: `report.submission.success = true` OR all step URLs reachable

### TC-02: Job discovery — search finds and saves matching job
- Description: Job Discovery step returns a matching "Data Engineer" result in Philadelphia and the save action persists the job.
- Suite: `jobhunter-e2e`
- Script: `testing/jobhunter-workflow-step1-6-data-engineer.mjs` (steps 2a)
- Expected: `report.steps.step2_search_criteria.hasRole = true`, `hasLocation = true`, `saved = true`
- Roles: authenticated

### TC-03: Manual job add fallback
- Description: When job discovery finds no matching role, user can manually add a job via `/jobhunter/jobs/paste` and it is saved.
- Suite: `jobhunter-e2e`
- Script: `testing/jobhunter-workflow-step1-6-data-engineer.mjs` (addManualDataEngineerJob path)
- Expected: POST to /jobhunter/jobs/paste succeeds; job appears in /jobhunter/my-jobs with correct title
- Roles: authenticated

### TC-04: Mark job as applied/submitted — status and date persisted
- Description: User marks a job "have_applied" with date; system persists and shows confirmation.
- Suite: `jobhunter-e2e`
- Script: `testing/jobhunter-workflow-step1-6-data-engineer.mjs` (markAppliedDataEngineerJob)
- Expected: `report.submission.success = true`; page body contains "marked as applied"/"updated"; applied_on_date is today
- Roles: authenticated

### TC-05: Job status visible in job list and detail view
- Description: After applying, job status is visible at /jobhunter/my-jobs and on the job detail page.
- Suite: `jobhunter-e2e`
- Script: extend `testing/jobhunter-workflow-step1-6-data-engineer.mjs` with a post-apply check navigating to /jobhunter/my-jobs and verifying "applied" label is visible
- Expected: Status cell shows "applied"/"submitted"; detail page shows applied date and link
- Roles: authenticated

### TC-06: Queues/background processes run without manual intervention
- Description: After saving a job (step 2), the dashboard shows no stuck/broken steps (queue completes).
- Suite: `jobhunter-e2e`
- Script: `testing/jobhunter-workflow-step1-6-data-engineer.mjs` — verify step 3 (submission page) loads and the job is present after queue processing delay
- Expected: /jobhunter/application-submission loads; no error state or stuck-step indicator on dashboard
- Roles: authenticated

### TC-07: Stage break — no external account creation (HARD CONSTRAINT)
- Description: The system must NOT create an account on any external portal (e.g., J&J careers portal). "Apply" only opens external link or records intent.
- Suite: `jobhunter-e2e`
- Test approach: Log review during Playwright run — inspect browser network requests made during "apply" action; assert no outbound POST to `careers.jnj.com` or equivalent. Also verify the apply action only navigates/opens a link internally.
- Implementation note: Extend the Playwright script to intercept network requests and assert no POST to external careers domains. Add a `page.on('request', ...)` listener for `careers.jnj.com` or any third-party `*/register*` or `*/account/create*` paths.
- Expected: No network POST to any external careers portal; apply action stays within forseti.life
- Roles: authenticated
- Automation flag: Requires Playwright network interception. Currently not implemented in the existing script — this test case requires an extension. **Flag to PM: stage break assertion is the highest-priority missing automation; must be verified before release.**

### TC-08: Duplicate job submission is deduplicated or user-notified
- Description: Submitting the same job URL or NID twice is handled gracefully.
- Suite: `jobhunter-e2e`
- Script: Extend script to run addManualDataEngineerJob twice with the same URL; assert deduplication message or single record in my-jobs
- Expected: Second submission shows "already exists" message OR job count does not increase; no crash/blank page
- Roles: authenticated

### TC-09: Missing external link — manual entry still saves
- Description: A job with no external URL can still be saved via manual entry.
- Suite: `jobhunter-e2e`
- Script: Extend addManualDataEngineerJob with empty job_url field; submit; verify job saved
- Expected: Job appears in /jobhunter/my-jobs with blank/null link; no validation error blocking save
- Roles: authenticated

### TC-10: Skipped/incomplete step shows clear prompt (not blank page)
- Description: Navigating to a step that requires earlier steps to be complete shows a prompt rather than a blank page.
- Suite: `jobhunter-e2e`
- Script: Navigate directly to step 3/4/5 before completing earlier steps; assert prompt text visible, not a blank body
- Expected: Page body contains guidance text or a redirect back to the relevant step; no blank page (body innerText length > 200 chars)
- Roles: authenticated

### TC-11: Queue/scrape failure shows recoverable error state
- Description: If external job scraping or queue processing fails, the UI shows an actionable error rather than silent data loss.
- Suite: `jobhunter-e2e`
- Automation flag: Requires a mock/injectable failure path. Recommend verifying at the unit/module level (dev-owned) or via Drupal log inspection. **Cannot be fully automated by QA without Dev providing a failure injection hook or test mode.** Flagged to PM: manual verification acceptable for this release; document the expected error message string for future automation.
- Expected: Error state is visible; last valid job data still present in /jobhunter/my-jobs
- Roles: authenticated

### TC-12: Error messages — no raw stack traces exposed
- Description: Any error in the flow shows user-friendly messaging; no raw PHP stack traces in browser output.
- Suite: `jobhunter-e2e`
- Script: Inspect `report.consoleErrors` from Playwright run for stack trace patterns (`#[0-9]`, `Drupal\`, `/home/`); assert none present
- Expected: consoleErrors array contains no stack trace fragments
- Roles: authenticated

### TC-13: Job status transitions logged and reversible
- Description: Applying a job status transition records history; toggling back restores previous state.
- Suite: `jobhunter-e2e`
- Script: Extend script to un-check `have_applied` after checking it; verify job returns to un-applied state in /jobhunter/my-jobs
- Expected: Status reverts; no data corruption; history visible (if history UI exists)
- Roles: authenticated

### TC-14: Rollback — mid-sequence crash preserves last valid state
- Description: If a multi-step sequence crashes partway, the last successfully saved state is intact.
- Suite: `jobhunter-e2e`
- Automation flag: Requires simulated crash (e.g., kill browser mid-apply, then reload). Recommend manual verification for this release; Dev should confirm DB transaction boundaries in implementation notes. **Flagged to PM: risk acceptance or Dev-provided transaction boundary confirmation needed before release.**
- Expected: Prior saved job data still intact; no partial/corrupt records in /jobhunter/my-jobs
- Roles: authenticated

### TC-15: Anonymous redirect — no job data exposed (ACL)
- Description: Anonymous user accessing /jobhunter is redirected to login; no job data visible.
- Suite: `role-url-audit`
- qa-permissions.json rule: existing `jobhunter-dashboard` rule expected to return 403/redirect for anon
- Expected: HTTP 403 for anonymous on /jobhunter (already validated in role-url-audit run 20260226-133424)
- Roles: anon → deny; authenticated → allow; content_editor → allow; administrator → allow

### TC-16: Authenticated user sees only own jobs (data isolation)
- Description: Authenticated user cannot see another user's job entries.
- Suite: `jobhunter-e2e`
- Script: Requires two distinct authenticated sessions (two ULI URLs). Assert that user A's jobs do not appear in user B's /jobhunter/my-jobs list.
- Expected: Job count for user B = 0 when user A has jobs; no cross-uid data leakage
- Roles: authenticated (two users)
- Note: This requires qa_tester_authenticated_2 or equivalent — Dev/QA to discuss whether jhtr:qa-users-ensure can provision two authenticated users.

### TC-17: Admin role — /jobhunter access per existing permission model
- Description: Administrator role can access /jobhunter and admin-scoped pages without unexpected 403.
- Suite: `role-url-audit`
- qa-permissions.json rule: /jobhunter → allow for administrator
- Expected: HTTP 200 for administrator on /jobhunter (already covered by role-url-audit)
- Roles: administrator

## Automation flags summary (for PM)

| TC | Flag |
|----|------|
| TC-07 | Stage break assertion requires Playwright network interception extension — not yet implemented |
| TC-11 | Queue failure requires mock injection hook from Dev; manual for this release |
| TC-14 | Mid-sequence rollback requires manual verification; Dev should confirm transaction boundaries |
| TC-16 | Requires two distinct QA user sessions; needs jhtr:qa-users-ensure support for second user |

## Suite activation (Stage 0 only)
Do NOT edit `qa-suites/products/forseti/suite.json` until this feature is selected into release scope at Stage 0.

Planned suite additions at Stage 0:
- `jobhunter-e2e` suite command: `ULI_URL=<drush-user-login-url> BASE_URL=http://localhost node testing/jobhunter-workflow-step1-6-data-engineer.mjs`
- Role-url-audit additions: TC-15, TC-17 will map to new rules in qa-permissions.json if routes are not already covered.

### Acceptance criteria (reference)

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
- Agent: qa-forseti.life
- Status: pending
