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
