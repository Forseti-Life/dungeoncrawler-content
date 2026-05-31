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
