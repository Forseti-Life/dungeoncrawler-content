# Implementation Notes: forseti-jobhunter-follow-up-reminders

- Feature: forseti-jobhunter-follow-up-reminders
- Author: ba-forseti / dev-forseti
- Date: 2026-04-13
- Status: implemented — commit 8486ab6cf

## Approach

`follow_up_date` column already exists on `jobhunter_saved_jobs` (varchar(10), YYYY-MM-DD). The date picker UI already exists in `viewJob()` (CompanyController.php ~line 1454) and `deadlineSave()` already saves the value with ownership + format validation (SEC-3, SEC-4). No new schema hooks, routes, or tables were needed.

**What was missing:**
1. `getSavedJobs()` in `JobDiscoveryService.php` did not include `sj.follow_up_date` in the result set.
2. `myJobs()` in `ApplicationSubmissionController.php` did not compute the overdue flag.
3. `my-jobs.html.twig` had no badge rendering.

## Storage decision

Used existing `follow_up_date` column on `jobhunter_saved_jobs` — already the canonical owner for per-saved-job tracking metadata (deadline_date lives there too). No new table needed.

## Changes made

### `JobDiscoveryService.php` — `getSavedJobs()`
Added two fields to the SELECT query:
- `sj.follow_up_date` aliased as `follow_up_date`
- `sj.deadline_date` aliased as `sj_deadline_date` (available if needed)

### `ApplicationSubmissionController.php` — `myJobs()`
In the per-job derivation loop, after setting `workflow_status`, compute:
```php
$fu_date = (string) ($job->follow_up_date ?? '');
$advanced = in_array($job->workflow_status, ['interview', 'closed'], TRUE);
$job->follow_up_overdue = ($fu_date !== '' && $fu_date < $today_str && !$advanced);
```
YYYY-MM-DD string comparison is safe and avoids timezone complexity.

### `my-jobs.html.twig`
- `<tr>` gains `job-row--follow-up-overdue` class when `job.follow_up_overdue` is truthy.
- Title cell renders `<span class="follow-up-overdue">⏰ Follow up overdue</span>` badge inline.
- CSS added: amber badge style matching the module's existing badge palette.

## AC coverage

| AC | Status |
|---|---|
| AC-1: Follow-up date picker visible in detail view | Pre-existing ✅ |
| AC-2: Overdue badge on my-jobs | ✅ implemented |
| AC-3: Badge clears when advanced status or future date | ✅ computed at render time |
| AC-4: Date can be cleared (saves NULL) | Pre-existing in deadlineSave() ✅ |
| AC-5: Data scoped to current user | ✅ getSavedJobs() scopes to uid |
| SEC-1: Login required | Pre-existing route requirement ✅ |
| SEC-2: CSRF on POST | Pre-existing split-route on deadline_save ✅ |
| SEC-3: Ownership check before save | Pre-existing in deadlineSave() ✅ |
| SEC-4: Date-only storage, no text | ✅ |
| SEC-5: Minimal logging | No new logging added ✅ |

## Verification targets

```bash
# Overdue badge appears
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -q 'follow-up-overdue'

# Verify DB value
cd /var/www/html/forseti && vendor/bin/drush sql:query \
  "SELECT id, follow_up_date FROM jobhunter_saved_jobs WHERE uid=<uid> AND follow_up_date IS NOT NULL"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
