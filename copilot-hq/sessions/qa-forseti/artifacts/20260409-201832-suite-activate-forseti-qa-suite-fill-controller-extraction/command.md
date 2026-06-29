# Suite Activation: forseti-qa-suite-fill-controller-extraction

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T20:18:32+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-qa-suite-fill-controller-extraction"`**  
   This links the test to the living requirements doc at `features/forseti-qa-suite-fill-controller-extraction/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-qa-suite-fill-controller-extraction-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-qa-suite-fill-controller-extraction",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-qa-suite-fill-controller-extraction"`**  
   Example:
   ```json
   {
     "id": "forseti-qa-suite-fill-controller-extraction-<route-slug>",
     "feature_id": "forseti-qa-suite-fill-controller-extraction",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-qa-suite-fill-controller-extraction",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-qa-suite-fill-controller-extraction

- Feature: forseti-qa-suite-fill-controller-extraction
- Module: qa_suites
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify static structural checks (Phase 1 refactor invariants) and regression routes (no 500s, ACL enforced) for the `JobApplicationController` extraction refactor. Both suites must have deterministic commands that exit 0 = PASS.

## Test cases

### TC-1: Manifest schema valid
- Steps: `python3 scripts/qa-suite-validate.py`
- Expected: exits 0 with "OK: validated N suite manifest(s)"

### TC-2: JobApplicationController has 0 direct DB calls
- Steps: `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php`
- Expected: 0

### TC-3: ApplicationSubmissionController has 0 direct DB calls
- Steps: `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php`
- Expected: 0

### TC-4: ApplicationActionController has 0 direct DB calls
- Steps: `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php`
- Expected: 0

### TC-5: JobApplicationRepository.php exists
- Steps: `test -f sites/forseti/web/modules/custom/job_hunter/src/Repository/JobApplicationRepository.php && echo PASS`
- Expected: PASS

### TC-6: Repository registered in services.yml
- Steps: `grep -c 'job_hunter.job_application_repository' sites/forseti/web/modules/custom/job_hunter/job_hunter.services.yml`
- Expected: ≥ 1

### TC-7: ApplicationSubmissionController injects repository via container
- Steps: `grep -c "job_hunter.job_application_repository" sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php`
- Expected: ≥ 1

### TC-8: static suite command exits 0 (all 7 AC-STATIC checks)
- Prereq: `DRUPAL_ROOT` set or running from `/home/ubuntu/forseti.life`
- Steps: Run the `forseti-jobhunter-controller-extraction-phase1-static` suite command
- Expected: exits 0

### TC-9: Anonymous → 403/302 on application-submission route (no 500)
- Prereq: `FORSETI_BASE_URL` set; Drupal running
- Steps: `curl -o /dev/null -s -w "%{http_code}" "${FORSETI_BASE_URL}/jobhunter/application-submission"`
- Expected: 403 or 302 (not 500)

### TC-10: Authenticated → 200 on application-submission route (no 500)
- Prereq: `FORSETI_BASE_URL` and `FORSETI_COOKIE_AUTHENTICATED` set with `access job hunter` user
- Steps: `curl -b "..." -o /dev/null -s -w "%{http_code}" "${FORSETI_BASE_URL}/jobhunter/application-submission"`
- Expected: 200 (not 500 or 403)

### TC-11: regression suite command exits 0 (all 7 Phase 1 routes)
- Prereq: `FORSETI_BASE_URL` and `FORSETI_COOKIE_AUTHENTICATED` set
- Steps: Run the `forseti-jobhunter-controller-extraction-phase1-regression` suite command
- Expected: exits 0; no route returns 500

## Regression notes
- `python3 scripts/qa-suite-validate.py` must exit 0 after suite.json changes
- CompanyController, GoogleJobsSearchController, ResumeController are NOT in Phase 1 scope; their DB calls are expected and must not appear as failures in this suite
- ApplicationSubmissionRouteTest.php functional tests must remain unaffected

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-qa-suite-fill-controller-extraction

- Feature: forseti-qa-suite-fill-controller-extraction
- Module: qa_suites
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Populate 2 suites in `qa-suites/products/forseti/suite.json` for the controller-extraction-phase1 feature: a static structural suite (grep-based) verifying the refactor constraints are still in place, and a regression suite verifying key routes return expected status codes. The Phase 1 refactor is already shipped — `JobApplicationController` is now an empty deprecated placeholder, and `ApplicationSubmissionController` / `ApplicationActionController` inject `job_hunter.job_application_repository` via the service container (`$container->get('job_hunter.job_application_repository')`). Static suite ACs must verify these structural invariants remain intact.

## Context

**Current state (verified in codebase):**
- `JobApplicationController.php` = empty deprecated placeholder class body (0 DB calls, no routes)
- `ApplicationSubmissionController.php` = page renders; injects `job_hunter.job_application_repository` via `create()` / `ContainerInterface`; 0 direct `$this->database` calls
- `ApplicationActionController.php` = AJAX/action endpoints; same injection pattern; 0 direct `$this->database` calls
- `JobApplicationRepository.php` = DI-registered at `job_hunter.job_application_repository` with `arguments: ['@database']`
- Other controllers (CompanyController, GoogleJobsSearchController, ResumeController, etc.) are **not in Phase 1 scope** — they may still have direct DB calls

**Key structural invariants the static suite must assert:**
1. `JobApplicationController` has 0 direct `$this->database` calls
2. `ApplicationSubmissionController` has 0 direct `$this->database` calls
3. `ApplicationActionController` has 0 direct `$this->database` calls
4. `JobApplicationRepository.php` exists
5. `job_hunter.job_application_repository` is registered in `job_hunter.services.yml`
6. `ApplicationSubmissionController::create()` retrieves repository via `$container->get('job_hunter.job_application_repository')`
7. `ApplicationActionController::create()` retrieves repository via `$container->get('job_hunter.job_application_repository')`

---

## Suite 1: forseti-jobhunter-controller-extraction-phase1-static

**Purpose:** Grep-based structural checks to confirm Phase 1 refactor constraints are maintained and not regressed by future commits.

**Command approach:** A single Python or bash script running all checks in sequence; exits 0 if all pass, exits 1 with failing check name if any fail.

### AC-STATIC-01: JobApplicationController has zero direct DB calls
- Check: `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/JobApplicationController.php` → 0
- If > 0: exit 1 with message "FAIL: JobApplicationController has DB calls after Phase 1 extraction"
- Expected: exit 0 (count = 0)

### AC-STATIC-02: ApplicationSubmissionController has zero direct DB calls
- Check: `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php` → 0
- If > 0: exit 1 with message "FAIL: ApplicationSubmissionController has direct DB calls (should use repository)"
- Expected: exit 0 (count = 0)

### AC-STATIC-03: ApplicationActionController has zero direct DB calls
- Check: `grep -c '\$this->database' sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php` → 0
- If > 0: exit 1 with message "FAIL: ApplicationActionController has direct DB calls (should use repository)"
- Expected: exit 0 (count = 0)

### AC-STATIC-04: JobApplicationRepository.php file exists
- Check: `test -f sites/forseti/web/modules/custom/job_hunter/src/Repository/JobApplicationRepository.php`
- If absent: exit 1 with message "FAIL: JobApplicationRepository.php not found"
- Expected: exit 0 (file present)

### AC-STATIC-05: job_hunter.job_application_repository registered in services.yml
- Check: `grep -c 'job_hunter.job_application_repository' sites/forseti/web/modules/custom/job_hunter/job_hunter.services.yml` → ≥ 1
- Expected: exit 0 (count ≥ 1)

### AC-STATIC-06: ApplicationSubmissionController injects repository via container
- Check: `grep -c "job_hunter.job_application_repository" sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php` → ≥ 1
- Expected: exit 0 (confirms `$container->get('job_hunter.job_application_repository')` present in `create()`)

### AC-STATIC-07: ApplicationActionController injects repository via container
- Check: `grep -c "job_hunter.job_application_repository" sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php` → ≥ 1
- Expected: exit 0 (confirms repository injection in `create()`)

---

## Suite 2: forseti-jobhunter-controller-extraction-phase1-regression

**Purpose:** Confirm that key application submission and discovery routes still return expected status codes after the Phase 1 controller extraction. Detects behavioral regressions.

**Routes to test (from routing.yml, all require `access job hunter`):**

| Route | Path | Method | Expected anon | Expected auth |
|---|---|---|---|---|
| `job_hunter.dashboard` | `/jobhunter` | GET | 403 or 302 | 200 |
| `job_hunter.my_jobs` | `/jobhunter/my-jobs` | GET | 403 or 302 | 200 |
| `job_hunter.application_submission` | `/jobhunter/application-submission` | GET | 403 or 302 | 200 |
| `job_hunter.application_submission_job` | `/jobhunter/application-submission/{job_id}` | GET | 403 or 302 | 200 |
| `job_hunter.application_submission_step3` | `/jobhunter/application-submission/{job_id}/identify-auth-path` | GET | 403 or 302 | 200 |
| `job_hunter.application_submission_step4` | `/jobhunter/application-submission/{job_id}/create-account` | GET | 403 or 302 | 200 |
| `job_hunter.application_submission_step5` | `/jobhunter/application-submission/{job_id}/submit-application` | GET | 403 or 302 | 200 |

Use `job_id=99999` for parameterized routes; controllers return graceful 200 for non-existent IDs.

### AC-REGR-01: Anonymous → 403/302 on all Phase 1 GET routes (no 500s)
- Command: For each route path, `curl -o /dev/null -s -w "%{http_code}" ${FORSETI_BASE_URL}{path}` with no session cookie
- Expected: Each returns 403 or 302; none returns 500
- Exit 0 = all pass; exit 1 = any route returns 500 (regression indicator)
- Verification note: 200 for anon is also a failure (ACL regression); treat 200 as exit 1

### AC-REGR-02: Authenticated → 200 on all Phase 1 GET routes (no 500s)
- Command: For each route path, GET with `FORSETI_COOKIE_AUTHENTICATED` (user with `access job hunter`)
- Expected: Each returns 200; none returns 500 or 403
- Exit 0 = all pass; exit 1 = any route returns 500 or 403 (regression)
- Verification note: The critical regression signal is 500 — that indicates the extraction broke a service dependency. 403 for an authenticated user with the permission is also a regression.

### AC-REGR-03: No 500 errors in watchdog after anonymous crawl of Phase 1 routes
- Command: After running AC-REGR-01 curl passes, `vendor/bin/drush watchdog:show --count=20 --severity=error --format=json | python3 -c "import json,sys; rows=json.load(sys.stdin); phase1_errors=[r for r in rows if 'application-submission' in str(r) or 'JobApplication' in str(r)]; assert not phase1_errors, f'{len(phase1_errors)} Phase 1 errors in watchdog'"` (from Drupal root)
- Expected: 0 errors for application-submission or JobApplicationController paths; exit 0

### AC-REGR-04: No 500 errors on authenticated step page (step3 known-good path)
- Command: GET `/jobhunter/application-submission/99999/identify-auth-path` with admin cookie; confirm 200 response body contains HTML (not PHP error output)
- Expected: HTTP 200; response body contains `<html` or `<!DOCTYPE`; does not contain "PHP Fatal error" or "Symfony\Component\HttpKernel\Exception"; exit 0

---

## Definition of done

- [ ] Both suites added to (or confirmed present as entries in) `qa-suites/products/forseti/suite.json` with the static suite having a deterministic multi-check command
- [ ] Static suite command: runs all 7 AC-STATIC checks; exits 0 if all pass, exits 1 with specific FAIL message
- [ ] Regression suite command: hits all 7 Phase 1 GET routes; exits 0 when no 500s and ACL is enforced
- [ ] `python3 scripts/qa-suite-validate.py` exits 0 after suite.json changes

## Verification

- Static suite run: `python3 qa-suites/products/forseti/run-controller-extraction-static.py` (or equivalent inline command) exits 0 against the installed codebase at `DRUPAL_ROOT`
- Regression run: `FORSETI_BASE_URL=http://localhost FORSETI_COOKIE_AUTHENTICATED=<value>` and run the regression suite command; confirm exit 0
- Schema check: `python3 scripts/qa-suite-validate.py` exits 0

## Security acceptance criteria

### Authentication/permission surface
- All Phase 1 routes require `_permission: 'access job hunter'`; regression suite verifies anon=403/302, authenticated=200 (AC-REGR-01, AC-REGR-02)

### CSRF expectations
- POST wizard step routes have `_csrf_token: 'TRUE'` (covered by `forseti-jobhunter-application-submission-route-acl` suite); regression suite targets GET routes only

### Input validation requirements
- Static suite confirms no raw DB calls in controllers; all queries now go through `JobApplicationRepository` which receives a typed `Connection` dependency (AC-STATIC-01 through AC-STATIC-03)

### PII/logging constraints
- AC-REGR-03 watchdog check confirms no application-submission errors are emitted during normal traversal; no credential or user data expected in error logs for 403/graceful-404 paths

## Open questions

| OQ | Question | Recommendation |
|---|---|---|
| OQ-1 | The suite stub refers to "repository injected via services.yml" but the controllers use `$container->get(...)` in `create()` (standard Drupal DI pattern, not a separately declared service entry for the controller itself). Should the static check target `services.yml` (repository registration) or the controller `create()` method (injection point)? | Recommend: check both — services.yml registration (AC-STATIC-05) AND controller `create()` method (AC-STATIC-06, AC-STATIC-07). Both are invariants that should hold. |
| OQ-2 | Scope question: CompanyController (42 DB calls), GoogleJobsSearchController (9), ResumeController (14), JobHunterHomeController (4) all still have direct DB calls. Are these Phase 2 targets? Should the static suite explicitly exclude them, or only assert the Phase 1 scope files? | Recommend: static suite checks only Phase 1 scope files (JobApplicationController, ApplicationSubmissionController, ApplicationActionController). Add a comment in the suite noting other controllers are Phase 2. |
- Agent: qa-forseti
- Status: pending
