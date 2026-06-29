Now I have enough context. Let me check the `myJobs()` method briefly for reference on the status dashboard, then implement all the pieces.

Now I have all the context I need. Let me implement everything:

Now I'll implement all pieces. Starting with the install hook, then routes, then controller methods:

Now add the three new routes to `job_hunter.routing.yml`:

Now add the three controller methods to `CompanyController.php`. First, extend `viewJob()` to show the deadline form, then add the three new methods. Let me find a good insertion point near the end of the file:

Now I have the full picture. I'll add the deadline form right before the `return $this->wrapWithNavigation($content)` in `viewJob()`, and append the three new methods after `applicationNotesSave`:

Now add the deadline form section right before the `return $this->wrapWithNavigation($content)` at line 1326:

Now add the three new controller methods at the end of the file:

Now run the DB migration and verify PHP syntax:

Both files clean. Now run the DB migration directly:

Migration successful. Now rebuild cache and verify routes:

All 3 routes registered. Test anon 403 on the GET routes:

Anon 403 confirmed on both GET routes. Now commit:

All done. Here's the outbox update:

---

- Status: done
- Summary: Implemented the full Application Deadline Tracker feature for Job Hunter. Added `deadline_date` and `follow_up_date` columns to `jobhunter_saved_jobs` (via `job_hunter_update_9047` + direct drush migration). Extended `viewJob()` to display a date form for saved jobs with CSRF-protected AJAX save. Added three new routes and controller methods: `deadlineSave()` (ownership guard, date validation, no PII in logs), `statusDashboard()` (all active saved jobs with overdue/amber/normal urgency badges at `/jobhunter/status`), and `deadlinesList()` (jobs with deadlines sorted ASC at `/jobhunter/deadlines`). PHP syntax clean, cache rebuilt, all 3 routes registered, anon 403 verified on GET routes.

## Next actions
- QA verification of the deadline form on `/jobhunter/job/{job_id}` for a saved job (confirm form saves, urgency renders correctly)
- QA verification of `/jobhunter/status` and `/jobhunter/deadlines` table output and color coding
- Mark feature `forseti-jobhunter-application-deadline-tracker` as shipped once QA signs off

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 15
- Rationale: High-visibility feature that directly supports the core job-hunting workflow; deadline awareness is a key differentiator for job seekers and was a release-b priority item.

**Commits:** `0f772acf0`

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260411-160846-impl-forseti-jobhunter-application-deadline-tracker
- Generated: 2026-04-11T16:16:31+00:00
