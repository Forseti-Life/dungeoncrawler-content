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
