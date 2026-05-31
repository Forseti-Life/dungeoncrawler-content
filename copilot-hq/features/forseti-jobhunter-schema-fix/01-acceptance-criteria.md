# Acceptance Criteria (PM-owned)
# Feature: forseti-jobhunter-schema-fix

## Gap analysis reference

Gap analysis in `feature.md` is complete. All criteria are `[EXTEND]` — existing field definition corrected.

## Happy Path

- [ ] `[EXTEND]` `field_age_18_or_older` can be written with a boolean value and the value persists in the DB without error.
- [ ] `[EXTEND]` `field_age_18_or_older` can be read back and returns the correct persisted value.
- [ ] `[EXTEND]` Drupal schema inspection (`drush php-eval "print_r(\Drupal::database()->schema()->fieldExists('job_hunter_profile', 'age_18_or_older') ? 'exists' : 'missing');"`) confirms the column exists.

## Edge Cases

- [ ] `[EXTEND]` Existing rows with NULL for `field_age_18_or_older` continue to return NULL (no data coercion).
- [ ] `[EXTEND]` Update hook (or schema correction) runs cleanly on an existing installation (`drush updb -y` returns no errors).

## Failure Modes

- [ ] `[EXTEND]` If the column was missing, `drush updb` adds it without corrupting existing profile rows.

## Permissions / Access Control

- [ ] `[TEST-ONLY]` No ACL changes — field access follows existing job_hunter profile permissions.

## Data Integrity

- [ ] No data loss on existing profiles during schema update.
- [ ] Rollback path: revert `job_hunter.install` update hook and run `drush updb` to restore prior state.

## Knowledgebase check

- None found relevant to this specific fix.

## Verification method

```bash
# After applying the fix:
cd /var/www/html/forseti && vendor/bin/drush updb -y && vendor/bin/drush cr
vendor/bin/drush php-eval "print_r(\Drupal::database()->schema()->fieldExists('job_hunter_profile', 'age_18_or_older') ? 'COLUMN EXISTS' : 'COLUMN MISSING');"
# Expected: COLUMN EXISTS
```
