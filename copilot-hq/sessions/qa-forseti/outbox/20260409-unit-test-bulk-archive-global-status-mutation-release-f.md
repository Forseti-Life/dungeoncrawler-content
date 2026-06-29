- Status: done
- Summary: Verified Dev fix for bulk-archive global status mutation (commits 55ab8337c + 3116c4187). All four changed files pass PHP lint. `hook_update_9041` correctly adds `archived tinyint(1)` to `jobhunter_saved_jobs` with migration of existing rows (no DDL on catalog table). `setJobArchivedForUser(uid, job_id, bool)` scopes UPDATE to both `uid` AND `job_id` — no cross-user mutation possible. `myJobsBulkArchive()` additionally verifies ownership via `findSavedJobMappingId($uid, $job_id)` before calling the repository. `archiveJob()`/`unarchiveJob()` use `currentUser()->id()` as uid. `getSavedJobs()` and `getSavedJobsFiltered()` filter `sj.archived = 0`; `getArchivedJobs()` / `getArchivedJobsCount()` filter `sj.archived = 1` — no reads from `j.status` for archive state. The `updateJobRequirement()` call remaining in the controller (line 545) is for the applied/not-applied toggle, not archive — confirmed out of scope. Anon GET `/jobhunter/my-jobs` → 403; anon POST `/jobhunter/my-jobs/bulk-archive` → 403. Site audit 20260409-135804: 0 failures, 0 violations, 191 paths. CSRF baseline unchanged at 39.

## Verdict
APPROVE — bulk-archive-global-status-mutation-release-f

## Checks performed
| Check | Result |
|---|---|
| PHP lint — JobApplicationRepository | PASS |
| PHP lint — ApplicationActionController | PASS |
| PHP lint — JobDiscoveryService | PASS |
| PHP lint — job_hunter.install | PASS |
| hook_update_9041: archived column on jobhunter_saved_jobs | PASS |
| setJobArchivedForUser: uid+job_id scoped | PASS |
| myJobsBulkArchive: ownership guard before archive | PASS |
| getArchivedJobs/getSavedJobs: filter sj.archived, not j.status | PASS |
| No global catalog status mutation in archive path | PASS |
| Anon GET /jobhunter/my-jobs → 403 | PASS |
| Anon POST /jobhunter/my-jobs/bulk-archive → 403 | PASS |
| Site audit 20260409-135804 | 0 failures / 0 violations |
| CSRF baseline | 39 (unchanged) |

## Next actions
- Inbox empty — awaiting dispatch.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 12
- Rationale: This fix eliminates a data integrity bug where archiving a saved job mutated the catalog entry for all users. Verification closes the last outstanding release-f QA item.
