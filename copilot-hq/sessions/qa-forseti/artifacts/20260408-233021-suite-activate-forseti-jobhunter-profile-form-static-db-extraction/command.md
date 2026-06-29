# Suite Activation: forseti-jobhunter-profile-form-static-db-extraction

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-08T23:30:21+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-profile-form-static-db-extraction"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-profile-form-static-db-extraction/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-profile-form-static-db-extraction-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-profile-form-static-db-extraction",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-profile-form-static-db-extraction"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-profile-form-static-db-extraction-<route-slug>",
     "feature_id": "forseti-jobhunter-profile-form-static-db-extraction",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-profile-form-static-db-extraction",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

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
