# Test Plan: forseti-jobhunter-tailoring-feedback

- Feature: forseti-jobhunter-tailoring-feedback
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-10
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED` (role: authenticated)
- At least one generated tailored resume in `jobhunter_tailored_resumes` for the test user
- `jobhunter_tailoring_feedback` table exists (run `drush sql:query "DESCRIBE jobhunter_tailoring_feedback"` to confirm)

## Test cases

### TC-1: Widget renders on tailoring result page

- **Type:** functional / smoke
- **Given:** authenticated user with a generated tailored resume
- **When:** GET `/jobhunter/tailor-resume` (or the result page for a specific tailored_resume_id)
- **Then:** response HTML contains `tailoring-feedback` widget markup (thumbs-up, thumbs-down, and notes textarea)
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/tailor-resume | grep -c 'tailoring-feedback'` → ≥ 1

---

### TC-2: Submit thumbs-up rating (AJAX POST)

- **Type:** functional / happy path
- **Given:** no prior feedback row for (uid, tailored_resume_id)
- **When:** AJAX POST to feedback endpoint with `rating=up` and `note="Looks good"`
- **Then:** HTTP 200; DB row created with `rating='up'`, `note='Looks good'`
- **Command:**
  ```sql
  SELECT rating, note FROM jobhunter_tailoring_feedback
  WHERE uid=<uid> AND tailored_resume_id=<id>;
  -- Expected: up | Looks good
  ```

---

### TC-3: Submit thumbs-down rating (AJAX POST)

- **Type:** functional / happy path
- **Given:** no prior feedback row for this resume
- **When:** AJAX POST with `rating=down` and empty note
- **Then:** HTTP 200; DB row created with `rating='down'`, `note=NULL`
- **Command:** `SELECT rating FROM jobhunter_tailoring_feedback WHERE uid=<uid> AND tailored_resume_id=<id>` → `down`

---

### TC-4: Update existing feedback (idempotency)

- **Type:** functional / update path
- **Given:** prior feedback row with `rating='up'`
- **When:** AJAX POST with `rating=down` and `note="Changed my mind"`
- **Then:** HTTP 200; existing row updated (not duplicated); `rating='down'`, `note='Changed my mind'`
- **Command:** `SELECT COUNT(*) FROM jobhunter_tailoring_feedback WHERE uid=<uid> AND tailored_resume_id=<id>` → 1 (not 2)

---

### TC-5: Widget pre-selects prior rating on revisit

- **Type:** functional / state persistence
- **Given:** feedback row exists with `rating='up'`
- **When:** GET the tailoring result page
- **Then:** page HTML contains `data-rated="up"` (or equivalent selected state on the thumbs-up button)
- **Command:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/tailor-resume | grep 'data-rated="up"'` → match

---

### TC-6: Unauthenticated POST returns 403

- **Type:** security / auth gate
- **Given:** no session cookie
- **When:** POST to feedback endpoint
- **Then:** HTTP 403
- **Command:** `curl -s -o /dev/null -w "%{http_code}" -X POST https://forseti.life/jobhunter/tailor-feedback` → `403`

---

### TC-7: Note exceeding 500 chars returns 422

- **Type:** validation / boundary
- **Given:** authenticated user
- **When:** POST with a 501-character note string
- **Then:** HTTP 422; response body contains an error message; no DB row created/updated
- **Command:** `python3 -c "print('x'*501)"` → pipe into POST body; check response status

---

### TC-8: Ownership check — rating another user's resume returns 403

- **Type:** security / authorization
- **Given:** `tailored_resume_id=42` belongs to uid 5; current user is uid 6
- **When:** uid 6 POSTs feedback for `tailored_resume_id=42`
- **Then:** HTTP 403; no feedback row created
- **Command:** `SELECT COUNT(*) FROM jobhunter_tailoring_feedback WHERE uid=6 AND tailored_resume_id=42` → 0

---

### TC-9: XSS — HTML in note is stored stripped

- **Type:** security / input sanitization
- **Given:** authenticated user
- **When:** POST with `note='<script>alert(1)</script>'`
- **Then:** DB stores plain text (tags stripped); rendered page does not execute script
- **Command:** `SELECT note FROM jobhunter_tailoring_feedback WHERE uid=<uid>` → no `<script>` tag present

---

### TC-10: CSRF token required on POST route

- **Type:** security / CSRF
- **Given:** authenticated session (valid cookie)
- **When:** POST to feedback endpoint without a valid CSRF token header
- **Then:** HTTP 403 (CSRF check fails)
- **Command:** POST without `X-CSRF-Token` header → 403
