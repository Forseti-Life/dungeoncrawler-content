# Test Plan: forseti-agent-tracker-dashboard-controller-db-extraction

**QA owner:** qa-forseti
**Release:** 20260408-forseti-release-j

## Test cases

### TC-1: No direct database calls in DashboardController
- **Type:** static analysis
- **Command:** `grep -c '->database(' sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php`
- **Expected:** 0
- **Pass criteria:** Output is exactly `0`

### TC-2: PHP syntax clean on DashboardController
- **Type:** static
- **Command:** `php -l sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php`
- **Expected:** No syntax errors detected
- **Pass criteria:** Exit 0

### TC-3: PHP syntax clean on DashboardRepository
- **Type:** static
- **Command:** `php -l sites/forseti/web/modules/custom/copilot_agent_tracker/src/Repository/DashboardRepository.php`
- **Expected:** No syntax errors
- **Pass criteria:** Exit 0

### TC-4: Dashboard page renders post-refactor
- **Type:** smoke
- **Method:** HTTP GET to `/admin/copilot-agent-tracker` as admin user
- **Expected:** HTTP 200, dashboard content visible
- **Pass criteria:** 200, no PHP error in response or watchdog

### TC-5: Site audit clean
- **Type:** static analysis
- **Command:** `./vendor/bin/drush site:audit`
- **Expected:** 0 failures, 0 violations
- **Pass criteria:** Audit exit 0 or known pre-existing issues only
