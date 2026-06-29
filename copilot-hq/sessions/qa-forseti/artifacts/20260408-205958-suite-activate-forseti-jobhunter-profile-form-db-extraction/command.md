# Suite Activation: forseti-jobhunter-profile-form-db-extraction

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-08T20:59:58+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-profile-form-db-extraction"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-profile-form-db-extraction/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-profile-form-db-extraction-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-profile-form-db-extraction",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-profile-form-db-extraction"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-profile-form-db-extraction-<route-slug>",
     "feature_id": "forseti-jobhunter-profile-form-db-extraction",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-profile-form-db-extraction",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-profile-form-db-extraction

**QA owner:** qa-forseti
**Release:** 20260408-forseti-release-j

## Test cases

### TC-1: No direct database calls in UserProfileForm
- **Type:** static analysis
- **Command:** `grep -c '->database(' sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php`
- **Expected:** 0
- **Pass criteria:** Output is exactly `0`

### TC-2: PHP syntax clean on UserProfileForm
- **Type:** static
- **Command:** `php -l sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php`
- **Expected:** No syntax errors detected
- **Pass criteria:** Exit 0, message contains "No syntax errors"

### TC-3: PHP syntax clean on new service/repository file
- **Type:** static
- **Command:** `php -l sites/forseti/web/modules/custom/job_hunter/src/Repository/UserProfileRepository.php` (or equivalent path)
- **Expected:** No syntax errors
- **Pass criteria:** Exit 0

### TC-4: Site renders without error post-refactor
- **Type:** smoke
- **Method:** HTTP GET to `/jobhunter/profile` as authenticated user
- **Expected:** HTTP 200, profile form visible
- **Pass criteria:** 200, no PHP error in response or watchdog

### TC-5: Site audit clean
- **Type:** static analysis
- **Command:** `./vendor/bin/drush site:audit` (or `drush audit:modules`)
- **Expected:** 0 failures, 0 violations
- **Pass criteria:** Audit exit 0 or known pre-existing issues only

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-profile-form-db-extraction

## AC-1 — No direct database calls in UserProfileForm

**Given** `UserProfileForm.php` is inspected,
**When** `grep -c '->database(' sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php` is run,
**Then** result is 0.

**Verification:** `grep -c '->database(' .../UserProfileForm.php` → 0

## AC-2 — DB logic extracted to service/repository layer

**Given** the 3 extracted DB operations (constructor injection, update at ~1701, select at ~3074),
**When** the refactor is complete,
**Then** all 3 operations exist as named methods on `UserProfileRepository.php` or `UserProfileService.php`.

**Verification:** Code review confirms public methods present on the target service/repository.

## AC-3 — All extracted methods have matching public interface

**Given** the service/repository receives the extracted methods,
**When** `UserProfileForm.php` calls them,
**Then** each call is through a properly injected dependency (not static `\Drupal::service()`).

**Verification:** Code review — no `\Drupal::database()` or `\Drupal::service('database')` in UserProfileForm.

## AC-4 — PHP syntax passes on all modified files

**Verification:** `php -l sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php` → no errors

## AC-5 — Site audit passes post-implementation

**Verification:** `drush site:audit` → 0 failures, 0 violations
- Agent: qa-forseti
- Status: pending
