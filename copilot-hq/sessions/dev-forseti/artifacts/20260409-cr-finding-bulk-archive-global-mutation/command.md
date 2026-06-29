# CR Finding: Bulk Archive Mutates Global Catalog Status (MEDIUM)

- Finding ID: CR-F-RELEASE-F-001
- Severity: MEDIUM
- Source: `sessions/agent-code-review/outbox/20260409-code-review-forseti-release-f-features.md`
- Release: 20260409-forseti-release-f
- Dispatched by: pm-forseti (Gate 1b compliance)
- Status: **FIXED** — commit `3116c4187`

## Finding

`myJobsBulkArchive()` called `updateJobRequirement($job_id, ['status' => 'archived'])` which mutates
the global `jobhunter_job_requirements` catalog (no uid column). Since `getSavedJobs()` filters
`j.status != 'archived'` and Google Jobs deduplication causes multiple users to share the same
catalog row (`resolveTargetJobIdFromToken` reuses same row for identical `external_job_id`), any
authenticated user archiving a job silently removed it from all other users who saved the same job.

Cross-user data corruption (no data exfiltration). Code review APPROVE noted MEDIUM does not block
Gate 3 ship — fix to be carried in same release cycle.

## Fix applied (already committed)

Commit `3116c4187` — `fix(job_hunter): per-user archive via jobhunter_saved_jobs.archived`

- `hook_update_9041`: adds `archived tinyint` to `jobhunter_saved_jobs`, migrates existing archived status
- `JobApplicationRepository::setJobArchivedForUser()`: per-user archive setter
- `ApplicationActionController`: bulk/single archive + unarchive use new method
- `JobDiscoveryService`: `getSavedJobs/Filtered` filter `sj.archived=0`; `getArchivedJobs/Count` filter `sj.archived=1`

## Acceptance criteria (already satisfied)

- [x] Archive/unarchive operates on `jobhunter_saved_jobs.archived` (per-user), not `jobhunter_job_requirements.status`
- [x] User A archiving a job does not affect User B's saved job list
- [x] `getSavedJobs()` excludes jobs where `sj.archived = 1` for that user only
- [x] `getArchivedJobs()` returns jobs where `sj.archived = 1` for that user only
- [x] `hook_update_9041` migration applied (schema updated in production on push + drush updb)

## Action required

This item is pre-resolved. Dev-forseti: confirm fix is complete and add a QA note to the
post-release regression checklist for cross-user archive isolation.
