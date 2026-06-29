# Suite Activation: forseti-jobhunter-application-controller-split

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T04:19:12+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-application-controller-split"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-application-controller-split/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-application-controller-split-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-application-controller-split",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-application-controller-split"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-application-controller-split-<route-slug>",
     "feature_id": "forseti-jobhunter-application-controller-split",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-application-controller-split",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-application-controller-split

- Feature: forseti-jobhunter-application-controller-split
- Author: pm-forseti (scaffold) — qa-forseti to review/extend
- Date: 2026-04-09
- KB reference: Phase 1 test approach in `features/forseti-jobhunter-application-controller-db-extraction/`

## Test scope

Pure structural refactor: verify that all routes, permissions, and HTTP behavior are identical after the controller split. No new behavior to test.

## Test cases

### TC-1: Route smoke test (functional)
- Precondition: `drush cr` succeeds after the split is committed.
- Action: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/` must return 200.
- Action: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/my-jobs` must return 403 or 302 (anonymous).
- Expected: same HTTP status codes as before the split.

### TC-2: Syntax validation (unit)
- Action: `php -l web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php`
- Action: `php -l web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php`
- Expected: both exit 0, no parse errors.

### TC-3: Zero DB calls (static analysis)
- Action: `grep -c '\$this->database' src/Controller/ApplicationSubmissionController.php`
- Action: `grep -c '\$this->database' src/Controller/ApplicationActionController.php`
- Expected: both return 0.

### TC-4: CSRF configuration preserved (static)
- Action: `grep -c "_csrf_token" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml`
- Expected: same count as recorded in implementation notes before refactor.

### TC-5: Cache rebuild (functional)
- Action: `drush cr`
- Expected: exits 0, no PHP fatal in watchdog (`drush watchdog:show --count=5 --severity=Error`).

### TC-6: Line count constraint (static)
- Action: `wc -l src/Controller/ApplicationSubmissionController.php`
- Action: `wc -l src/Controller/ApplicationActionController.php`
- Expected: both ≤ 800 lines.

## STAGE 0 PENDING
All test cases above are pending implementation. Dev must record pre-refactor baseline counts (CSRF token count, route list) in implementation notes before beginning the split.

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-application-controller-split

- Feature: forseti-jobhunter-application-controller-split
- Author: pm-forseti
- Date: 2026-04-09
- KB reference: Phase 1 context in `features/forseti-jobhunter-application-controller-db-extraction/`

## Summary

Structural split of `JobApplicationController.php` (4177 lines) into two controllers. Pure refactor — no behavior changes, no new DB calls, all existing routes and permissions preserved.

## Acceptance criteria

### AC-1: Controller files created
- `ApplicationSubmissionController.php` exists at `web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php`.
- `ApplicationActionController.php` exists at `web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php`.
- `JobApplicationController.php` is either removed or reduced to a thin delegating stub ≤ 50 lines.

### AC-2: Route correctness
- All routes in `job_hunter.routing.yml` previously pointing to `JobApplicationController::*` now point to the correct split controller method.
- Zero broken routes: `drush router:debug | grep job_hunter` must show all expected routes.

### AC-3: Line count
- Each new controller file is ≤ 800 lines.

### AC-4: Syntax clean
- `php -l web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php` exits 0.
- `php -l web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php` exits 0.

### AC-5: Zero new DB calls
- `grep -c '\$this->database' web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php` = 0
- `grep -c '\$this->database' web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php` = 0

### AC-6: Auth/permission surface unchanged
- Authenticated GET on `/jobhunter/my-jobs` returns 200.
- Anonymous GET on `/jobhunter/my-jobs` returns 403 or redirect (no change from current).
- Smoke: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/my-jobs` = 403 anonymous.

### AC-7: CSRF tokens preserved
- All POST routes that previously used the split-route pattern (`_csrf_token: TRUE` on POST-only entry) retain that configuration in `job_hunter.routing.yml`. No collapse of split routes.
- Verify: `grep -A5 "_csrf_token" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml` returns same count as before.

### AC-8: Cache rebuild passes
- `drush cr` exits 0 with no PHP fatal errors in watchdog after the split.

## Non-goals (explicitly out of scope)
- Business logic extraction (Phase 3 deferred)
- Test file creation (QA follow-on)
- Any new feature additions
