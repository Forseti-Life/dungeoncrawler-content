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
