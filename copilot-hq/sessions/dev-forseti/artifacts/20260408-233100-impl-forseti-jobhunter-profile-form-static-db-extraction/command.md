# Implementation: forseti-jobhunter-profile-form-static-db-extraction

- Release: 20260408-forseti-release-k
- Feature: forseti-jobhunter-profile-form-static-db-extraction
- Module: job_hunter
- Priority: P3

## Task
Extract the 10 remaining `\Drupal::database()` static calls from `UserProfileForm.php` into the repository layer.

## Background
Release-j extracted the 2 `$this->database` property injection call sites. 10 pre-existing `\Drupal::database()` static calls were narrowed out of scope. This feature covers those remaining calls.

**Known locations in `UserProfileForm.php`:** lines 1483, 1792, 1838, 1889, 2023, 2722, 4541, 4907, 4937, 5175

## Approach
- Add extracted query methods to `UserProfileRepository.php` (preferred) or a new `UserProfileStaticQueryRepository.php`
- Inject the repository into `UserProfileForm` via constructor DI (already has `UserProfileRepository` injected from release-j)
- Replace each `\Drupal::database()` call with a repository method call

## Acceptance criteria
See `features/forseti-jobhunter-profile-form-static-db-extraction/01-acceptance-criteria.md`

## Done when
- AC-1: `grep -c '\\Drupal::database()' sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php` → 0
- AC-2: All 10 operations extracted to named repository methods
- AC-4: PHP lint clean
- Provide commit hash(es) and rollback notes

## File paths
- Form: `sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php`
- Repository: `sites/forseti/web/modules/custom/job_hunter/src/Repository/UserProfileRepository.php`
