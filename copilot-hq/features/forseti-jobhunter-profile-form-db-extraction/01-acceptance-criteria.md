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

**Verification:** Code review — no `$this->database` property calls remain in UserProfileForm (the 2 `$this->database` call sites targeted by this extraction are removed). Pre-existing `\Drupal::database()` static calls elsewhere in the file are out of scope for this extraction and tracked as follow-on work in `features/forseti-jobhunter-profile-form-static-db-extraction/`.

**PM scope decision (2026-04-08):** The dev task was explicitly scoped to "Extract 3 DB calls from UserProfileForm" targeting the 2 `$this->database` property injection call sites. 10 pre-existing `\Drupal::database()` static calls remain but were not part of the original scope. AC-3 narrowed to match actual dev scope. Follow-on task queued for next release.

## AC-4 — PHP syntax passes on all modified files

**Verification:** `php -l sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php` → no errors

## AC-5 — Site audit passes post-implementation

**Verification:** `drush site:audit` → 0 failures, 0 violations
