# Acceptance Criteria: forseti-jobhunter-resume-tailoring-display

- Feature: forseti-jobhunter-resume-tailoring-display
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-09

## Summary

Polish the resume tailoring result display at `/jobhunter/jobtailoring/{job_id}`. Adds side-by-side view, PDF download button, save-to-profile action, confidence score display, and status state indicators. No new data model needed — builds on existing `tailored_resume` records and `job-tailoring-combined.html.twig`.

## Acceptance criteria

### AC-1: Side-by-side view (completed state)
- When tailoring `status` is `completed`: original profile text and tailored resume text are rendered in adjacent columns (or stacked on viewport < 768px).
- Both columns have a header label ("Your Profile" / "Tailored Resume").

### AC-2: PDF download button
- When `pdf_path` is set and non-empty: a "Download PDF" link/button renders, href points to `pdf_path`.
- When `pdf_path` is null/empty: no download button is rendered (no broken link).

### AC-3: Save-to-profile action
- When tailoring is `completed`: a "Save as active resume" button is present.
- Button submits to a POST-only route `job_hunter.job_tailoring_save_resume` with CSRF token.
- On success: confirmation message rendered; user's active resume updated.
- On missing/invalid CSRF: 403.
- Verify route: `grep -A5 "job_hunter.job_tailoring_save_resume" job_hunter.routing.yml` shows `methods: [POST]` and `_csrf_token: 'TRUE'`.

### AC-4: Confidence score
- When `tailored_resume` record has a `confidence_score` field (integer 0–100): display as percentage label adjacent to the "Tailored Resume" column header.
- When field is absent or null: no label rendered (not "null%" or "0%").

### AC-5: Status state indicators
- `pending` / `queued` / `processing`: a status message is shown (e.g., "Tailoring in progress — check back shortly."); no side-by-side view rendered.
- `failed`: an error message shown with a "Retry" link/button.
- `completed`: full display per AC-1 to AC-4.

### AC-6: Access control
- Users can only access tailoring results for their own jobs. Controller must verify `job.uid == current_user->id()`.
- Accessing another user's job tailoring → 403 or 404.

## Security acceptance criteria

### Authentication/permission surface
- Route requires `_permission: 'access job hunter'` and `_user_is_logged_in: 'TRUE'`.
- `{job_id}` path param scoped to current user's jobs. Cross-user access must return 403/404.
- Verify: anonymous GET `/jobhunter/jobtailoring/1` → 403.

### CSRF expectations
- "Save as active resume" uses split-route pattern: existing GET route unchanged; new `job_hunter.job_tailoring_save_resume` POST-only route with `_csrf_token: 'TRUE'`.
- PDF download is a GET link; no CSRF token needed.

### Input validation requirements
- `{job_id}` must be an integer. Non-integer → 404, no PHP error.
- No user-supplied input is rendered unescaped in the template.

### PII/logging constraints
- Resume content (original or tailored text) must NOT be written to watchdog at any log level.
- PDF path must point to Drupal private file system (not public). No direct public URL to resume PDF.

## Verification commands
```bash
# Confirm CSRF on save route
grep -A8 "job_tailoring_save_resume" /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml

# PHP lint on controller
php -l /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/src/Controller/ApplicationSubmissionController.php

# Template exists
ls /home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/templates/job-tailoring-combined.html.twig

# Anonymous access
curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/jobtailoring/1
# Expected: 403
```
