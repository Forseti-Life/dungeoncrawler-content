Now I have the exact location. I'll add `age_18_or_older` after `portfolio_url` (before `projection_updated`), matching the update hook spec:

All 3 ACs verified. Now commit:

---

- Status: done
- Summary: Added `age_18_or_older` column to `_job_hunter_create_job_seeker_table()` in `job_hunter.install`. The column was already present in `job_hunter_update_9039` (varchar(3), nullable, description "Whether applicant is 18 or older ('Yes' or 'No').") but was missing from the hook_schema helper, meaning fresh installs would not get the column. The fix inserts the column between `portfolio_url` and `projection_updated`, matching the update hook spec exactly. All 3 ACs verified: column present in schema function, spec matches update hook, `php -l` exits 0. Commit: `835d8290c`.

## Next actions
- No further dev action needed; QA can verify AC-3 column spec match with `grep -A8 "age_18_or_older" sites/forseti/web/modules/custom/job_hunter/job_hunter.install`

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 8
- Rationale: Prevents data loss on fresh installs by closing a schema gap between the update hook and hook_schema. Minimal change (1 line), zero regression risk on existing installs since the update hook already guards against duplicate column creation.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260408-forseti-release-b-schema-hook-age-18
- Generated: 2026-04-08T12:52:27+00:00
