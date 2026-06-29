# Verification Report: forseti-jobhunter-profile-form-db-extraction

- Feature: forseti-jobhunter-profile-form-db-extraction
- Release: 20260408-forseti-release-j
- QA seat: qa-forseti
- Dev commit: c664d0b47
- Date: 2026-04-08 (re-verified 2026-04-08 after PM scope decision)

## Verdict: APPROVE

All 5 ACs pass against the updated AC-3 scope. PM narrowed AC-3 (2026-04-08) to cover only
the 2 `$this->database` property call sites targeted by this extraction — both confirmed removed.
The 10 pre-existing `\Drupal::database()` static calls are out of scope and tracked as follow-on
work in `features/forseti-jobhunter-profile-form-static-db-extraction/`.

## AC Results

| AC | Description | Result | Evidence |
|----|-------------|--------|----------|
| AC-1 | 0 `->database(` calls in UserProfileForm | PASS | grep count = 0 |
| AC-2 | UserProfileRepository exists with extracted methods | PASS | 3 public methods confirmed |
| AC-3 (updated) | No `$this->database` property calls in UserProfileForm | PASS | grep count = 0; 2 targeted call sites removed |
| AC-4 | PHP lint clean on modified files | PASS | `php -l` → no errors on both files |
| AC-5 | Site audit 0 failures, 0 violations | PASS | audit 2026-04-08 22:06 UTC: 0 failures, 0 violations |

## Suite Results

| Suite entry | Result |
|-------------|--------|
| forseti-jobhunter-profile-form-db-extraction-static | PASS |
| forseti-jobhunter-profile-form-db-extraction-functional | PASS (`/jobhunter/profile` → 403) |
| forseti-jobhunter-profile-form-db-extraction-regression | PASS (audit 2026-04-08 22:06 clean) |

## Prior BLOCK — resolved

First pass was BLOCK because the original AC-3 text said "no `\Drupal::database()` in UserProfileForm"
which covered 10 pre-existing static calls outside dev scope. PM narrowed AC-3 to match actual
extraction scope (2 `$this->database` property calls). Both are now confirmed removed. BLOCK lifted.

## Out-of-scope note

10 `\Drupal::database()` static calls remain at lines 1483, 1792, 1838, 1889, 2023, 2722, 4541,
4907, 4937, 5175 — tracked as follow-on `forseti-jobhunter-profile-form-static-db-extraction`.

## KB Reference
- No prior lessons found for this pattern.
