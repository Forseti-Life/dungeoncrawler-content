# Acceptance Criteria: forseti-jobhunter-cover-letter-display

- Feature: forseti-jobhunter-cover-letter-display
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Display UI for generated cover letters at `/jobhunter/coverletter/{job_id}`. Backend (`CoverLetterTailoringWorker`, `jobhunter_cover_letters` table) already shipped. Pattern mirrors `forseti-jobhunter-resume-tailoring-display`.

## Acceptance criteria

### AC-1: Route and access
- `GET /jobhunter/coverletter/{job_id}` returns 200 for authenticated users with `access job hunter` permission.
- Anonymous access → 403.
- `{job_id}` must be an integer; non-integer → 404.
- Controller verifies `job.uid == current_user->id()`; otherwise → 403/404.

### AC-2: No existing record — generate button
- When no `jobhunter_cover_letters` record exists for (uid, job_id): a "Generate Cover Letter" button is shown.
- POST to `job_hunter.cover_letter_generate` (POST-only, CSRF-guarded) creates a record with `tailoring_status = queued` and enqueues the item.
- After POST: redirect back to the GET page showing the status indicator.

### AC-3: Pending/queued/processing status indicator
- When `tailoring_status` is `pending`, `queued`, or `processing`: a status message is shown with a note to check back (or auto-refresh hint).
- No cover letter content rendered in this state.

### AC-4: Completed — cover letter rendered
- When `tailoring_status = completed`: `cover_letter_html` content is rendered in the display area.

### AC-5: PDF download
- When `pdf_path` is non-empty: a "Download PDF" link renders pointing to `pdf_path`.
- When `pdf_path` is null/empty: no download link shown.

### AC-6: Save to application
- "Save to application" POST button sends to `job_hunter.cover_letter_save` (POST-only, CSRF-guarded).
- On success: confirmation message; job record is updated to reference this cover letter.
- Missing/invalid CSRF → 403.
- Verify: `grep -A5 "job_hunter.cover_letter_save" job_hunter.routing.yml` shows `methods: [POST]` and `_csrf_token: 'TRUE'`.

### AC-7: Failed status — retry
- When `tailoring_status = failed`: error message shown with a retry option (re-enqueue).

### AC-8: CSRF on all POST routes
- `grep -A5 "job_hunter.cover_letter_generate" job_hunter.routing.yml` shows `_csrf_token: 'TRUE'`.
- `grep -A5 "job_hunter.cover_letter_save" job_hunter.routing.yml` shows `_csrf_token: 'TRUE'`.

## Security acceptance criteria

### Authentication/permission surface
- Route requires `_permission: 'access job hunter'` and `_user_is_logged_in: 'TRUE'`.
- Controller verifies `uid == current_user->id()` before any read or write.
- Verify: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/coverletter/1` → 403 (anonymous).

### CSRF expectations
- GET route: no CSRF.
- Generate and Save routes: POST-only, `_csrf_token: 'TRUE'` (split-route pattern).

### Input validation requirements
- `{job_id}` is an integer. Non-integer → 404, no PHP fatal.
- `cover_letter_html` is AI-generated server-side output only; never user-supplied HTML.
- No user-provided content is rendered unescaped.

### PII/logging constraints
- Cover letter content (text, HTML) must NOT be written to watchdog.
- `pdf_path` must point to Drupal private file system.
- Error log entries: `job_id` (integer) + error code only.

## Verification commands
```bash
# CSRF guards
grep -A8 "job_hunter.cover_letter_generate" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml
grep -A8 "job_hunter.cover_letter_save" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml

# Anonymous access
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/coverletter/1
# Expected: 403

# Drush route check
cd /home/ubuntu/forseti.life/sites/forseti && ./vendor/bin/drush router:debug | grep cover_letter
```
