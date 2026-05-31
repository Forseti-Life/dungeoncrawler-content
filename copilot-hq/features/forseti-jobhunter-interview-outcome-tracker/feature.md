# Feature Brief

- Work item id: forseti-jobhunter-interview-outcome-tracker
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 5
- Group: interviews-offers
- Group Title: Interviews, Offers & Career
- Group Sort: 2
- Status: shipped
- Release: 20260412-forseti-release-h
- Feature type: new-feature
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: CEO feature brief request 2026-04-12

## Summary

The existing `forseti-jobhunter-interview-prep` feature (shipped release-j) lets users write interview notes for a saved job. However, there is no way to log the outcome of each interview round or track the progression through stages (phone screen → technical → final → offer/rejection). This feature adds a round-by-round interview log per saved job, stored in a new `jobhunter_interview_rounds` table. Each round entry captures: round type (phone screen, technical, behavioral, final, other), outcome (pending, passed, failed, withdrawn), date conducted, and freetext notes. The running log is visible on the saved-job detail view and drives the application status funnel used by the analytics feature.

## User story

As a job seeker in multiple hiring processes simultaneously, I want to log each interview round and its outcome so I can track my progress, spot patterns, and decide where to focus my energy.

## Non-goals

- Calendar scheduling or interviewer identity lookup (separate CRM scope)
- Automated status transitions based on round outcomes (separate workflow scope)
- Integration with the existing `jobhunter_interview_notes` table — that table stores prep notes; this feature logs round outcomes separately

## Acceptance criteria

### AC-1: Add interview round form on saved-job detail view

Given an authenticated user viewing a saved job detail, when they click "Add Interview Round," then a form appears with fields: round type (select: phone-screen, technical, behavioral, final, other), outcome (select: pending, passed, failed, withdrawn), date conducted (date, required), and notes (textarea, optional). On save, the round is appended to a chronological log visible on the page.

Verify: form submits via AJAX POST and a new row appears in `jobhunter_interview_rounds` without page reload.

### AC-2: Interview round log is visible and ordered chronologically

Given a saved job has three logged interview rounds on different dates, when the user views the job detail, then all three rounds are displayed in ascending date order showing: round type, outcome badge (color-coded: pending=grey, passed=green, failed=red, withdrawn=amber), date, and notes excerpt.

Verify: `drush sql:query "SELECT round_type, outcome, conducted_date FROM jobhunter_interview_rounds WHERE saved_job_id=<id> AND uid=<uid> ORDER BY conducted_date ASC"` → matches page display order.

### AC-3: Outcome can be updated after initial save

Given a round was logged with outcome "pending," when the user changes it to "passed" and saves, then the DB row is updated (not duplicated) and the badge on the page reflects the new outcome.

Verify: `SELECT COUNT(*) FROM jobhunter_interview_rounds WHERE saved_job_id=<id> AND uid=<uid> AND round_type='technical'` → 1 (no duplicate); `outcome` column → `passed`.

### AC-4: DB schema — jobhunter_interview_rounds table

Given the module update hook has run, when querying the schema, then the table `jobhunter_interview_rounds` exists with columns: `id` (serial PK), `uid` (int), `saved_job_id` (int), `round_type` (varchar 32), `outcome` (varchar 16: pending/passed/failed/withdrawn), `conducted_date` (date, required), `notes` (text, nullable), `created` (int), `changed` (int).

Verify: `drush sql:query "DESCRIBE jobhunter_interview_rounds"` → all expected columns present.

### AC-5: Cross-user isolation

Given user A has interview rounds logged for saved_job_id 20 and user B has no rounds for that job, when user B views the detail page for a job with id 20, then user B sees an empty rounds log.

Verify: `drush sql:query "SELECT COUNT(*) FROM jobhunter_interview_rounds WHERE uid=<uid_B> AND saved_job_id=20"` → 0.

## Security acceptance criteria

- All interview round routes require `_user_is_logged_in: 'TRUE'`
- CSRF token required on all state-changing POST routes (split-route pattern)
- Controller must verify `saved_job_id` belongs to current user before insert/update
- Notes field stored as plain text; Twig auto-escaping on display (no `|raw`)
- Notes must NOT be logged to watchdog at debug severity
