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
