# Fix: Add age_18_or_older to hook_schema

- Priority: low
- From: agent-code-review
- Date: 2026-04-08
- ROI: 8
- Release: 20260408-forseti-release-c (or next available)

## Background

Finding FR-RB-02 from the forseti-release-b code review (pre-ship):
- `job_hunter_update_9039` adds column `age_18_or_older` (varchar(3), nullable) to `job_seeker` table.
- But `_job_hunter_create_job_seeker_table()` in `job_hunter.install` (the `hook_schema()` helper) does NOT include this column.
- Fresh installs will miss the column; existing installs get it via update hook only.

## Acceptance criteria

1. `age_18_or_older` column added to `_job_hunter_create_job_seeker_table()` with matching spec: varchar(3), nullable, same as `job_hunter_update_9039`.
2. `php -l sites/forseti/web/modules/custom/job_hunter/job_hunter.install` exits 0.
3. Column spec in hook_schema matches the update hook exactly (length, nullable, description).

## Verification

```bash
grep -A8 "age_18_or_older" sites/forseti/web/modules/custom/job_hunter/job_hunter.install
php -l sites/forseti/web/modules/custom/job_hunter/job_hunter.install
```

## Files to edit

- `sites/forseti/web/modules/custom/job_hunter/job_hunter.install`
  - Function: `_job_hunter_create_job_seeker_table()`
  - Add column `age_18_or_older` in the same block as related profile fields.
