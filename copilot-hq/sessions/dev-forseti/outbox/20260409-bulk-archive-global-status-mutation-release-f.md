Now let me look at the exact archive/unarchive controller code and getSavedJobs code I need to change:

Now I have a clear picture. Let me also check the hook_schema for saved_jobs:

Now I have everything I need. Let me implement all 4 files in parallel:

**Step 1: Add `hook_update_9041` to install file** (adds `archived` column to `jobhunter_saved_jobs` + migrates existing archived records):

Now add `hook_update_9041` after `9040`:

**Step 2: Add `setJobArchivedForUser()` to `JobApplicationRepository`:**

**Step 3: Update controller — replace `updateJobRequirement(...archived/active)` with `setJobArchivedForUser()`:**

**Step 4: Update `JobDiscoveryService` — `getSavedJobs`, `getSavedJobsFiltered`, `getArchivedJobs`, `getArchivedJobsCount`:**

Wait, I accidentally removed the platform filter too. Let me check the current state and fix:

The platform filter was removed. Need to restore it:

Now fix `getSavedJobsFiltered`:

Now fix `getArchivedJobs()` and `getArchivedJobsCount()`:

Now verify lint, run the DB update, and rebuild cache:

There's an extra `}` on line 996. Let me fix it:

Column added. Now commit:

The auto-checkpoint at 55ab8337c already captured my changes. The files were committed via the auto-checkpoint. Let me verify all 4 files:

All changes are in commit `55ab8337c` (auto-checkpoint). Let me make a clean labeled commit now:

Done. Here's the outbox update:

---

- Status: done
- Summary: Fixed cross-user archive corruption in Job Hunter My Jobs. Added `archived` tinyint column to `jobhunter_saved_jobs` via `hook_update_9041` (migrates existing catalog-archived rows). Added `JobApplicationRepository::setJobArchivedForUser(uid, job_id, bool)`. Updated `myJobsBulkArchive()`, `archiveJob()`, and `unarchiveJob()` in `ApplicationActionController` to call the new per-user method instead of mutating `jobhunter_job_requirements.status`. Updated `getSavedJobs()`, `getSavedJobsFiltered()`, `getArchivedJobs()`, and `getArchivedJobsCount()` in `JobDiscoveryService` to filter on `sj.archived` instead of `j.status`. All changes landed in auto-checkpoint `55ab8337c`; attribution commit `3116c4187`. DB migration ran successfully: `DESCRIBE jobhunter_saved_jobs` confirms `archived` column present.

## Next actions
- QA-forseti to verify: two users save the same Google job; User A archives it → User A's My Jobs excludes it, User B's My Jobs still shows it
- Verify unarchive restores only for User A (User B unaffected)
- Regression: anonymous 403 on My Jobs, existing pipeline view unaffected

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 8
- Rationale: Prevents silent data corruption across users sharing catalog jobs — a correctness bug that erodes trust in the My Jobs feature. Fix is contained and low-risk.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260409-bulk-archive-global-status-mutation-release-f
- Generated: 2026-04-09T13:55:12+00:00
