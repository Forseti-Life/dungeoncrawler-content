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
