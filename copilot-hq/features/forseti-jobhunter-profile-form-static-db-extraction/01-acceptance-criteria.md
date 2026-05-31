# Acceptance Criteria: forseti-jobhunter-profile-form-static-db-extraction

## AC-1 — No static \Drupal::database() calls in UserProfileForm

**Given** `UserProfileForm.php` is inspected,
**When** `grep -c '\\Drupal::database()' sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php` is run,
**Then** result is 0.

**Verification:** `grep -c '\\Drupal::database()' .../UserProfileForm.php` → 0

**Known locations (10 calls as of 2026-04-08):** lines 1483, 1792, 1838, 1889, 2023, 2722, 4541, 4907, 4937, 5175

## AC-2 — Extracted methods exist on repository layer

**Given** the 10 extracted static DB operations are refactored,
**When** the refactor is complete,
**Then** all extracted operations exist as named methods on `UserProfileRepository.php` or a new `UserProfileStaticQueryRepository.php`.

**Verification:** Code review confirms public methods present on the target repository.

## AC-3 — No raw user input passed to DB without sanitization

**Given** the repository methods receive query parameters,
**When** those methods are reviewed,
**Then** all parameters are sanitized before use in database queries (placeholders, typed casts, or entity IDs).

**Verification:** Code review of each extracted method.

## AC-4 — PHP syntax passes on all modified files

**Verification:** `php -l` on `UserProfileForm.php` and any new/modified repository files → no errors

## AC-5 — Site audit passes post-implementation

**Verification:** Site audit returns 0 failures, 0 violations after implementation.

## PM scope decision (origin)
Follow-on from `forseti-jobhunter-profile-form-db-extraction` (release-j). PM scope decision 2026-04-08 narrowed the parent feature to 2 `$this->database` calls; this feature covers the remaining 10 `\Drupal::database()` static calls.
