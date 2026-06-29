# Test Plan — forseti-jobhunter-application-submission

- Feature: WorkdayWizardService — 5-step Workday portal automation (Phase 3 tracking)
- QA owner: qa-forseti
- Date: 2026-04-05
- Release target: 20260402-forseti-release-b (grooming; NOT added to suite.json yet)
- AC source: features/forseti-jobhunter-application-submission/01-acceptance-criteria.md

## Knowledgebase references
- `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — attempt logging must use correct `job_seeker_id` (profile PK, not raw UID); critical for all log-record assertions (TC-02, TC-05, TC-07, TC-10).
- `features/forseti-jobhunter-browser-automation/01-acceptance-criteria.md` — WorkdayWizardService is Phase 3 of browser automation; test cases extend that scope.

## Suite assignments
- Route/ACL tests → `role-url-audit` suite
- Unit service logic → `WorkdayWizardServiceTest.php` (PHPUnit, to be created at Stage 0)
- Functional route smoke test → `tests/src/Functional/` (PHPUnit, to be created at Stage 0)

## Test cases

### TC-01 — advanceStep(): success result, no PHP error
- **AC:** Happy Path — advanceStep() with valid job context and mock runner
- **Suite:** Unit (`WorkdayWizardServiceTest.php`)
- **Description:** Invoke `WorkdayWizardService::advanceStep()` with a valid job context object and a mock Playwright runner configured to return success. Assert return value is a success result (not null, not exception).
- **Expected behavior:** Non-null success result returned; no PHP fatal or uncaught exception; no HTTP 500 on any user-facing surface.
- **Roles covered:** authenticated (backend unit)

### TC-02 — advanceWizardAutoSingleSession(): full step sequence, log records with correct job_seeker_id
- **AC:** Happy Path — advanceWizardAutoSingleSession() advances all steps; each step logs with correct job_seeker_id
- **Suite:** Unit (`WorkdayWizardServiceTest.php`)
- **Description:** Invoke `advanceWizardAutoSingleSession()` with a mock runner that returns success for each step. After each step, assert a run-history record is written with: `job_seeker_id` = profile PK (NOT Drupal UID), correct strategy/step identifier, non-null timestamp, status = success.
- **Expected behavior:** N log records written (one per step); all have correct `job_seeker_id`; no exception thrown.
- **Roles covered:** authenticated (backend unit)
- **KB note:** Use `jobhunter_job_seeker.id` for `job_seeker_id`, not `$account->id()`. Per KB `20260220-forseti-jobhunter-uid-vs-jobseeker-id.md`.

### TC-03 — WorkdayPlaywrightRunner credential retrieval
- **AC:** Happy Path — credential payload received from credential store
- **Suite:** Unit (`WorkdayWizardServiceTest.php`)
- **Description:** Inject a mock credential store into `WorkdayPlaywrightRunner`. Assert the runner receives the expected credential payload (service name + username + encrypted value) without error.
- **Expected behavior:** Credential payload matches fixture; no PHP error; no credential exposed in logs.
- **Roles covered:** authenticated (backend unit)

### TC-04 — application_submission routes: authenticated user receives HTTP 200
- **AC:** Permissions/Access Control — 5 application_submission_* routes → 200 for authenticated
- **Suite:** `role-url-audit` + Functional (`tests/src/Functional/`)
- **Description:** GET each of the 5 primary `application_submission_*` route paths as authenticated user with `access job hunter` permission. Assert HTTP 200.
- **Routes to test (parameterized, use a test job_id fixture):**
  - `/jobhunter/application-submission`
  - `/jobhunter/application-submission/{job_id}`
  - `/jobhunter/application-submission/{job_id}/resolve-redirect-chain`
  - `/jobhunter/application-submission/{job_id}/identify-auth-path`
  - `/jobhunter/application-submission/{job_id}/create-account`
  - `/jobhunter/application-submission/{job_id}/submit-application`
  - `/jobhunter/application-submission/{job_id}/step/{step}`
- **Expected HTTP status:** 200 (or 302 to a valid follow-on page) for authenticated
- **Roles covered:** authenticated → allow; administrator → allow
- **Note:** AC says "5 routes" but routing.yml shows more; coverage target is all routes registered by WorkdayWizardService. Confirm exact 5 with Dev at Stage 0 if needed.

### TC-05 — application_submission routes: anonymous user receives HTTP 403
- **AC:** Permissions/Access Control — all routes → 403 for anon
- **Suite:** `role-url-audit`
- **Description:** GET each `application_submission_*` route path without a session.
- **Expected HTTP status:** 403 or redirect to login; final destination must not be 200.
- **Roles covered:** anon → deny

### TC-06 — advanceWizardAutoSingleSession(): Playwright timeout/failure logged, structured error returned
- **AC:** Edge Case — Playwright timeout/non-zero exit
- **Suite:** Unit (`WorkdayWizardServiceTest.php`)
- **Description:** Mock `WorkdayPlaywrightRunner` to return a timeout/failure status. Assert: (a) a failure log record is written with `job_seeker_id` and failure status, (b) the method returns a structured error object (not null, not exception), (c) no user-facing PHP fatal.
- **Expected behavior:** Structured error returned; failure record in DB; no propagated exception.
- **Roles covered:** authenticated (backend unit)

### TC-07 — Missing/expired credentials: user-facing error, no external ATS access
- **AC:** Edge Case — missing or expired credentials
- **Suite:** Unit (`WorkdayWizardServiceTest.php`)
- **Description:** Inject a mock credential store that returns null/expired. Assert: (a) method returns a user-facing error message (not an exception), (b) no call is made to external ATS (assert `WorkdayPlaywrightRunner::run()` is NOT invoked), (c) log record written with credential-missing status.
- **Expected behavior:** Error message in return value; no external call; no PHP fatal.
- **Roles covered:** authenticated (backend unit)

### TC-08 — Duplicate session invocation: no duplicate submission records
- **AC:** Edge Case — duplicate call for same job
- **Suite:** Unit (`WorkdayWizardServiceTest.php`)
- **Description:** Invoke `advanceWizardAutoSingleSession()` twice with the same job context. Assert: no duplicate submission record is created (idempotency enforced via de-duplication or session guard).
- **Expected behavior:** DB has at most one submission record per job+session; second call returns an appropriate status (e.g., "already in progress" or deduplication); no crash.
- **Roles covered:** authenticated (backend unit)

### TC-09 — External ATS unavailable (mock 503): structured failure logged, no user exception
- **AC:** Failure Mode — Workday ATS returns 503
- **Suite:** Unit (`WorkdayWizardServiceTest.php`)
- **Description:** Mock `WorkdayPlaywrightRunner` to simulate a 503 response from the ATS. Assert: (a) runner returns structured failure with ATS-unavailable status code, (b) wizard logs the failure with that status code, (c) no exception propagates to the calling layer.
- **Expected behavior:** Log record has ATS-unavailable code; structured failure returned; no HTTP 500 visible to user.
- **Roles covered:** authenticated (backend unit)

### TC-10 — Invalid job context (null job_id): structured error returned, no PHP exception
- **AC:** Failure Mode — null job_id passed to advanceStep()
- **Suite:** Unit (`WorkdayWizardServiceTest.php`)
- **Description:** Call `advanceStep()` with a null `job_id`. Assert method returns a structured error response (array or object with error code/message), not a PHP exception or fatal.
- **Expected behavior:** Structured error return; no uncaught exception; no HTTP 500.
- **Roles covered:** authenticated (backend unit)

### TC-11 — Each advanceStep() writes exactly one log record per call; no orphaned records
- **AC:** Data Integrity — exactly one log record per step, no orphaned records on rollback
- **Suite:** Unit (`WorkdayWizardServiceTest.php`)
- **Description:** Mock runner configured for success. Call `advanceStep()` once per step. Assert exactly N records in DB (one per call). Then simulate a mid-sequence failure; assert no orphaned/partial records from failed step.
- **Expected behavior:** Record count matches call count; no extra rows; failed step cleanup confirmed.
- **Roles covered:** authenticated (backend unit)
- **Note:** Orphan cleanup may be implemented via DB transaction. Dev should confirm in implementation notes.

### TC-12 — Rollback path: disabling WorkdayWizardService restores prior behavior
- **AC:** Data Integrity — rollback via service disable
- **Suite:** Manual verification (Dev-confirmed)
- **Automation flag:** This test requires toggling the service call and verifying end-to-end flow reverts. Not automatable without a feature-flag mechanism. **Flagged to PM: Dev should document the exact rollback procedure in implementation notes; manual verification at Gate 2 is acceptable.**
- **Expected behavior:** With service call commented out, existing browser-automation behavior is restored; no schema changes needed; no startup errors.
- **Roles covered:** backend / operational

## AC items that cannot be expressed as automation

| AC item | Reason | Note to PM |
|---|---|---|
| Dev gap-fill: update `02-implementation-notes.md` to reference `7dea91e8f` | Dev task, not QA testable | Confirm with Dev before Gate 2 — missing notes = incomplete handoff artifact |
| Dev gap-fill: incomplete timeout handling in `WorkdayPlaywrightRunner` | Dev task — QA verifies the fix via TC-06/TC-09 | QA will test outcome, not the implementation detail |
| TC-12 rollback path | Requires service-call toggle; no feature-flag API | Manual verification at Gate 2; Dev documents rollback procedure |

## Suite activation checklist (Stage 0 only)

Do NOT edit `qa-suites/products/forseti/suite.json` or `qa-permissions.json` until this feature is selected into scope at Stage 0.

At Stage 0:
- [ ] Create `web/modules/custom/job_hunter/tests/src/Unit/WorkdayWizardServiceTest.php` with TC-01..TC-11 (mock runner + credential store)
- [ ] Create `web/modules/custom/job_hunter/tests/src/Functional/ApplicationSubmissionRouteTest.php` with TC-04..TC-05
- [ ] Add `application_submission_*` route rules to `qa-permissions.json` (authenticated: allow, anon: deny)
- [ ] Confirm exact list of 5 target routes with Dev and update qa-permissions.json accordingly
- [ ] Wire PHPUnit suites into `qa-suites/products/forseti/suite.json`
