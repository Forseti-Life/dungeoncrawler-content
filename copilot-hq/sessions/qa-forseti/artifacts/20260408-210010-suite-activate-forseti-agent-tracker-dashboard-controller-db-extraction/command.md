# Suite Activation: forseti-agent-tracker-dashboard-controller-db-extraction

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-08T21:00:10+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-agent-tracker-dashboard-controller-db-extraction"`**  
   This links the test to the living requirements doc at `features/forseti-agent-tracker-dashboard-controller-db-extraction/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-agent-tracker-dashboard-controller-db-extraction-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-agent-tracker-dashboard-controller-db-extraction",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-agent-tracker-dashboard-controller-db-extraction"`**  
   Example:
   ```json
   {
     "id": "forseti-agent-tracker-dashboard-controller-db-extraction-<route-slug>",
     "feature_id": "forseti-agent-tracker-dashboard-controller-db-extraction",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-agent-tracker-dashboard-controller-db-extraction",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-agent-tracker-dashboard-controller-db-extraction

## AC-1 — No direct database calls in DashboardController

**Given** `DashboardController.php` is inspected,
**When** `grep -c '->database(' sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php` is run,
**Then** result is 0.

**Verification:** `grep -c '->database(' .../DashboardController.php` → 0

## AC-2 — New DashboardRepository contains all extracted DB logic

**Given** all 20 direct `$this->database` calls are extracted,
**When** the refactor is complete,
**Then** a `DashboardRepository.php` (or equivalent service) contains all DB methods.

**Verification:** Code review confirms repository file exists with all extracted queries.

## AC-3 — Controller injects repository via DI

**Given** the repository is created,
**When** DashboardController is inspected,
**Then** the repository is injected via constructor injection with `ContainerInjectionInterface`.

**Verification:** Code review — no `\Drupal::database()` calls remain in DashboardController.

## AC-4 — PHP syntax passes on all modified files

**Verification:** `php -l` on DashboardController.php and new repository file → no errors

## AC-5 — Site audit clean post-implementation

**Verification:** `drush site:audit` → 0 failures, 0 violations

## AC-6 — Dashboard pages render without error

**Verification:** HTTP GET `/admin/copilot-agent-tracker` → HTTP 200, dashboard content visible
