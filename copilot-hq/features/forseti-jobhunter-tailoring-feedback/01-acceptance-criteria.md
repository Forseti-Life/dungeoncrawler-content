# Acceptance Criteria: forseti-jobhunter-tailoring-feedback

- Feature: forseti-jobhunter-tailoring-feedback
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-10

## Summary

Add a thumbs-up/thumbs-down rating widget (plus optional freetext note, max 500 chars) to the tailoring result display. Ratings are stored in a new `jobhunter_tailoring_feedback` table keyed by `(uid, tailored_resume_id)`. Submission is via AJAX with CSRF protection. The page updates the widget state in-place after successful save — no full reload.

## Acceptance criteria

### AC-1: Rating widget renders on tailoring result

**Given** a logged-in user has generated a tailored resume and is viewing the result page,
**When** the page loads,
**Then** a 👍/👎 widget and optional notes field (textarea, max 500 chars) are visible below the tailored resume output.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/tailor-resume | grep -q 'tailoring-feedback'`

---

### AC-2: Rating widget shows prior rating if one exists

**Given** the user has previously rated tailored resume ID 42,
**When** they revisit the result page for that resume,
**Then** the widget pre-selects their prior rating and displays their prior note text.

**Verify:** `drush sql:query "SELECT * FROM jobhunter_tailoring_feedback WHERE tailored_resume_id=42 AND uid=<uid>"` returns a row; the page HTML contains `data-rated="up"` or `data-rated="down"`.

---

### AC-3: AJAX submit creates or updates feedback row

**Given** the user clicks 👍 on resume ID 42 with note "Great match for the role",
**When** the AJAX POST is submitted to the feedback endpoint,
**Then** a row is created/updated in `jobhunter_tailoring_feedback` with `rating='up'`, `note='Great match for the role'`, and `changed=<current timestamp>`.

**Verify:**
```sql
SELECT rating, note FROM jobhunter_tailoring_feedback WHERE tailored_resume_id=42 AND uid=<uid>;
-- Expected: rating=up, note='Great match for the role'
```

---

### AC-4: Unauthenticated users cannot submit feedback

**Given** a request to the feedback POST endpoint without a valid session,
**When** the request is sent,
**Then** the endpoint returns HTTP 403.

**Verify:** `curl -s -o /dev/null -w "%{http_code}" -X POST https://forseti.life/jobhunter/tailor-feedback` → `403`

---

### AC-5: Rating note is capped at 500 characters

**Given** a user submits a note longer than 500 characters,
**When** the form is submitted,
**Then** the endpoint returns HTTP 422 with an error message; no row is created/updated.

**Verify:** submit a 501-char string via AJAX POST; response body contains `"error":"Note exceeds maximum length"`.

---

### AC-6: DB schema — `jobhunter_tailoring_feedback` table exists

**Given** the module update hook has run,
**When** querying the schema,
**Then** the table `jobhunter_tailoring_feedback` exists with columns: `id` (serial PK), `uid` (int), `tailored_resume_id` (int), `rating` (varchar 4: `up`/`down`), `note` (text, nullable), `created` (int), `changed` (int).

**Verify:** `drush sql:query "DESCRIBE jobhunter_tailoring_feedback"` → all expected columns present.

---

## Security acceptance criteria

### SEC-1: Authentication required

All feedback routes (`GET` of widget state, `POST` of feedback) must require `_user_is_logged_in: 'TRUE'` in routing.yml. Unauthenticated requests receive HTTP 403.

### SEC-2: CSRF protection on POST

The feedback POST route must include `_csrf_token: 'TRUE'` in routing.yml (POST-only route, not mixed GET/POST — follow split-route pattern to avoid GET 403 regression).

**Verify:** `grep -A5 "job_hunter.tailoring_feedback" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml | grep "_csrf_token"` → present on POST route entry only.

### SEC-3: Ownership check — users can only rate their own resumes

The controller must verify `jobhunter_tailored_resumes.uid == \Drupal::currentUser()->id()` before accepting a rating. A request with a valid session but a `tailored_resume_id` owned by another user must return HTTP 403.

**Verify:** submit a feedback POST with a `tailored_resume_id` owned by a different uid; expect 403.

### SEC-4: Input sanitization

The `note` field must be stored as plain text (no HTML). The controller must strip HTML tags before persistence. Display must use Twig auto-escaping (no `|raw` filter).

**Verify:** submit `note='<script>alert(1)</script>'`; `drush sql:query "SELECT note FROM jobhunter_tailoring_feedback WHERE ..."` → stored as stripped plain text.

### SEC-5: PII / logging constraints

The `note` field may contain user-authored text. It must NOT be logged to Drupal watchdog at severity DEBUG or lower with the full note content. Log only `uid` and `tailored_resume_id`.
