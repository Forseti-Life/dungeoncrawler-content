# Fix: Bulk archive mutates global catalog status (MEDIUM — cross-user data corruption)

- From: agent-code-review
- Release: 20260409-forseti-release-f
- ROI: 8
- Priority: dispatch for next release cycle (does not block release-f Gate 3 ship)

## Problem

`myJobsBulkArchive()` in `ApplicationActionController` calls:

```php
$this->repository->updateJobRequirement($job_id, ['status' => 'archived']);
```

`updateJobRequirement()` updates `jobhunter_job_requirements.status` — a global, uid-free catalog table — with no per-user constraint.

`getSavedJobs()` (used by `myJobs()`) excludes archived rows by default:

```php
$query->condition('j.status', 'archived', '!=');
```

Since the Google Jobs save flow deduplicates by `external_job_id` (same external job → same catalog row shared across users), User A archiving a job silently removes it from the My Jobs view for every other user who saved the same job.

## Acceptance criteria

1. Archiving a job via bulk archive or single-archive only affects the calling user's view — it must NOT change the `jobhunter_job_requirements.status` column.
2. Other users who saved the same job continue to see it in their My Jobs pipeline.
3. The user who archived a job no longer sees it in their My Jobs pipeline.

## Recommended fix

Move archived status tracking to `jobhunter_saved_jobs`:

1. Add an `archived` column (tinyint, default 0) to `jobhunter_saved_jobs` via `hook_update_N`.
2. Update `updateJobRequirement` callers in bulk-archive and single-archive paths to instead update `jobhunter_saved_jobs.archived = 1` WHERE uid = current_user AND job_id = $job_id.
3. Update `getSavedJobs()` (and `getSavedJobsFiltered()`) to filter `sj.archived != 1` instead of `j.status != 'archived'`.
4. Update `hook_schema()` for `jobhunter_saved_jobs` to include the new `archived` column.

## Affected files
- `sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationActionController.php` — `myJobsBulkArchive()`, `archiveJob()`
- `sites/forseti/web/modules/custom/job_hunter/src/Repository/JobApplicationRepository.php` — add `setJobArchivedForUser(int $uid, int $job_id, bool $archived): void`
- `sites/forseti/web/modules/custom/job_hunter/src/Service/JobDiscoveryService.php` — update `getSavedJobs()` filter
- `sites/forseti/web/modules/custom/job_hunter/job_hunter.install` — new `hook_update_N`, update `hook_schema()`

## Verification
- Two user accounts both save the same Google Job
- User A archives it → User A's My Jobs no longer shows it
- User B's My Jobs still shows it
- `php -l` clean, `drush cr` success
