# JobHunter Requirements — JH-08: Security, Permissions & RBAC

**Status summary:** 4 of 6 requirements COMPLETED, 1 PARTIAL, 1 GAP  
**Last updated:** 2026-02-27  
**Module:** `job_hunter` (forseti.life)

## Overview

This subsystem defines the security posture of JobHunter: role-based access control, CSRF protection for state-changing routes, input sanitization, file path validation, cross-user data isolation, and API credential storage. Three critical security vulnerabilities have been identified via code review and are documented here as GAP items requiring immediate remediation before any multi-user or internet-facing deployment.

## Requirements

### REQ-08.1 — CSRF protection on all state-changing AJAX routes

**Status:** COMPLETED — fixed 2026-02-27  
**Code path:** `job_hunter.routing.yml` (routes: `job_discovery_search_ajax`, `save_job`, `tailor_resume_ajax`, `add_skill_to_profile_ajax`, `refresh_skills_gap_ajax`); `js/tailor-resume.js`; `js/job-search-results.js`

- All AJAX routes that modify server-side state (create, update, or delete operations) must enforce Drupal's CSRF token validation.
- CSRF tokens must be embedded in the page at render time and validated server-side on each AJAX request.
- Failure to supply a valid CSRF token must result in a 403 response; no state change must occur.
- **Fix applied:** Removed `options: _csrf_token: FALSE` from all 5 routes; added `_csrf_request_header_mode: 'TRUE'` to each route's requirements block. Updated `tailor-resume.js` to send `X-CSRF-Token` header on 4 AJAX calls; updated `job-search-results.js` to send the token as a request header instead of in the POST body.

---

### REQ-08.2 — SQL injection prevention

**Status:** COMPLETED — fixed 2026-02-27  
**Code path:** `src/Controller/UserProfileController.php` (`getActualQueueStatus()` method)

- All user-supplied input used in database queries must be sanitized using Drupal's database abstraction layer (parameterized queries or `escapeLike()` escaping).
- Raw `LIKE` queries must never be constructed from unsanitized request parameters.
- **Fix applied:** Both `$job_id` and `$user_id` are now cast to `(int)` before use, eliminating injection via type coercion. `LIKE` pattern strings are wrapped with `$database->escapeLike()` to prevent wildcard injection in both the `queue` and `jobhunter_queue_suspended` table queries.

---

### REQ-08.3 — Path traversal prevention

**Status:** COMPLETED — already fixed (pre-existing)  
**Code path:** `src/Controller/DocumentationController.php` (`viewDocument()` method)

- Any controller that accepts a file path or file name as a URL parameter must validate that path against an explicit allowlist of permitted files or directories before constructing a filesystem path.
- User-supplied path components must never be concatenated directly into a `file_get_contents()`, `fopen()`, or equivalent filesystem call.
- **Verified:** `DocumentationController::viewDocument()` has an allowlist of 14 permitted filenames and validates the resolved real path against the expected base directory. No fix required.

---

### REQ-08.4 — Cross-user data isolation

**Status:** PARTIAL  
**Code path:** `src/Service/JobSeekerService.php`, `src/Service/UserProfileService.php`, `src/Service/OpportunityManagementService.php`

- All data retrieval operations (profile, resumes, job postings, applications, tailored resumes) must be scoped to the authenticated user's UID.
- Service methods must not accept a `$uid` parameter from untrusted input without first verifying that the requesting user has permission to access that UID's data.
- No route must allow access to another user's data without explicit administrator authorization.
- **GAP (partial):** Data scoping relies on convention (passing `\Drupal::currentUser()->id()` at the call site) rather than enforced checks within service methods. No automated tests verify isolation. This must be remediated via access checks at the service layer and confirmed by integration tests before multi-user deployment.

---

### REQ-08.5 — Role-based access control (RBAC)

**Status:** PARTIAL  
**Code path:** `job_hunter.permissions.yml`, `docs/PERMISSIONS.md`

- A `job_seeker` role must exist with permissions scoped to all user-facing JobHunter features (profile, job discovery, tailoring, opportunity management).
- An `administrator` role must have access to all admin features: queue management, error queue, settings, company management.
- All routes must declare their required permissions via Drupal's `_permission` or `_role` requirement in `routing.yml`.
- **GAP (partial):** Permissions are defined in `job_hunter.permissions.yml` and documented in `docs/PERMISSIONS.md`, but are not fully enforced on all routes. Some routes rely only on `_user_is_logged_in: TRUE` without role or permission checks.

---

### REQ-08.6 — API credential secure storage

**Status:** COMPLETED  
**Code path:** `src/Service/CredentialManagementService.php`

- All external API credentials (AWS Bedrock, SerpAPI, Google Cloud, Adzuna, USAJobs) must be stored and retrieved via `CredentialManagementService`.
- Credentials must not be stored in plain Drupal config (i.e., not directly in `job_hunter.settings.yml` or any exported config file).
- `CredentialManagementService` must use Drupal's Key module or an equivalent secure storage mechanism to protect credential values at rest.
- Credential retrieval must be auditable (logged at DEBUG level).

---

## Known Gaps

- **CRITICAL:** CSRF protection missing on 5 AJAX routes (REQ-08.1) — remediate before multi-user deployment.
- **CRITICAL:** SQL injection vulnerability in `UserProfileController::queueStatus()` (REQ-08.2) — remediate immediately.
- **CRITICAL:** Path traversal vulnerability in `DocumentationController::viewDocument()` (REQ-08.3) — remediate immediately.
- RBAC is defined but not fully enforced on all routes (REQ-08.5).
- Cross-user data isolation relies on convention; no service-layer enforcement or automated tests (REQ-08.4).
- No security test suite exists; penetration testing has not been performed.

## Test Coverage

No security-focused tests exist. Estimated coverage: **0%**. Priority test targets (in order of urgency):

1. CSRF token validation on the 5 unprotected routes (REQ-08.1).
2. Parameterized query verification in `UserProfileController::queueStatus()` (REQ-08.2).
3. Path allowlist validation in `DocumentationController::viewDocument()` (REQ-08.3).
4. Cross-user data isolation: confirm that accessing `/jobhunter/profile` as User A cannot return User B's data (REQ-08.4).

---

### REQ-08.7 — Output encoding / XSS prevention

**Status:** GAP
**Code path:** `templates/google-jobs-job-detail.html.twig`

- All Twig templates that render user-supplied or API-sourced data must apply explicit output encoding appropriate to the context.
- Data rendered in HTML body context must use `|escape('html')` (or rely on Twig auto-escape, confirmed enabled for the template).
- Data interpolated into HTML event attributes (e.g., `onclick`) must use `|escape('js')` AND the attribute must be rendered via a `data-*` attribute + JS listener pattern (not inline event handlers with Twig interpolation).
- Any field that accepts external API data (e.g., `structured_data_json` from Google Jobs API) must be treated as untrusted and escaped before rendering.
- Failure: stored XSS via a crafted Google Jobs listing is possible with the current unescaped `{{ sync.structured_data_json }}` output.

**Acceptance criteria:**
1. `{{ sync.structured_data_json }}` in `google-jobs-job-detail.html.twig` is rendered via `|escape('html')` (or wrapped in a raw JSON `<script type="application/json">` block with no Twig interpolation of HTML-context values).
2. No `onclick="..."` attributes contain unescaped Twig variable interpolation.
3. QA verifies by injecting `<script>alert(1)</script>` as a job title and confirming it renders as escaped text.

**Added by:** ba-forseti (2026-02-27) — gap candidate from CC-011
