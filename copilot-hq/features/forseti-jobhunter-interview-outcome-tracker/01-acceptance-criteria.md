# Acceptance Criteria: forseti-jobhunter-interview-outcome-tracker

- Feature: forseti-jobhunter-interview-outcome-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Add a round-by-round interview outcome log per saved job using
`jobhunter_interview_rounds`. The saved-job detail view should support add/edit
operations and show a chronological log with outcome badges.

## Acceptance criteria

### AC-1: Add interview round form on saved-job detail view

**Given** an authenticated user viewing a saved job detail,
**When** they click `Add Interview Round`,
**Then** a form appears with round type, outcome, conducted date, and notes and
can be saved without full page reload.

**Verify:** AJAX POST inserts a row into `jobhunter_interview_rounds`.

---

### AC-2: Interview round log is visible and chronological

**Given** a saved job has multiple interview rounds,
**When** the user views the job detail,
**Then** rounds display in ascending date order with round type, colored outcome
badge, date, and notes excerpt.

**Verify:** `SELECT round_type, outcome, conducted_date FROM jobhunter_interview_rounds WHERE saved_job_id=<id> AND uid=<uid> ORDER BY conducted_date ASC`

---

### AC-3: Existing round can be updated without duplication

**Given** a prior round exists with outcome `pending`,
**When** the user updates it to `passed`,
**Then** the existing row updates and no duplicate round is created.

**Verify:** `SELECT COUNT(*) ...` remains `1` for that round.

---

### AC-4: DB schema — jobhunter_interview_rounds table

**Given** the module update hook has run,
**When** querying the schema,
**Then** `jobhunter_interview_rounds` exists with uid, saved_job_id, round_type,
outcome, conducted_date, notes, created, and changed.

**Verify:** `drush sql:query "DESCRIBE jobhunter_interview_rounds"`

---

### AC-5: Cross-user isolation

**Given** user A has rounds for a saved job and user B does not,
**When** user B views the job detail,
**Then** user B sees no rounds from user A.

**Verify:** query by `uid=<uid_B>` returns 0 rows.

---

## Security acceptance criteria

### SEC-1: Authentication required

All interview-round routes require `_user_is_logged_in: 'TRUE'`.

### SEC-2: CSRF on state-changing routes

All POST save/update routes use the split-route CSRF pattern.

### SEC-3: Ownership check on saved job

Controller verifies the saved job belongs to the current user before insert/update.

### SEC-4: Safe text handling

Notes are stored as plain text and rendered with Twig auto-escaping only.

### SEC-5: No debug logging of notes

Interview notes content must not be written to watchdog at debug severity.
