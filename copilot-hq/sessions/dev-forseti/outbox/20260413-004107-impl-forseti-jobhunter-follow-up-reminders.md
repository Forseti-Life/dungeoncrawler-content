No conflict. Now I'll implement the three changes:

Now add `follow_up_overdue` computation in `myJobs()`:

Now add the badge to the Twig template. Let me find the right spot:

Now add CSS for the follow-up-overdue badge. Let me find where styles are defined in the template:

Now run `drush cr` and verify:

Only the pre-existing cron errors. Clean. Now check implementation notes and commit:

Update implementation notes with commit hash:

Done. Here is the outbox update:

---

- Status: done
- Summary: Implemented the `forseti-jobhunter-follow-up-reminders` feature. The `follow_up_date` column and its save/clear UI already existed (detail view date picker + `deadlineSave()` endpoint with SEC-2/SEC-3 compliance). What was missing were three pieces: (1) `getSavedJobs()` in `JobDiscoveryService` now includes `sj.follow_up_date` in its SELECT so the value reaches the controller; (2) `myJobs()` in `ApplicationSubmissionController` now computes `follow_up_overdue` per job at render time — overdue when `follow_up_date < today` AND `workflow_status` is not `interview`/`closed`; (3) `my-jobs.html.twig` renders a `<span class="follow-up-overdue">⏰ Follow up overdue</span>` badge inline on the job title cell, with amber CSS. All ACs and SEC requirements confirmed covered. `drush cr` ran clean; no new watchdog errors. Commits: `8486ab6cf` (code + notes) and `601700250` (notes hash update).

## Next actions
- Signal qa-forseti for Gate 2 verification on `forseti-jobhunter-follow-up-reminders` (AC-1..5, SEC-1..5)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 22
- Rationale: Closes a tangible user pain point (losing track of follow-ups across 20+ applications) with zero new infrastructure — leverages pre-existing schema and save endpoint, making the diff very small and low-risk.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260413-004107-impl-forseti-jobhunter-follow-up-reminders
- Generated: 2026-04-13T01:09:35+00:00
