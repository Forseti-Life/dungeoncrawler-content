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
