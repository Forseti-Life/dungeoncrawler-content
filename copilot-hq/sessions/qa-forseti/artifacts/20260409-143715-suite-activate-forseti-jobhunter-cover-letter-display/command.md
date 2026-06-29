# Suite Activation: forseti-jobhunter-cover-letter-display

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-09T14:37:15+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-cover-letter-display"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-cover-letter-display/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-cover-letter-display-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-cover-letter-display",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-cover-letter-display"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-cover-letter-display-<route-slug>",
     "feature_id": "forseti-jobhunter-cover-letter-display",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-cover-letter-display",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-cover-letter-display

- Feature: forseti-jobhunter-cover-letter-display
- Module: job_hunter
- Author: ba-forseti (scaffold — qa-forseti to expand)
- Date: 2026-04-09

## Scope

Verify generate trigger, status states, completed display, PDF download, save-to-application CSRF, and access control at `/jobhunter/coverletter/{job_id}`.

## Test cases

### TC-1: Anonymous access → 403
- Steps: `curl -o /dev/null -s -w "%{http_code}" https://forseti.life/jobhunter/coverletter/1`
- Expected: `403`

### TC-2: No existing record — generate button shown
- Steps: Log in; access a job with no cover letter record.
- Expected: "Generate Cover Letter" button shown; no cover letter content displayed.

### TC-3: Generate POST — valid CSRF → queued
- Steps: Click "Generate Cover Letter" (valid CSRF).
- Expected: Record created with `tailoring_status = queued`; page shows status indicator.

### TC-4: Generate POST — invalid CSRF → 403
- Steps: POST to `job_hunter.cover_letter_generate` without CSRF token.
- Expected: 403.

### TC-5: Pending/processing status indicator
- Steps: Set `tailoring_status = processing` for a test record; view page.
- Expected: Status message displayed; no cover letter content.

### TC-6: Completed cover letter rendered
- Steps: Set `tailoring_status = completed` with content; view page.
- Expected: Cover letter HTML rendered in display area.

### TC-7: PDF download button present iff pdf_path set
- Steps: Test with `pdf_path` set vs. null.
- Expected: Button present when set; absent when null.

### TC-8: Save to application — valid CSRF
- Steps: Click "Save to application" (valid CSRF).
- Expected: Confirmation message; job record updated.

### TC-9: Save to application — invalid CSRF → 403
- Steps: POST without CSRF token.
- Expected: 403.

### TC-10: Failed status — retry shown
- Steps: `tailoring_status = failed`.
- Expected: Error message + retry option visible.

### TC-11: Cross-user access → 403/404
- Steps: As User A, GET `/jobhunter/coverletter/{job_id_owned_by_B}`.
- Expected: 403 or 404.

### TC-12: Non-integer job_id → 404
- Steps: `curl https://forseti.life/jobhunter/coverletter/notanid`
- Expected: 404.

## Regression notes
- `CoverLetterTailoringWorker` queue worker must remain registered: `drush queue:list | grep cover_letter`.
- Resume tailoring routes must be unaffected.

### Acceptance criteria (reference)

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
