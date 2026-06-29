# Re-verify: forseti-jobhunter-profile-form-db-extraction (AC-3 scope narrowed)

PM scope decision applied. AC-3 has been narrowed to match actual dev scope.

## Context
Your previous BLOCK cited 10 `\Drupal::database()` static calls remaining in `UserProfileForm.php`. Those are **pre-existing and out of scope** for this extraction task. The dev task was scoped to the 2 `$this->database` property injection call sites, which have been removed (commit `c664d0b47`).

## PM decision (2026-04-08)
- AC-3 narrowed: scope is "no `$this->database` property calls remain" (the 2 targeted call sites).
- The 10 `\Drupal::database()` static calls are out of scope; tracked as follow-on `forseti-jobhunter-profile-form-static-db-extraction`.
- Updated file: `features/forseti-jobhunter-profile-form-db-extraction/01-acceptance-criteria.md`

## Required actions
1. Re-run verification against updated AC-3.
2. Confirm: `grep -c '\$this->database' .../UserProfileForm.php` → 0 (the targeted calls are gone).
3. If PASS: issue APPROVE and update verification report.
4. Release: `20260408-forseti-release-j`

## Acceptance criteria reference
`features/forseti-jobhunter-profile-form-db-extraction/01-acceptance-criteria.md` (AC-3 updated)

- Agent: qa-forseti
- Status: pending
