# Feature Brief

- Work item id: forseti-jobhunter-cover-letter-display
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 1
- Group: profile-resume
- Group Title: Profile & Resume
- Group Sort: 10
- Status: shipped
- Release: 20260409-forseti-release-g
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: CEO direction 2026-04-09 (cover letter generation track), BA grooming 2026-04-09

## Summary

The cover letter generation backend is fully implemented: `CoverLetterTailoringWorker` processes jobs via AWS Bedrock, the `jobhunter_cover_letters` table stores `cover_letter_text`, `cover_letter_html`, `cover_letter_json`, `tailoring_status`, and `pdf_path`. No user-facing display route exists. This feature adds the display UI at `/jobhunter/coverletter/{job_id}`: trigger generation, view the result, download PDF, and save to the job application record. Pattern mirrors `forseti-jobhunter-resume-tailoring-display` (shipped release-f).

## Goal

- Users can trigger cover letter generation for a saved job.
- Users can view the generated cover letter and download it as PDF.
- Users can save the cover letter to their job application for use in submissions.

## Acceptance criteria

- AC-1: Route `/jobhunter/coverletter/{job_id}` renders for authenticated users with `access job hunter` permission. Anonymous access → 403.
- AC-2: When no cover letter record exists for the job: a "Generate Cover Letter" button is shown. Clicking it enqueues a cover letter tailoring job (adds a record with `tailoring_status = queued`).
- AC-3: While `tailoring_status` is `pending/queued/processing`: a status indicator is shown with an auto-refresh hint.
- AC-4: When `tailoring_status = completed`: cover letter HTML is rendered in the display area.
- AC-5: PDF download button is present iff `pdf_path` is non-empty. Links to the private file path.
- AC-6: "Save to application" action sets a flag/link on the job record so the cover letter is associated with that job application. POST-only, CSRF-guarded.
- AC-7: `tailoring_status = failed`: error message shown with retry option.
- AC-8: Users can only view/generate cover letters for their own jobs (`uid == current_user->id()`). Cross-user access → 403/404.

## Non-goals

- Inline editing of the generated cover letter (deferred).
- Email the cover letter to the employer.
- Multiple cover letter versions per job (single active record per user+job as per DB unique key).

## Security acceptance criteria

- Authentication/permission surface: Route requires `_permission: 'access job hunter'` and `_user_is_logged_in: 'TRUE'`. Controller enforces `uid == current_user`. Anonymous access → 403.
- CSRF expectations: "Generate" and "Save to application" are POST-only actions using the split-route pattern (`_csrf_token: 'TRUE'` on POST-only route entries). GET route has no CSRF. Twig `path()` auto-appends `?token=`.
- Input validation requirements: `{job_id}` must be an integer; non-integer → 404. No user input is rendered unescaped. Generated cover letter HTML is already server-produced; display with `{{ cover_letter_html|raw }}` is acceptable only when the source is the server's own AI output (not user-supplied content).
- PII/logging constraints: Cover letter content must NOT be written to watchdog at any level. PDF path must be in Drupal private file system. Error messages logged at WATCHDOG_ERROR must not include cover letter text.

## Implementation notes (to be authored by dev-forseti)

- Follow the same pattern as `ApplicationSubmissionController::jobTailoring` (resume tailoring display).
- New routes needed: `job_hunter.cover_letter` (GET), `job_hunter.cover_letter_generate` (POST + CSRF), `job_hunter.cover_letter_save` (POST + CSRF).
- Queue enqueue: use `\Drupal::queue('job_hunter_cover_letter_tailoring')->createItem(...)`.

## Test plan (to be authored by qa-forseti)

- TC-1: Anonymous access → 403.
- TC-2: No existing record → generate button shown; POST enqueues job; status indicator shown.
- TC-3: Completed cover letter → HTML rendered, PDF download button present.
- TC-4: Save-to-application POST with valid CSRF → success; invalid CSRF → 403.
- TC-5: Failed status → error + retry shown.
- TC-6: Cross-user access → 403/404.
- TC-7: Non-integer job_id → 404.

## Journal

- 2026-04-09: Feature stub created by ba-forseti (pm-forseti dispatch, release-g grooming). Backend (CoverLetterTailoringWorker + DB schema) already shipped.
