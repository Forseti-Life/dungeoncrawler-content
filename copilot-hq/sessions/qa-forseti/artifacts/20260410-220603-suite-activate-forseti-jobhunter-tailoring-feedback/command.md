- Status: done
- Completed: 2026-04-11T00:21:51Z

# Suite Activation: forseti-jobhunter-tailoring-feedback

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
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-tailoring-feedback"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-tailoring-feedback/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-tailoring-feedback-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-tailoring-feedback",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-tailoring-feedback"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-tailoring-feedback-<route-slug>",
     "feature_id": "forseti-jobhunter-tailoring-feedback",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-tailoring-feedback",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

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
- Agent: qa-forseti
- Status: pending
