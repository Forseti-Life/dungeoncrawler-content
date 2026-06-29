- Status: done
- Completed: 2026-04-13T02:34:36Z

# Suite Activation: forseti-jobhunter-interview-outcome-tracker

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-13T00:41:07+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-interview-outcome-tracker"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-interview-outcome-tracker/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-interview-outcome-tracker-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-interview-outcome-tracker",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-interview-outcome-tracker"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-interview-outcome-tracker-<route-slug>",
     "feature_id": "forseti-jobhunter-interview-outcome-tracker",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-interview-outcome-tracker",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-interview-outcome-tracker

- Feature: forseti-jobhunter-interview-outcome-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- At least one saved job for the test user
- `jobhunter_interview_rounds` table exists

## Test cases

### TC-1: Interview round form renders on saved-job detail

- **Type:** functional / smoke
- **When:** open saved-job detail
- **Then:** interview-round form is visible

---

### TC-2: Saving new round inserts row

- **Type:** functional / happy path
- **When:** save a phone-screen round with outcome `pending`
- **Then:** row exists in `jobhunter_interview_rounds`

---

### TC-3: Round log ordering is chronological

- **Type:** functional / sorting
- **When:** multiple rounds exist with different dates
- **Then:** rendered order matches ascending `conducted_date`

---

### TC-4: Updating existing round does not duplicate

- **Type:** functional / idempotency
- **When:** update the same round from `pending` to `passed`
- **Then:** count remains 1 and outcome changes

---

### TC-5: Cross-user isolation

- **Type:** security / data isolation
- **When:** user B views same saved job id with no round rows
- **Then:** no user A round data is visible

---

### TC-6: Unauthenticated and CSRF failures blocked

- **Type:** security
- **When:** GET/POST without auth or POST without valid CSRF token
- **Then:** requests are blocked appropriately

### Acceptance criteria (reference)

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
