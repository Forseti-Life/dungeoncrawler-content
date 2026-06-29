# Test Plan Design: forseti-jobhunter-schema-fix

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-05T21:07:14+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-jobhunter-schema-fix/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-jobhunter-schema-fix "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/forseti/suite.json`
- Do NOT edit `org-chart/sites/forseti.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
- Agent: qa-forseti
- Status: pending
