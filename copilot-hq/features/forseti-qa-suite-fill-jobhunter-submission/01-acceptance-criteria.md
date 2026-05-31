# Acceptance Criteria: forseti-qa-suite-fill-jobhunter-submission

- Feature: forseti-qa-suite-fill-jobhunter-submission
- Module: qa_suites
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Populate 2 suites in `qa-suites/products/forseti/suite.json` for the application-submission feature: a route ACL audit verifying all submission routes enforce `access job hunter` permission, and a PHPUnit unit suite for `WorkdayWizardService` referencing the existing test class. Each suite must have a deterministic `command` (exit 0 = PASS) and the route ACL suite must explicitly enumerate all submission paths and expected role responses.

## Context

- Existing suite.json entries for both suites are already present in `qa-suites/products/forseti/suite.json`.
- The `forseti-jobhunter-application-submission-unit` entry has an **incorrect path**: `web/modules/custom/job_hunter/tests/src/Unit/WorkdayWizardServiceTest.php` — the actual file is at `web/modules/custom/job_hunter/tests/src/Unit/Service/WorkdayWizardServiceTest.php`. The "STAGE 0 PENDING" note is **outdated** — the test file exists and has 15 test methods.
- ACL coverage is split between GET routes (wizard step views) and POST routes (wizard step actions with `_csrf_token: 'TRUE'`).

---

## Suite 1: forseti-jobhunter-application-submission-route-acl

**Purpose:** Verify all application-submission routes enforce `_permission: 'access job hunter'` — anonymous users receive 403 (or 302 redirect), authenticated users with the permission receive 200.

**Implementation note:** Suite command should drive coverage via `qa-permissions.json` rules tagged `feature_id: forseti-jobhunter-application-submission`, backed by `scripts/site-audit-run.sh`. A fixture `job_id` (e.g., `99999`) is used for parameterized paths; controllers return graceful 200 for unknown job_id without requiring DB fixture.

### AC-ACL-01: Anonymous → 403/302 on all GET wizard step routes

Routes covered (GET, all require `access job hunter`):

| Route ID | Path |
|---|---|
| `job_hunter.application_submission` | `/jobhunter/application-submission` |
| `job_hunter.application_submission_job` | `/jobhunter/application-submission/{job_id}` |
| `job_hunter.application_submission_short` | `/application-submission` |
| `job_hunter.application_submission_job_short` | `/application-submission/{job_id}` |
| `job_hunter.application_submission_step2` | `/jobhunter/application-submission/{job_id}/resolve-redirect-chain` |
| `job_hunter.application_submission_step3` | `/jobhunter/application-submission/{job_id}/identify-auth-path` GET |
| `job_hunter.application_submission_step4` | `/jobhunter/application-submission/{job_id}/create-account` GET |
| `job_hunter.application_submission_step5` | `/jobhunter/application-submission/{job_id}/submit-application` GET |

Expected for each: HTTP 403 or 302 when no session cookie is present. Exit 0 = PASS; 200 = exit 1 (ACL bypass).

Verification: `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/jobhunter/application-submission` returns 403 or 302.

### AC-ACL-02: Anonymous → 403/302 on all POST wizard step routes

POST routes covered (all have `_csrf_token: 'TRUE'` and `_permission: 'access job hunter'`):

| Route ID | Path |
|---|---|
| `job_hunter.application_submission_step3_post` | `/jobhunter/application-submission/{job_id}/identify-auth-path` POST |
| `job_hunter.application_submission_step3_short_post` | `/application-submission/{job_id}/identify-auth-path` POST |
| `job_hunter.application_submission_step4_post` | `/jobhunter/application-submission/{job_id}/create-account` POST |
| `job_hunter.application_submission_step4_short_post` | `/application-submission/{job_id}/create-account` POST |
| `job_hunter.application_submission_step5_post` | `/jobhunter/application-submission/{job_id}/submit-application` POST |
| `job_hunter.application_submission_step5_short_post` | `/application-submission/{job_id}/submit-application` POST |
| `job_hunter.application_submission_step_stub_post` | `/jobhunter/application-submission/{job_id}/step/{step}` POST |

Expected for each: HTTP 403 (no session = no auth, CSRF check does not reach permission check). Exit 0 = PASS.

Verification: `curl -X POST -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}/jobhunter/application-submission/99999/identify-auth-path` returns 403.

### AC-ACL-03: Authenticated user with permission → 200 on GET wizard step routes
- User holds `access job hunter` permission
- Expected: HTTP 200 for all GET routes listed in AC-ACL-01 (with fixture `job_id=99999`)
- Exit 0 = PASS; 403 = exit 1 (regression)
- Verification: `ApplicationSubmissionRouteTest::testStep2AllowsAuthenticatedUser` etc. cover this at the PHPUnit functional level; route audit must also confirm for the installed module at `${FORSETI_BASE_URL}`

### AC-ACL-04: Authenticated user without permission → 403 on GET routes
- User is logged in but does NOT hold `access job hunter`
- Expected: HTTP 403 for all GET routes
- Verification: `FORSETI_COOKIE_AUTHENTICATED` (non-job-hunter user) + curl check; or `ApplicationSubmissionRouteTest` extended to cover this case

---

## Suite 2: forseti-jobhunter-application-submission-unit

**Purpose:** Verify `WorkdayWizardService` business logic via PHPUnit unit tests covering `advanceStep()` happy path, failure modes, and `advanceWizardAutoSingleSession()` sequence.

**File:** `web/modules/custom/job_hunter/tests/src/Unit/Service/WorkdayWizardServiceTest.php`

**IMPORTANT path correction:** The suite.json command currently references the wrong path (`tests/src/Unit/WorkdayWizardServiceTest.php`). The correct path is `tests/src/Unit/Service/WorkdayWizardServiceTest.php`. The STAGE 0 PENDING note is outdated — the file exists with 15 test methods.

### AC-UNIT-01: advanceStep() happy path returns success result
- Method: `testAdvanceStepHappyPathReturnsSuccessResult`
- Scenario: Valid step key, non-null job_id, application record exists, credentials available, runner returns success
- Expected: Returns array with `ok = true`; no PHP exception thrown
- Verification: PHPUnit `--testdox` output shows `✔ Advance step happy path returns success result`

### AC-UNIT-02: advanceStep() invalid step key returns structured error
- Method: `testAdvanceStepInvalidStepKeyReturnsStructuredError`
- Scenario: Step key not in registered wizard steps
- Expected: Returns structured error array (not exception); `ok = false`
- Verification: PHPUnit `--testdox` confirms pass

### AC-UNIT-03: advanceStep() null job_id returns structured error
- Method: `testAdvanceStepNullJobIdReturnsStructuredError`
- Scenario: `null` passed as `job_id`
- Expected: Returns structured error; no exception; no DB query attempted
- Verification: PHPUnit `--testdox` confirms pass

### AC-UNIT-04: advanceStep() runner timeout returns structured error
- Method: `testAdvanceStepRunnerTimesOutReturnsStructuredError`
- Scenario: `WorkdayPlaywrightRunner` mock returns a timeout result
- Expected: Returns structured failure with timeout indicator; no exception propagates
- Verification: PHPUnit `--testdox` confirms pass

### AC-UNIT-05: advanceStep() missing credentials returns error
- Method: `testAdvanceStepMissingCredentialsReturnsError`
- Scenario: Credential store returns null/empty for the job context
- Expected: Returns structured error; no call made to external ATS
- Verification: PHPUnit `--testdox` confirms pass

### AC-UNIT-06: advanceStep() ATS 503 simulation returns structured failure
- Method: `testAdvanceStepRunnerSimulates503ReturnsStructuredFailure`
- Scenario: Runner mock simulates external ATS returning 503
- Expected: Returns structured failure with ATS-unavailable status; no PHP fatal; no exception propagated to caller
- Verification: PHPUnit `--testdox` confirms pass

### AC-UNIT-07: advanceWizardAutoSingleSession() happy path returns success
- Method: `testAdvanceWizardAutoHappyPathReturnsSuccessResult`
- Scenario: All steps succeed; runner mock returns success for each
- Expected: Returns success result; runner invoked for each step; exit 0 in PHPUnit run
- Verification: PHPUnit `--testdox` confirms pass

### AC-UNIT-08: advanceWizardAutoSingleSession() called twice invokes runner twice
- Method: `testAdvanceWizardAutoCalledTwiceInvokesRunnerTwice`
- Scenario: Same service instance called with same job_id twice
- Expected: Runner invoked twice (no idempotent short-circuit at service level); each call produces a result
- Verification: PHPUnit `--testdox` confirms pass

### AC-UNIT-09: runner receives credential payload
- Method: `testAdvanceStepRunnerReceivesCredentialPayload`
- Scenario: Valid context; runner mock asserts it receives the expected credential structure
- Expected: Runner mock's `expects($this->once())` assertion passes; credentials not logged
- Verification: PHPUnit `--testdox` confirms pass

---

## Definition of done

- [ ] `forseti-jobhunter-application-submission-route-acl` suite command: runs against `${FORSETI_BASE_URL}` and exits 0 when all submission routes enforce access control correctly
- [ ] Route ACL suite explicitly covers: all GET wizard routes (anon=403/302, auth=200), all 7 POST wizard routes (anon=403)
- [ ] `forseti-jobhunter-application-submission-unit` suite command updated to correct path: `web/modules/custom/job_hunter/tests/src/Unit/Service/WorkdayWizardServiceTest.php`
- [ ] Unit suite `run_notes` updated: remove "STAGE 0 PENDING" — the file exists
- [ ] PHPUnit test class runs clean: `vendor/bin/phpunit web/modules/custom/job_hunter/tests/src/Unit/Service/WorkdayWizardServiceTest.php --testdox` exits 0 with all 15 methods passing
- [ ] `python3 scripts/qa-suite-validate.py` exits 0 after suite.json corrections

## Verification

- ACL suite: run suite command; confirm exit 0; check artifacts JSON shows anon=DENY, auth=ALLOW for all submission paths
- Unit suite path fix: `test -f sites/forseti/web/modules/custom/job_hunter/tests/src/Unit/Service/WorkdayWizardServiceTest.php && echo EXISTS`
- PHPUnit run (from Drupal root `/var/www/html/forseti`): `vendor/bin/phpunit web/modules/custom/job_hunter/tests/src/Unit/Service/WorkdayWizardServiceTest.php --testdox`
- Schema validation: `python3 scripts/qa-suite-validate.py` exits 0

## Security acceptance criteria

### Authentication/permission surface
- All submission routes require `_permission: 'access job hunter'`; anonymous users receive 403 or 302 on all GET and POST routes (AC-ACL-01, AC-ACL-02)

### CSRF expectations
- All POST wizard step routes have `_csrf_token: 'TRUE'` in routing.yml (split-route pattern); GET routes have no CSRF token requirement; anonymous POST to any wizard step returns 403 before CSRF check even runs (AC-ACL-02)

### Input validation requirements
- `advanceStep()` with invalid step key, null job_id, missing credentials, and oversized/unexpected runner results all return structured errors without PHP exceptions (AC-UNIT-02 through AC-UNIT-06)

### PII/logging constraints
- `testAdvanceStepRunnerReceivesCredentialPayload` verifies credential payload passed to runner, not logged; unit suite must confirm credential struct is used only within runner scope (AC-UNIT-09)

## Open questions / implementation flags

| OQ | Question | Recommendation |
|---|---|---|
| OQ-1 | suite.json has wrong PHPUnit path (`tests/src/Unit/WorkdayWizardServiceTest.php`). Should qa-forseti fix the path in the next release, or does pm-forseti want a separate suite.json fix task dispatched to dev-forseti? | Recommend: qa-forseti corrects the path as part of implementing this suite (content-scope fix, not a code change). |
| OQ-2 | "7 POST routes" in command.md — actual count from routing.yml is 7 canonical POST routes (step3 through step_stub, `/jobhunter/` prefix only; short variants are aliases). Recommend: ACL suite tests the `/jobhunter/` prefix paths (canonical) and the `/application-submission/` short aliases separately to confirm both aliases enforce ACL. | Confirm with qa-forseti during implementation. |
