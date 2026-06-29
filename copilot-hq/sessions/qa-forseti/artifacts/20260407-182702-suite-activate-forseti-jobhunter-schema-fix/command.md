# Suite Activation: forseti-jobhunter-schema-fix

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-07T18:27:02+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-schema-fix"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-schema-fix/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-schema-fix-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-schema-fix",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-schema-fix"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-schema-fix-<route-slug>",
     "feature_id": "forseti-jobhunter-schema-fix",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-schema-fix",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-schema-fix

- Feature: forseti-jobhunter-schema-fix
- QA owner: qa-forseti
- Status: groomed (next-release; activate at Stage 0)
- KB check: None found relevant to this specific fix.

## Scope

Fix the missing `age_18_or_older` column in the `job_hunter_profile` DB table.
`UserProfileForm.php` declares `db_column: 'age_18_or_older'` but the column does not exist,
causing silent failures on reads/writes. Dev must add a schema update hook in `job_hunter.install`.

## Suite mapping (activate at Stage 0)

All cases below are **drush/PHP functional tests** — NOT `role-url-audit` (no ACL changes).
They will live in the `jobhunter-e2e` suite as CLI/functional steps.
No `suite.json` or `qa-permissions.json` edits until Stage 0.

---

## Test Cases

### TC-01 — Column exists after fix

- Description: Schema inspection confirms `age_18_or_older` column exists in `job_hunter_profile`.
- Suite: `jobhunter-e2e` (CLI functional)
- Command:
  ```bash
  drush php-eval "print_r(\Drupal::database()->schema()->fieldExists('job_hunter_profile', 'age_18_or_older') ? 'COLUMN EXISTS' : 'COLUMN MISSING');"
  ```
- Expected: `COLUMN EXISTS`
- Roles covered: admin (drush)

### TC-02 — `drush updb` runs cleanly

- Description: Running `drush updb -y` after the fix applies the update hook without errors.
- Suite: `jobhunter-e2e` (CLI functional)
- Command:
  ```bash
  drush updb -y 2>&1
  ```
- Expected: exit 0; no PHP errors or warnings; update hook listed as applied successfully (or "No pending updates" on re-run).
- Roles covered: admin (drush)

### TC-03 — Field write persists boolean value

- Description: A `TRUE`/`FALSE` value written to `field_age_18_or_older` on a profile is saved without DB error.
- Suite: `jobhunter-e2e` (CLI functional)
- Command:
  ```bash
  drush php-eval "
    \$uid = \Drupal\user\Entity\User::load(1)->id();
    \$db = \Drupal::database();
    \$db->update('job_hunter_profile')
      ->fields(['age_18_or_older' => 1])
      ->condition('uid', \$uid)
      ->execute();
    print_r('WRITE OK');
  "
  ```
- Expected: `WRITE OK`; no DB exception thrown.
- Roles covered: admin (drush)

### TC-04 — Field read returns correct persisted value

- Description: After TC-03 write, reading `age_18_or_older` from the DB returns `1`.
- Suite: `jobhunter-e2e` (CLI functional)
- Command:
  ```bash
  drush php-eval "
    \$uid = \Drupal\user\Entity\User::load(1)->id();
    \$val = \Drupal::database()->select('job_hunter_profile', 'p')
      ->fields('p', ['age_18_or_older'])
      ->condition('uid', \$uid)
      ->execute()->fetchField();
    print_r('VALUE: ' . var_export(\$val, TRUE));
  "
  ```
- Expected: `VALUE: '1'` (or `1`); round-trip matches what was written.
- Roles covered: admin (drush)

### TC-05 — Existing NULL rows remain NULL (no data coercion)

- Description: Profiles with no value for `age_18_or_older` before the fix continue to return NULL after.
- Suite: `jobhunter-e2e` (CLI functional)
- Command:
  ```bash
  drush php-eval "
    \$count = \Drupal::database()->select('job_hunter_profile', 'p')
      ->isNull('p.age_18_or_older')
      ->countQuery()->execute()->fetchField();
    print_r('NULL rows: ' . \$count);
  "
  ```
- Expected: Count ≥ 0 with no exception; NULL rows unaffected (value is still NULL, not coerced to 0).
- Roles covered: admin (drush)

### TC-06 — No data loss on existing profiles

- Description: Total profile row count before and after `drush updb` is unchanged.
- Suite: `jobhunter-e2e` (CLI functional)
- Steps:
  1. Record `SELECT COUNT(*) FROM job_hunter_profile` before update.
  2. Run `drush updb -y`.
  3. Record count after.
- Expected: Before count == after count.
- Roles covered: admin (drush)

### TC-07 — Rollback path is clean

- Description: Reverting the install update hook and re-running `drush updb -y` restores prior state without errors.
- Suite: `jobhunter-e2e` (manual verification step — note to PM)
- Note to PM: Automated rollback is destructive in production. This case must be verified in a staging/backup environment or accepted as a manual-only test with documented steps. Recommend accepting risk in production if rollback procedure is documented in release notes.
- Steps (manual):
  1. Revert `job_hunter.install` update hook.
  2. Run `drush updb -y`.
  3. Verify no PHP errors; column may be absent (expected).
- Expected: Clean exit; no corrupt profile rows.
- Roles covered: admin (drush/manual)

### TC-08 — No ACL regressions

- Description: `role-url-audit` suite produces no new failures after schema fix is deployed.
- Suite: `role-url-audit` (existing — just rerun)
- Command:
  ```bash
  ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh
  ```
- Expected: 0 new violations; existing permission matrix in `qa-permissions.json` unchanged.
- Roles covered: anonymous, authenticated, admin

---

## Automation gaps / notes to PM

- TC-07 (rollback) cannot be safely automated against production. Recommend accepting as a manual step with documented rollback instructions in the release notes, or provisioning a staging clone for one-time verification.
- All other TCs (TC-01..TC-06, TC-08) are automatable as drush/CLI commands in the `jobhunter-e2e` suite.

### Acceptance criteria (reference)

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
