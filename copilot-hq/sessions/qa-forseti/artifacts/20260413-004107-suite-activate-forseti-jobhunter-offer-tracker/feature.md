# Feature Brief

- Work item id: forseti-jobhunter-offer-tracker
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260412-forseti-release-d
- Feature type: new-feature
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: CEO feature brief request 2026-04-12

## Summary

Job seekers often receive multiple offers simultaneously and have no structured place to track and compare them. This feature adds an offer tracking view to the Job Hunter module. When a saved job's status is updated to "offer received," the user can record offer details — compensation, equity, benefits summary, response deadline, and freetext notes. A comparison view at `/jobhunter/offers` surfaces all active offers side-by-side so the user can evaluate them. Offer details are stored in a new `jobhunter_offers` table keyed by `(uid, saved_job_id)`.

## User story

As a job seeker who has received one or more offers, I want to record and compare offer details in one place so I can make an informed decision before responding to employers.

## Non-goals

- Automated negotiation suggestions (separate AI feature)
- Calendar or deadline email reminders (handled by follow-up-reminders feature)
- Sharing offers with other users

## Acceptance criteria

### AC-1: Offer details form on saved-job detail view

Given an authenticated user whose saved job has status "offer received," when they view the job detail panel, then an "Offer Details" form is visible with fields: base salary (integer), equity/bonus summary (text, optional), benefits summary (text, optional), response deadline (date, optional), and notes (textarea, optional). The form saves via CSRF-protected AJAX POST without page reload.

Verify: `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -q 'offer-details'`

### AC-2: Offer comparison page at /jobhunter/offers

Given an authenticated user with at least two saved jobs with recorded offer details, when they navigate to `/jobhunter/offers`, then all their active offers are displayed in a structured comparison layout showing: company, role, base salary, equity, deadline, and a link back to the full detail view. If the user has zero or one offer, a prompt is shown ("You currently have N active offers").

Verify: `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/offers` → HTTP 200, page contains offer comparison markup.

### AC-3: Offer data is scoped strictly to current user

Given user A has an offer recorded for saved_job_id 10 and user B has no offers, when user B visits `/jobhunter/offers`, then user B sees their own (empty) offer list — no data from user A is accessible.

Verify: `drush sql:query "SELECT COUNT(*) FROM jobhunter_offers WHERE uid=<uid_B>"` → 0; user B's offers page shows zero-offer prompt.

### AC-4: DB schema — jobhunter_offers table

Given the module update hook has run, when querying the schema, then the table `jobhunter_offers` exists with columns: `id` (serial PK), `uid` (int), `saved_job_id` (int), `base_salary` (int, nullable), `equity_summary` (text, nullable), `benefits_summary` (text, nullable), `response_deadline` (date, nullable), `notes` (text, nullable), `created` (int), `changed` (int).

Verify: `drush sql:query "DESCRIBE jobhunter_offers"` → all expected columns present.

## Security acceptance criteria

- All offer routes require `_user_is_logged_in: 'TRUE'`
- CSRF token required on POST save route (POST-only split route; GET page has no CSRF requirement)
- Controller must verify `jobhunter_saved_jobs.uid == currentUser()->id()` before creating/updating offer row
- Salary and notes fields stored as plain text; no HTML rendered without Twig auto-escaping
- Salary and notes must NOT be logged to watchdog at debug severity; log only `uid` and `saved_job_id`
