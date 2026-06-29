# Feature: forseti-jobhunter-profile-form-db-extraction

- Feature ID: forseti-jobhunter-profile-form-db-extraction
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Priority: P2
- Release: 20260408-forseti-release-j

## Summary
Extract the 3 direct `$this->database` calls from `UserProfileForm.php` (6104 lines) into the existing `UserProfileService` or a new `UserProfileRepository`. This continues the DB-layer separation pattern established in release-i.

## Problem
`UserProfileForm.php` contains direct `$this->database` calls at lines 127 (constructor injection stored), 1701 (update), and 3074 (select). Forms should not contain direct DB access — logic belongs in a service or repository layer.

## Acceptance criteria
- AC-1: `grep -c '->database(' sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php` → 0
- AC-2: DB logic extracted to `UserProfileRepository.php` or added to `UserProfileService.php` (whichever is architecturally cleaner)
- AC-3: All extracted methods have matching public method on the service/repository
- AC-4: `php -l` passes on all modified files
- AC-5: Site audit 0 failures, 0 violations post-implementation

## Security acceptance criteria
- Authentication/permission surface: No change — form already requires `authenticated` role. DB reads/writes operate on user-owned data only.
- CSRF expectations: Form uses standard Drupal form API `#token` — no change needed. No POST routes added.
- Input validation requirements: All inputs already validated via Drupal form validators. Extraction must not bypass existing validators.
- PII/logging constraints: User profile data (resume, profile fields) must not be logged. No change to logging behavior.

## Rollback
- `git revert` to previous commit of `UserProfileForm.php` and any new service/repository file.

## Verification method
- Static: `grep -c '->database(' ...UserProfileForm.php` → 0
- `php -l` on modified files
- Site audit clean
