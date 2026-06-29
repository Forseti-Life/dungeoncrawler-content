# JobHunter Requirements — JH-01: User Profile & Resume Pipeline

**Status summary:** 5 of 8 requirements COMPLETED, 2 PARTIAL, 1 GAP  
**Last updated:** 2026-02-27  
**Module:** `job_hunter` (forseti.life)

## Overview

This subsystem manages the user's professional identity within JobHunter. It handles resume file uploads, asynchronous text extraction and GenAI parsing, consolidation of parsed data into a unified job-seeker profile, and the computation of profile completeness. The consolidated profile feeds every downstream AI tailoring operation.

## Requirements

### REQ-01.1 — Resume file upload

**Status:** COMPLETED  
**Code path:** `src/Form/UserProfileForm.php`, `src/Controller/UserProfileController.php`

- The system must allow an authenticated user to upload one or more resume files in PDF or DOCX format.
- Uploaded files must be stored and associated with the user's account.
- Each upload must be recorded in the `jobhunter_job_seeker_resumes` table.

---

### REQ-01.2 — Resume text extraction (queue)

**Status:** COMPLETED  
**Code path:** `src/Plugin/QueueWorker/ResumeTextExtractionWorker.php`

- The system must extract plain text from each uploaded resume file via an asynchronous queue worker (`ResumeTextExtractionWorker`).
- Extraction must support both PDF and DOCX file formats.
- Extracted text must be stored for downstream GenAI parsing.
- If extraction fails, the queue item must follow the standard 3-retry / auto-suspension policy (see JH-06).

---

### REQ-01.3 — GenAI resume parsing (queue)

**Status:** COMPLETED  
**Code path:** `src/Plugin/QueueWorker/ResumeGenAiParsingWorker.php`

- The system must parse extracted resume text into structured JSON via AWS Bedrock (Claude 3.5 Sonnet) using an asynchronous queue worker (`ResumeGenAiParsingWorker`).
- The resulting JSON must conform to the schema defined in `docs/RESUME_JSON_SCHEMA.md`.
- Parsed data must be stored in `jobhunter_resume_parsed_data`.
- If parsing fails, the queue item must follow the standard 3-retry / auto-suspension policy.

---

### REQ-01.4 — Consolidated job-seeker profile

**Status:** COMPLETED  
**Code path:** `src/Service/JobSeekerService.php`

- The system must consolidate parsed data from one or more resumes into a single unified profile record in `jobhunter_job_seeker`.
- `JobSeekerService` must provide CRUD operations for the `jobhunter_job_seeker` table.
- When multiple resumes exist for a user, the consolidation logic must merge them without duplication.

---

### REQ-01.5 — Profile data fields

**Status:** COMPLETED  
**Code path:** `src/Service/JobSeekerService.php`, `jobhunter_job_seeker` table

- The consolidated profile must store, at minimum: contact information, work history (employer, title, dates, description), education (institution, degree, dates), skills (name, category, proficiency), and achievements/certifications.
- All fields must be stored in a format suitable for direct injection into AI prompts.

---

### REQ-01.6 — Profile completeness score

**Status:** COMPLETED  
**Code path:** `src/Service/UserProfileService.php`

- The system must calculate a profile completeness percentage based on the presence and quality of key profile fields.
- The completeness score must be surfaced to the user on the profile page at `/jobhunter/profile`.
- `UserProfileService` must provide field-level recommendations indicating which fields are missing or incomplete.

---

### REQ-01.7 — Consolidated profile for AI consumption

**Status:** PARTIAL  
**Code path:** `src/Service/UserJobProfileService.php`

- `UserJobProfileService` must expose a consolidated, AI-ready representation of the user's profile, combining `jobhunter_job_seeker` data with any additional Drupal user fields.
- The service must be injectable and usable by `ResumeTailoringWorker` and `CoverLetterTailoringWorker`.
- **GAP (partial):** The format and completeness of the AI-ready output has not been independently validated against all tailoring prompt templates.

---

### REQ-01.8 — Per-user data isolation

**Status:** GAP  
**Code path:** `src/Service/JobSeekerService.php`, `src/Controller/UserProfileController.php`

- All profile data, resume files, and parsed results must be strictly scoped to the authenticated user.
- No API endpoint or service method may return another user's profile data without explicit administrator authorization.
- **GAP:** Cross-user data isolation has not been audited or enforced via automated tests. The security review identified that user-scoping relies on convention rather than enforced access checks. This must be addressed before multi-user deployment.

---

## Known Gaps

- Cross-user data isolation is not enforced at the service layer (REQ-01.8).
- User registration / onboarding flow is documented as TODO; new users have no guided path to upload their first resume.
- `ProfileTextExtractionWorker` also exists in the queue worker directory — its relationship to `ResumeTextExtractionWorker` should be clarified and deduplicated.
- Merge logic for multiple resumes (REQ-01.4) is not covered by unit tests.

## Test Coverage

Three unit test files exist in the module; none specifically target `JobSeekerService`, `UserProfileService`, or `UserJobProfileService`. Estimated coverage of this subsystem: **< 10%**. Priority test targets: `JobSeekerService` CRUD operations, completeness calculation in `UserProfileService`, and the merge logic for multi-resume consolidation.
