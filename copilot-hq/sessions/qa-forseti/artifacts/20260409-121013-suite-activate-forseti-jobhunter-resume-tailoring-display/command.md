# Suite Activation: forseti-jobhunter-resume-tailoring-display

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T12:10:13+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-resume-tailoring-display"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-resume-tailoring-display/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-resume-tailoring-display-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-resume-tailoring-display",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-resume-tailoring-display"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-resume-tailoring-display-<route-slug>",
     "feature_id": "forseti-jobhunter-resume-tailoring-display",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-resume-tailoring-display",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-resume-tailoring-display

- Feature: forseti-jobhunter-resume-tailoring-display
- Module: job_hunter
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify side-by-side view, PDF download, save-to-profile CSRF, confidence score, status indicators, and access control at `/jobhunter/jobtailoring/{job_id}`.

## Test cases

### TC-1: Anonymous access → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/jobtailoring/1`
- Expected: `403`

### TC-2: Completed tailoring — side-by-side view
- Steps: Log in as a user with a `completed` tailoring result; GET `/jobhunter/jobtailoring/{job_id}`.
- Expected: Two columns rendered: original profile text and tailored resume text. Both have labelled headers.

### TC-3: Confidence score displayed
- Steps: Access a tailoring result where `confidence_score` is set (e.g., 82).
- Expected: "82%" label displayed adjacent to tailored resume header.

### TC-4: Confidence score absent
- Steps: Access a tailoring result where `confidence_score` is null.
- Expected: No percentage label rendered (not "null%" or "0%").

### TC-5: PDF download button present iff PDF exists
- Steps: Test with a job that has `pdf_path` set vs. one with `pdf_path` null.
- Expected: Button present with `pdf_path` set; absent when null.

### TC-6: Save-to-profile — valid CSRF
- Steps: Click "Save as active resume" button (valid session CSRF).
- Expected: 200 or redirect; active resume field updated; confirmation message shown.

### TC-7: Save-to-profile — missing CSRF
- Steps: POST to `job_hunter.job_tailoring_save_resume` without CSRF token.
- Expected: 403.

### TC-8: Pending/processing status indicator
- Steps: Access a job with tailoring `status = processing`.
- Expected: Status message shown ("Tailoring in progress…"); no side-by-side view rendered.

### TC-9: Failed status — retry option
- Steps: Access a job with tailoring `status = failed`.
- Expected: Error message shown with retry link/button visible.

### TC-10: Cross-user access
- Steps: As User A, GET `/jobhunter/jobtailoring/{job_id_owned_by_B}`.
- Expected: 403 or 404. No data from User B rendered.

### TC-11: Non-integer job_id
- Steps: GET `/jobhunter/jobtailoring/notanid`
- Expected: 404; no PHP fatal.

## Regression notes
- `job_hunter.job_tailoring` GET route must remain registered and functional.
- CSRF guard on new save route: `grep -A5 "job_tailoring_save_resume" job_hunter.routing.yml`.

### Acceptance criteria (reference)

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
