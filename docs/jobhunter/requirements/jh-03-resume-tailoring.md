# JobHunter Requirements — JH-03: AI Resume & Cover Letter Tailoring

**Status summary:** 8 of 9 requirements COMPLETED, 1 PARTIAL, 0 GAP  
**Last updated:** 2026-02-27  
**Module:** `job_hunter` (forseti.life)

## Overview

This subsystem produces AI-tailored resumes and cover letters for specific job postings using AWS Bedrock (Claude 3.5 Sonnet). It manages the queue-based tailoring pipeline, renders output as downloadable PDFs, and maintains a per-job PDF history. The tailoring UI is accessible to the user at `/jobhunter/jobtailoring/{job_id}`.

## Requirements

### REQ-03.1 — AI resume tailoring per job posting

**Status:** COMPLETED  
**Code path:** `src/Plugin/QueueWorker/ResumeTailoringWorker.php`

- For each job posting, the system must generate a tailored version of the user's resume via an asynchronous queue worker (`ResumeTailoringWorker`).
- Tailoring must use AWS Bedrock with the Claude 3.5 Sonnet model.
- The tailored resume must be stored in `jobhunter_tailored_resumes` and associated with both the user and the job posting node.
- Tailoring must be idempotent: re-triggering for the same job must overwrite the previous tailored result.

---

### REQ-03.2 — Consolidated profile as tailoring input

**Status:** COMPLETED  
**Code path:** `src/Plugin/QueueWorker/ResumeTailoringWorker.php`, `src/Service/UserJobProfileService.php`

- The resume tailoring worker must use `UserJobProfileService` to retrieve the user's consolidated profile as the primary input to the AI prompt.
- The prompt must include: full work history, skills, education, achievements, and any user-supplied objective or summary.

---

### REQ-03.3 — Job description as tailoring input

**Status:** COMPLETED  
**Code path:** `src/Plugin/QueueWorker/ResumeTailoringWorker.php`

- The resume tailoring worker must use the full job description text of the target `job_posting` node as the secondary input to the AI prompt.
- The job description must include, where available: required skills, responsibilities, qualifications, and employer context.

---

### REQ-03.4 — AI cover letter generation

**Status:** COMPLETED  
**Code path:** `src/Plugin/QueueWorker/CoverLetterTailoringWorker.php`

- The system must generate a cover letter tailored to each job posting via a separate queue worker (`CoverLetterTailoringWorker`).
- Cover letter generation must use AWS Bedrock (Claude 3.5 Sonnet).
- The cover letter must be stored in association with the corresponding `job_posting` node.
- Cover letter generation must be independently triggerable from resume tailoring.

---

### REQ-03.5 — Tailored resume PDF generation

**Status:** COMPLETED  
**Code path:** `src/Service/ResumePdfService.php`, route `job_hunter.resume_generate`

- The system must render the tailored resume as a downloadable PDF via `ResumePdfService`.
- The PDF generation endpoint is `/jobhunter/jobs/{job_id}/resume/generate`.
- The generated PDF must be downloadable at `/jobhunter/jobs/{job_id}/resume/pdf`.
- The base (non-tailored) resume PDF must also be downloadable at `/jobhunter/resume/pdf`.

---

### REQ-03.6 — Configurable PDF style

**Status:** COMPLETED  
**Code path:** `src/Service/ResumePdfService.php`, `config/resume_styles/keith_aumiller.json`

- PDF output style (fonts, layout, section ordering, color scheme) must be configurable per user via a JSON style definition file.
- `config/resume_styles/keith_aumiller.json` is the reference/default style implementation.
- The system must be capable of supporting multiple style definitions without code changes.
- The schema for style files is defined in `docs/RESUME_PDF_STYLE_SCHEMA.md`.

---

### REQ-03.7 — PDF history per job per user

**Status:** COMPLETED  
**Code path:** `src/Service/ResumePdfService.php`, `jobhunter_pdf_history` table

- Every generated PDF must be recorded in `jobhunter_pdf_history` with: user ID, job posting ID, generation timestamp, file path, and style identifier.
- The history must allow the user to retrieve previously generated PDFs without re-generating them.

---

### REQ-03.8 — Retry-safe tailoring with 3-retry limit

**Status:** COMPLETED  
**Code path:** `src/Traits/QueueWorkerBaseTrait.php`, `src/Plugin/QueueWorker/ResumeTailoringWorker.php`, `src/Plugin/QueueWorker/CoverLetterTailoringWorker.php`

- Tailoring queue items must be retried automatically up to 3 times upon failure before being suspended.
- Each retry must be logged with the failure reason.
- Suspended tailoring items must be reviewable and re-triggerable via the queue management UI at `/jobhunter/queue-management`.
- Retry counting and suspension logic must be provided by `QueueWorkerBaseTrait`.

---

### REQ-03.9 — Manual tailoring trigger via UI

**Status:** PARTIAL  
**Code path:** `src/Controller/ResumeController.php`, route `job_hunter.job_tailoring`

- The user must be able to manually trigger resume tailoring for a specific job via the UI at `/jobhunter/jobtailoring/{job_id}`.
- The trigger must dispatch items to the tailoring queue and display a confirmation to the user.
- The AJAX endpoint for triggering tailoring is `job_hunter.tailor_resume_ajax`.
- **GAP (partial):** The AJAX trigger route (`tailor_resume_ajax`) currently lacks CSRF protection (tracked as a critical security gap in JH-08 REQ-08.1). The UI itself is functional but must not be considered production-ready until CSRF is enforced.

---

## Known Gaps

- CSRF protection is missing on the `tailor_resume_ajax` route (REQ-03.9); tracked as HIGH priority in JH-08.
- Cover letter output format and storage schema are not formally documented.
- No mechanism exists to compare the current tailored resume against a previous version for the same job.

## Test Coverage

No unit tests exist for `ResumeTailoringWorker`, `CoverLetterTailoringWorker`, or `ResumePdfService`. Estimated coverage: **0%** for this subsystem. Priority test targets: `ResumePdfService` PDF rendering, `ResumeTailoringWorker` retry logic (via `QueueWorkerBaseTrait`), and idempotency of re-tailoring.

---

### REQ-03.10 — AI tailoring cache invalidation policy

**Status:** GAP
**Code path:** `src/Plugin/QueueWorker/ResumeTailoringWorker.php`

- The AI tailoring result cache must have a defined invalidation policy specifying when a cached result is considered stale and must be regenerated.
- Cache key: `(uid, job_id, section)` — currently implemented.
- Cache invalidation must trigger when: (a) the user's consolidated profile changes materially (new resume parsed, profile fields updated), or (b) the user explicitly requests a re-tailoring of a specific job.
- Returning a cached result silently without informing the user is not acceptable if the user's profile has changed since the result was cached.
- A cache TTL (time-to-live) of at most 7 days is recommended as a fallback even without explicit invalidation.

**Acceptance criteria:**
1. Re-tailoring the same job after updating the consolidated profile generates a new AI result (not the cached version).
2. The user can trigger re-tailoring explicitly from the UI (REQ-03.9 PARTIAL — may be linked).
3. Cached results older than 7 days are regenerated on next request.
4. QA verifies by updating a profile field, re-tailoring the same job, and confirming the output differs from the prior cached version.

**Added by:** ba-forseti (2026-02-27) — gap candidate from CC-007
