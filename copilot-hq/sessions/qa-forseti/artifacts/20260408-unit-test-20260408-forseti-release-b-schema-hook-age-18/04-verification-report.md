# Verification Report: forseti-release-b-schema-hook-age-18

- Feature: forseti-jobhunter-schema-fix
- Dev commit: 835d8290c
- QA run: 2026-04-08T12:57:00+00:00
- Site audit: 20260408-125738
- Verdict: **APPROVE**

## KB references
- None found relevant to schema hook verification.

## Evidence

### TC-01 — Column present in hook_schema
- Command: `grep -n "age_18_or_older" job_hunter.install`
- Result: Line 1051 — `'age_18_or_older' => ['type' => 'varchar', 'length' => 3, 'not null' => FALSE, ...]`
- Spec matches update hook 9039 (same type/length/default/description)
- **PASS**

### TC-02 — PHP lint clean
- Command: `php -l sites/forseti/web/modules/custom/job_hunter/job_hunter.install`
- Result: `No syntax errors detected`
- **PASS**

### TC-01b — Column exists in live DB (jobhunter_job_seeker)
- Command: `drush php-eval "\Drupal::database()->schema()->fieldExists('jobhunter_job_seeker', 'age_18_or_older')"`
- Result: `COLUMN EXISTS`
- Note: Prior QA run checked wrong table name (`job_hunter_profile`); correct table is `jobhunter_job_seeker` per install hook
- **PASS**

### TC-03 — Write persists
- Command: drush php-eval update jobhunter_job_seeker set age_18_or_older='Yes' where uid=1
- Result: `WRITE OK`
- **PASS**

### TC-04 — Read round-trip
- Command: drush php-eval select age_18_or_older from jobhunter_job_seeker where uid=1
- Result: `VALUE: 'Yes'`
- **PASS**

### TC-05 — NULL rows uncoerced
- Command: drush php-eval countQuery isNull('age_18_or_older')
- Result: `NULL rows: 2` (2 rows remain NULL, no coercion to 0)
- **PASS**

### TC-06 — Row count preserved (manual)
- Pre-update baseline: 3 rows (already post-update-hook environment)
- Count after verification: 3 rows
- **PASS** (no data loss observed)

### TC-07 — Rollback (manual-only)
- Accepted as manual-only per test plan; rollback path documented in install file via `fieldExists` guard
- Risk accepted by PM

### TC-08 — No ACL regressions
- Site audit 20260408-125738: 0 permission violations, 0 failures, 0 missing assets
- **PASS**

## Summary
All automatable TCs pass. Column `age_18_or_older` is present in `jobhunter_job_seeker` table (existing installs via update hook 9039 already applied; fresh installs now covered by hook_schema fix). Read/write round-trip confirmed. NULL rows preserved. No ACL regressions.
