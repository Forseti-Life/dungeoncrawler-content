# JobHunter Requirements — JH-04: Application Tracking & Lifecycle

**Status summary:** 2 of 6 requirements COMPLETED, 3 PARTIAL, 1 GAP  
**Last updated:** 2026-02-27  
**Module:** `job_hunter` (forseti.life)

## Overview

This subsystem manages the lifecycle of a job application from initial discovery through submission and final disposition. It provides an opportunity management interface for viewing and filtering all jobs, and records the application status, submission date, and external links for each opportunity. The underlying content type (`application` node) exists; a formal state machine and full submission automation are incomplete.

## Requirements

### REQ-04.1 — Application status lifecycle

**Status:** PARTIAL  
**Code path:** `src/Service/OpportunityManagementService.php`, `application` content type

- A job posting must progress through a defined set of status states: `discovered` → `saved` → `tailored` → `ready-to-apply` → `applied` → `tracking` → `closed`.
- Each status transition must be recorded with a timestamp.
- The system must prevent illogical backward transitions (e.g., moving from `applied` back to `discovered`).
- **GAP (partial):** A formal state machine is not enforced in code; status values are stored on the `application` node but transitions are not validated programmatically.

---

### REQ-04.2 — Manual "applied" marking

**Status:** COMPLETED  
**Code path:** `src/Form/JobApplicationForm.php`, `src/Controller/JobApplicationController.php`

- The user must be able to manually mark a job as "applied" by recording: submission date, external application URL, and any notes.
- This action must update the application status to `applied`.
- The form must be accessible from the job detail view at `/jobhunter/jobs/{job_id}`.

---

### REQ-04.3 — Application record fields

**Status:** COMPLETED  
**Code path:** `application` content type fields

- Each application record must store: application status, submission date, external application URL, associated job posting reference, and user reference.
- Optional fields: notes, recruiter contact, follow-up date, rejection/offer date.

---

### REQ-04.4 — Opportunity management interface

**Status:** PARTIAL  
**Code path:** `src/Controller/OpportunityManagementController.php`, `src/Service/OpportunityManagementService.php`

- The opportunity management interface at `/jobhunter/opportunity-management` must list all of the user's jobs with their current status.
- The list must support filtering by status (e.g., show only "applied" jobs).
- Each row must provide direct links to: job detail, resume tailoring, and edit/delete actions.
- **GAP (partial):** Filtering by status is present in the UI but is not consistently applied across all status values.

---

### REQ-04.5 — Bulk delete of opportunities

**Status:** PARTIAL  
**Code path:** `src/Form/BulkActionsForm.php`

- The user must be able to select multiple opportunities and delete them in bulk.
- Bulk delete must require a confirmation step.
- **GAP (partial):** `BulkActionsForm` exists but bulk operations beyond delete (e.g., bulk status update) are not implemented.

---

### REQ-04.6 — Application history detail view

**Status:** GAP  
**Code path:** N/A

- A per-job detail view must display the full application history: all status changes with timestamps, submission details, tailoring history, and any notes.
- The detail view must be accessible at `/jobhunter/jobs/{job_id}`.
- **GAP:** While the job detail route exists, a structured application timeline/history display has not been implemented; only the current status is shown.

---

## Known Gaps

- No programmatic state machine enforces status transitions or prevents invalid moves (REQ-04.1).
- Application history / timeline view is not implemented (REQ-04.6).
- `ApplicationSubmissionService` exists but portal-specific integrations (e.g., auto-filling Workday or Greenhouse forms) are incomplete; see also JH-05 for ATS detection.
- User registration and onboarding flow (how a new user arrives at the opportunity list) is documented as TODO.
- Bulk status update (in addition to bulk delete) is not available (REQ-04.5).

## Test Coverage

No unit tests exist for `OpportunityManagementService`, `ApplicationSubmissionService`, or `JobApplicationController`. Estimated coverage: **0%** for this subsystem. Priority test targets: status transition validation logic and `OpportunityManagementService` query methods.
