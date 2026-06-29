# Feature: forseti-jobhunter-profile-form-static-db-extraction

- Feature id: forseti-jobhunter-profile-form-static-db-extraction
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Priority: P3
- Release: TBD (next forseti release after release-j)
- Created: 2026-04-08 (follow-on from forseti-jobhunter-profile-form-db-extraction PM scope decision)

## Summary
Extract or replace the 10 remaining `\Drupal::database()` static calls in `UserProfileForm.php`. These were out of scope for the release-j extraction (which targeted only the 2 `$this->database` property injection call sites). These are pre-existing calls scattered throughout the 6100-line form.

## Background
During QA verification of `forseti-jobhunter-profile-form-db-extraction` (release-j), qa-forseti found 10 `\Drupal::database()` static calls remaining at lines: 1483, 1792, 1838, 1889, 2023, 2722, 4541, 4907, 4937, 5175. These were narrowed out of scope by PM decision (2026-04-08); see `features/forseti-jobhunter-profile-form-db-extraction/01-acceptance-criteria.md` AC-3.

## Acceptance criteria
- AC-1: `grep -c '\\Drupal::database()' .../UserProfileForm.php` → 0
- AC-2: All extracted methods exist on `UserProfileRepository.php` or a new `UserProfileStaticQueryRepository.php`
- AC-3: PHP lint clean on all modified files
- AC-4: Site audit passes post-implementation (0 failures, 0 violations)

## Security acceptance criteria
- Authentication/permission surface: No new routes; form is already auth-gated
- CSRF expectations: No CSRF surface changes
- Input validation requirements: Repository methods must not accept raw user input without sanitization
- PII/logging constraints: No PII in watchdog/logs

## Verification
- `grep -c '\\Drupal::database()' sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php` → 0
- `php -l` on modified files → clean
