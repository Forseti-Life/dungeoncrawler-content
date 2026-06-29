- Status: done
- Completed: 2026-04-10T23:35:34Z

# Suite Activation: forseti-jobhunter-application-notes

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-10T22:06:03+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-application-notes"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-application-notes/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-application-notes-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-application-notes",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-application-notes"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-application-notes-<route-slug>",
     "feature_id": "forseti-jobhunter-application-notes",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-application-notes",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-application-notes

- Feature: forseti-jobhunter-application-notes
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-10
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED` (role: authenticated)
- At least one saved job in `jobhunter_saved_jobs` for the test user
- `jobhunter_application_notes` table exists (run `drush sql:query "DESCRIBE jobhunter_application_notes"` to confirm)

## Test cases

### TC-1: Notes form renders on saved-job detail view

- **Type:** functional / smoke
- **Given:** authenticated user with at least one saved job
- **When:** GET `/jobhunter/my-jobs` (or `/jobhunter/saved-jobs/{job_id}`)
- **Then:** response HTML contains `application-notes` form section with the four fields
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -c 'application-notes'` → ≥ 1

---

### TC-2: Save notes (AJAX POST) — all fields

- **Type:** functional / happy path
- **Given:** no prior notes row for (uid, saved_job_id=55)
- **When:** POST with `manager_name="Jane Smith"`, `contact_email="jane@example.com"`, `last_contact_date="2026-04-10"`, `notes="Follow up Monday"`
- **Then:** HTTP 200; DB row created with all four fields populated; page shows success flash
- **Command:**
  ```sql
  SELECT manager_name, contact_email, last_contact_date, notes
  FROM jobhunter_application_notes
  WHERE uid=<uid> AND saved_job_id=55;
  ```

---

### TC-3: Notes pre-populate on revisit

- **Type:** functional / state persistence
- **Given:** notes row exists for (uid, saved_job_id=55)
- **When:** GET the job detail page
- **Then:** form fields are pre-populated with saved values
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep 'Jane Smith'` → match

---

### TC-4: Update existing notes row (idempotency)

- **Type:** functional / update path
- **Given:** prior notes row for (uid, saved_job_id=55)
- **When:** POST with updated `notes="Sent thank-you email"`
- **Then:** HTTP 200; row updated, not duplicated; `SELECT COUNT(*)` → 1
- **Command:** `SELECT COUNT(*) FROM jobhunter_application_notes WHERE uid=<uid> AND saved_job_id=55` → 1

---

### TC-5: Cross-user isolation — user B cannot see user A's notes

- **Type:** security / data isolation
- **Given:** user A has notes for saved_job_id=55; user B has no notes for that job
- **When:** user B loads the detail view for that job
- **Then:** form is empty; no user A data rendered
- **Command:** query `jobhunter_application_notes WHERE uid=<uid_B> AND saved_job_id=55` → 0 rows; page fields empty

---

### TC-6: Unauthenticated POST returns 403

- **Type:** security / auth gate
- **Given:** no session cookie
- **When:** POST to notes save endpoint
- **Then:** HTTP 403
- **Command:** `curl -s -o /dev/null -w "%{http_code}" -X POST https://forseti.life/jobhunter/application-notes` → `403`

---

### TC-7: Ownership check — save notes for another user's saved_job returns 403

- **Type:** security / authorization
- **Given:** `saved_job_id=55` belongs to uid 5; current user is uid 6
- **When:** uid 6 POSTs notes for `saved_job_id=55`
- **Then:** HTTP 403; no row created
- **Command:** `SELECT COUNT(*) FROM jobhunter_application_notes WHERE uid=6 AND saved_job_id=55` → 0

---

### TC-8: Invalid email returns 422

- **Type:** validation
- **Given:** authenticated user
- **When:** POST with `contact_email="not-an-email"`
- **Then:** HTTP 422; response body contains error message; no DB row created/updated
- **Command:** POST with bad email; check response status → 422

---

### TC-9: Notes exceeding 2000 chars returns 422

- **Type:** validation / boundary
- **Given:** authenticated user
- **When:** POST with a 2001-character notes string
- **Then:** HTTP 422; no DB row created/updated
- **Command:** `python3 -c "print('x'*2001)"` → pipe into POST body; check response status → 422

---

### TC-10: XSS — HTML in notes/manager_name is stored stripped

- **Type:** security / input sanitization
- **Given:** authenticated user
- **When:** POST with `notes='<script>alert(1)</script>'` and `manager_name='<img src=x>'`
- **Then:** DB stores plain text (tags stripped); rendered page does not execute script
- **Command:** `SELECT notes, manager_name FROM jobhunter_application_notes WHERE uid=<uid>` → no HTML tags

---

### TC-11: CSRF token required on POST route

- **Type:** security / CSRF
- **Given:** authenticated session
- **When:** POST to notes endpoint without valid CSRF token
- **Then:** HTTP 403
- **Command:** POST without `X-CSRF-Token` header → 403

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-application-notes

- Feature: forseti-jobhunter-application-notes
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-10

## Summary

Add a per-saved-job contact log and notes field. New table `jobhunter_application_notes` keyed by `(uid, saved_job_id)`. The saved-job detail view exposes an inline form (AJAX POST, CSRF-protected) for hiring manager name, contact email, last contact date, and freetext notes. Revisiting the page pre-populates the form.

Note: this is distinct from `jobhunter_interview_notes` (shipped release-g) — that table is for interview prep; this is for application-tracking contact information.

## Acceptance criteria

### AC-1: Notes form renders on saved-job detail view

**Given** a logged-in user views a saved job at `/jobhunter/saved-jobs/{job_id}` (or the inline detail panel at `/jobhunter/my-jobs`),
**When** the page loads,
**Then** a "Application Notes" section is visible with fields: Hiring Manager Name (text, optional), Contact Email (text, optional), Last Contact Date (date, optional), Notes (textarea, optional, max 2000 chars).

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -q 'application-notes'`

---

### AC-2: Notes pre-populate on revisit

**Given** the user has previously saved notes for saved_job_id 55,
**When** they revisit that job's detail view,
**Then** all four fields are pre-populated with their saved values.

**Verify:** `drush sql:query "SELECT * FROM jobhunter_application_notes WHERE saved_job_id=55 AND uid=<uid>"` returns a row; page HTML contains the saved values in the form fields.

---

### AC-3: AJAX save creates or updates notes row

**Given** the user fills the form and clicks Save,
**When** the AJAX POST is submitted,
**Then** a row is created/updated in `jobhunter_application_notes` with the submitted values and `changed=<current timestamp>`. The page shows a success flash ("Notes saved.") without full reload.

**Verify:**
```sql
SELECT manager_name, contact_email, last_contact_date, notes
FROM jobhunter_application_notes
WHERE saved_job_id=55 AND uid=<uid>;
```

---

### AC-4: Notes row is isolated per user

**Given** user A saves notes for saved_job_id 55 and user B has no notes for that job,
**When** user B views the same saved job,
**Then** user B's form is empty (no cross-user data leak).

**Verify:** query `jobhunter_application_notes WHERE saved_job_id=55 AND uid=<uid_B>` returns 0 rows; page form fields are empty for user B.

---

### AC-5: DB schema — `jobhunter_application_notes` table exists

**Given** the module update hook has run,
**When** querying the schema,
**Then** the table exists with columns: `id` (serial PK), `uid` (int), `saved_job_id` (int), `manager_name` (varchar 255, nullable), `contact_email` (varchar 255, nullable), `last_contact_date` (date, nullable), `notes` (text, max 2000 chars, nullable), `created` (int), `changed` (int).

**Verify:** `drush sql:query "DESCRIBE jobhunter_application_notes"` → all expected columns present.

---

### AC-6: Email field validation

**Given** the user enters an invalid email in the Contact Email field,
**When** the form is submitted,
**Then** the endpoint returns HTTP 422 with an error message; no row is created/updated.

**Verify:** POST with `contact_email='not-an-email'`; response body contains `"error":"Invalid email address"`.

---

## Security acceptance criteria

### SEC-1: Authentication required

All routes for notes form and save endpoint must require `_user_is_logged_in: 'TRUE'`. Unauthenticated requests receive HTTP 403.

### SEC-2: CSRF protection on POST

The notes save POST route must include `_csrf_token: 'TRUE'` on the POST-only route entry (split-route pattern; no CSRF token on GET route to avoid 403 regression).

**Verify:** `grep -A5 "job_hunter.application_notes" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml | grep "_csrf_token"` → present on POST route entry.

### SEC-3: Ownership check on save

The save controller must verify that `saved_job_id` belongs to the current user (i.e., a row in `jobhunter_saved_jobs` with matching `uid`) before creating/updating the notes row. A valid-session request for another user's saved_job_id must return HTTP 403.

**Verify:** POST with a `saved_job_id` owned by a different uid; expect HTTP 403.

### SEC-4: Input sanitization

All freetext fields (manager_name, notes) must be stored as plain text. HTML must be stripped before persistence. Display in Twig must use auto-escaping only (no `|raw`).

**Verify:** POST `notes='<script>alert(1)</script>'`; DB shows stripped text; rendered page does not execute script.

### SEC-5: PII / logging constraints

Contact email and manager name are PII. These fields must NOT be written to Drupal watchdog at any severity. Log only `uid` and `saved_job_id` for audit events (save/update).
- Agent: qa-forseti
- Status: pending
