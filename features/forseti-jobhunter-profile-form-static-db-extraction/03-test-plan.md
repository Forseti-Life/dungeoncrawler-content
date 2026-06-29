# Test Plan: forseti-jobhunter-profile-form-static-db-extraction

- Feature: forseti-jobhunter-profile-form-static-db-extraction
- Module: job_hunter
- QA seat: qa-forseti
- Test type: static + functional + regression

## Static checks

### TC-1 — No \Drupal::database() in UserProfileForm
- Command: `grep -c '\\Drupal::database()' sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php`
- Expected: `0`
- FAIL if: count > 0

### TC-2 — PHP lint clean
- Command: `php -l sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php`
- Expected: no errors
- Also lint any new/modified repository files

### TC-3 — Repository methods exist
- Check: `UserProfileRepository.php` or `UserProfileStaticQueryRepository.php` contains public methods corresponding to each extracted query
- Minimum: 10 new/updated methods

## Functional checks

### TC-4 — Profile form loads without 500
- URL: `/jobhunter/profile` (authenticated)
- Expected: 200 (or 403 if anonymous, as per site policy)
- FAIL if: 500

### TC-5 — Site audit post-implementation
- Command: `bash scripts/site-audit-run.sh` (with `ALLOW_PROD_QA=1`)
- Expected: 0 failures, 0 violations
- FAIL if: new failures appear vs. pre-release baseline

## Regression check

### TC-6 — No regression on profile-form-db-extraction scope
- Command: `grep -c '->database(' sites/forseti/web/modules/custom/job_hunter/src/Form/UserProfileForm.php`
- Expected: 0 (must not re-introduce injected calls)

## Reference
- Pre-release baseline audit: `sessions/qa-forseti/artifacts/auto-site-audit/20260408-220624/`
- Parent feature AC: `features/forseti-jobhunter-profile-form-db-extraction/01-acceptance-criteria.md`
