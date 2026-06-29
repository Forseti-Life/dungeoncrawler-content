# Suite Activation: forseti-jobhunter-profile

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-06T02:42:45+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-profile"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-profile/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-profile-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-profile",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-profile"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-profile-<route-slug>",
     "feature_id": "forseti-jobhunter-profile",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-profile",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan — forseti-jobhunter-profile

- Feature: JobHunter Profile Page (resume upload, profile editing)
- QA owner: qa-forseti
- Date: 2026-04-05 (updated; originally 2026-02-26)
- Release target: 20260405-forseti-release-c (updated; originally 20260226-forseti-release-b)
- AC source: features/forseti-jobhunter-profile/01-acceptance-criteria.md

## Knowledgebase references
- KB lesson: `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — do not conflate uid with job_seeker_id; critical for cross-user access checks (TC-09, TC-11).
- PM review: `sessions/pm-forseti/artifacts/20260220-job-hunter-profile-review/pm-review.md` — top risks: cross-user access leaks, resume parsing queue failures, JSON corruption, file safety gaps.

## Suite assignments
- Route/ACL tests → `role-url-audit` suite
- Form/E2E/file upload tests → `jobhunter-e2e` suite (Playwright, extend existing script or new file)

## Test cases

### TC-01: /jobhunter/profile redirects to /jobhunter/profile/edit
- Description: Accessing `/jobhunter/profile` as an authenticated user redirects to `/jobhunter/profile/edit` and the edit form renders.
- Suite: `role-url-audit`
- Expected: HTTP 200 at final URL `/jobhunter/profile/edit`; form present in page body
- Roles: authenticated → allow (HTTP 200 at redirect destination); anon → deny (redirect to login)

### TC-02: /jobhunter/profile/edit accessible to authenticated user
- Description: Profile edit form renders at `/jobhunter/profile/edit`.
- Suite: `role-url-audit`
- Expected: HTTP 200 for authenticated; HTTP 403 for anon
- Roles: authenticated → allow; anon → deny; administrator → allow

### TC-03: Profile form fields render correctly
- Description: The edit form renders all expected fields (job titles, keywords, work authorization, resume upload input).
- Suite: `jobhunter-e2e`
- Script: New Playwright test (or extend `testing/jobhunter-workflow-step1-6-data-engineer.mjs` step 1): login via ULI → navigate to `/jobhunter/profile/edit` → assert form fields present
- Expected: Key field selectors visible: `textarea[name="field_target_job_titles"]`, resume file input `input[type="file"]`; no blank page
- Roles: authenticated

### TC-04: Resume upload — PDF persists
- Description: Authenticated user uploads a valid PDF resume under the size limit; it persists and appears as downloadable.
- Suite: `jobhunter-e2e`
- Script: Playwright — upload a small test PDF (stored in `testing/fixtures/test-resume.pdf` or create minimal fixture); submit form; verify file appears in download list
- Expected: Form submits without error; download link `/jobhunter/resume/download/{fid}` is present on page reload
- Roles: authenticated
- Note: Requires a test PDF fixture. If no fixture exists in `testing/`, one must be provided at Stage 0.

### TC-05: Resume upload — Word document persists
- Description: Authenticated user uploads a valid .docx resume; it persists.
- Suite: `jobhunter-e2e`
- Script: Same as TC-04 with a .docx fixture
- Expected: Same as TC-04
- Roles: authenticated
- Note: Same fixture dependency as TC-04.

### TC-06: Consolidated profile JSON saved correctly on form submit
- Description: After form submit, `jobhunter_job_seeker.consolidated_profile_json` is updated with the new profile data.
- Suite: `jobhunter-e2e`
- Verification approach: After Playwright form submit, run a drush query (or DB check script) to verify the JSON field is non-null and contains the submitted values.
- Expected: `consolidated_profile_json` column updated; JSON is valid and contains expected field values (e.g., job titles entered in the form)
- Roles: authenticated
- Automation flag: Requires DB-level assertion post-submit. Prefer a Playwright + drush verification step. Dev should expose a `/jobhunter/profile/summary` endpoint readable by the test user as an alternative.

### TC-07: Profile completeness score displayed and updates after save
- Description: After submitting the profile edit form, a completeness score/indicator is visible and reflects the updated profile.
- Suite: `jobhunter-e2e`
- Script: Playwright — submit form with more fields filled in; assert completeness indicator is present and has changed value
- Expected: Completeness score element visible on `/jobhunter/profile/edit` or `/jobhunter/profile/summary`; value increases when more fields are filled
- Roles: authenticated

### TC-08: Resume download action accessible to owning user only
- Description: The owning user can download their own resume via `/jobhunter/resume/download/{fid}`; other users cannot.
- Suite: `role-url-audit` + `jobhunter-e2e`
- Expected (role-url-audit): `/jobhunter/resume/download/{fid}` → HTTP 200 for owning authenticated user; HTTP 403 for different authenticated user; HTTP 403/redirect for anon
- Expected (E2E): Download link clicked → file served; no error
- Roles: owning authenticated → allow; other authenticated → deny; anon → deny
- Note: fid is parameterized; role-url-audit rule should use a pattern match and mark as ignore for non-owning authenticated per existing qa-permissions.json pattern.

### TC-09: Resume delete action accessible to owning user only
- Description: Resume delete `/jobhunter/resume/delete/{resume_id}` accessible to owning user; denied to others.
- Suite: `role-url-audit`
- Expected: HTTP 200/redirect for owner; HTTP 403 for other authenticated; HTTP 403 for anon
- Roles: owning authenticated → allow; other authenticated → deny; anon → deny

### TC-10: Oversized file upload shows error; existing data not corrupted
- Description: Uploading a file exceeding the size limit shows a clear error message; the existing resume/profile data is still intact.
- Suite: `jobhunter-e2e`
- Script: Playwright — attempt upload of a large fake file (generated in-test, e.g., 20MB random bytes); assert error message visible; assert previous resume still listed
- Expected: Form shows file size error; no prior data removed; no stack trace
- Roles: authenticated

### TC-11: Cross-user profile access blocked (different UID)
- Description: Authenticated user B visiting user A's `/jobhunter/profile/edit` URL is denied (no cross-user data leakage).
- Suite: `jobhunter-e2e` + `role-url-audit`
- Script: Playwright with two sessions — user A creates a profile; user B attempts to access user A's profile edit URL
- Expected: HTTP 403 or redirect to user B's own profile; user A's data not visible
- Roles: other authenticated → deny
- Automation flag: Same dual-user session requirement as noted in forseti-jobhunter-e2e-flow TC-16; requires two QA users. KB lesson: verify that controller uses job_seeker_id (not uid) for the UID ownership check to avoid the known uid-vs-jobseeker-id confusion.

### TC-12: Form reload after JSON parse error — no crash
- Description: If the stored `consolidated_profile_json` is malformed, the form reloads with a recoverable error state (no blank page or fatal crash).
- Suite: `jobhunter-e2e`
- Automation flag: Requires injecting a corrupt JSON value into the DB before the test (Dev must provide a mechanism or test fixture SQL). Manual verification acceptable for this release. **Flagged to PM: recommend Dev provides a test fixture or error injection hook at Stage 0.**
- Expected: Form renders with error message; does not show a blank page or PHP stack trace
- Roles: authenticated

### TC-13: Error messages are actionable — no raw PHP stack traces
- Description: Error conditions in the profile form show user-friendly messages; no raw PHP/stack trace output in the browser.
- Suite: `jobhunter-e2e`
- Script: Playwright — inspect page body / console errors for stack trace patterns (`#[0-9]`, `Drupal\`, `/home/`) across all profile form scenarios
- Expected: No stack trace fragments in page body or browser console errors
- Roles: authenticated

### TC-14: Resume parsing queue failure shows graceful UI state
- Description: When resume parsing queue fails, the UI shows a "processing" or retry state instead of silent failure.
- Suite: `jobhunter-e2e`
- Automation flag: Requires queue failure simulation. Dev must expose a mechanism to trigger a queue failure in a test environment (e.g., queue-disable env var or drush command). Manual verification acceptable for this release. **Flagged to PM: Dev should confirm/document retry UX behavior and expected queue status URL (`/jobhunter/queue/status`).**
- Expected: After upload with queue failure: `/jobhunter/queue/status` shows error state; user sees "processing" or "retry" prompt; no silent data loss
- Roles: authenticated

### TC-15: Anonymous redirect on all profile routes — no profile data exposed
- Description: Anonymous users on all `/jobhunter/profile*` and `/jobhunter/resume/*` routes are redirected to login; no profile data is visible.
- Suite: `role-url-audit`
- Expected: HTTP 403/redirect for anon on: `/jobhunter/profile`, `/jobhunter/profile/edit`, `/jobhunter/profile/dashboard`, `/jobhunter/profile/summary`, `/jobhunter/resume/download/{fid}`, `/jobhunter/resume/delete/{resume_id}`
- Roles: anon → deny (all above routes)

### TC-16: Admin access to profile edit pages
- Description: Administrator can access `/jobhunter/profile/edit` and profile-related routes without unexpected 403.
- Suite: `role-url-audit`
- Expected: HTTP 200 for administrator on `/jobhunter/profile/edit`
- Roles: administrator → allow
- Note: Document if admin access to OTHER users' profile edit pages is intentional or blocked.

### TC-17: Existing consolidated profile JSON not corrupted by partial save
- Description: If a form submit fails mid-write (e.g., validation error), the existing `consolidated_profile_json` is unchanged.
- Suite: `jobhunter-e2e`
- Script: Playwright — trigger a validation error (e.g., submit with an invalid file); verify DB JSON matches pre-submit value
- Expected: JSON column unchanged after failed submit; data integrity preserved
- Roles: authenticated
- Automation flag: Requires DB read before/after. Recommend Dev provide a profile data read endpoint or drush command for test assertions.

### TC-18: /jobhunter/profile/dashboard and /profile/summary accessible to authenticated
- Description: Profile dashboard and summary pages render without error for authenticated users.
- Suite: `role-url-audit`
- Expected: HTTP 200 for authenticated on `/jobhunter/profile/dashboard`, `/jobhunter/profile/summary`; HTTP 403 for anon
- Roles: authenticated → allow; anon → deny; administrator → allow

## Automation flags summary (for PM)

| TC | Flag |
|----|------|
| TC-04, TC-05 | Requires PDF/.docx test fixture in `testing/fixtures/` — must be provided at Stage 0 |
| TC-06 | Requires DB-level assertion or a `/profile/summary` JSON endpoint for post-submit verification |
| TC-11 | Requires dual-user QA session (same as forseti-jobhunter-e2e-flow TC-16) |
| TC-12 | Requires corrupt-JSON DB injection mechanism from Dev |
| TC-14 | Requires queue failure simulation mechanism from Dev; document retry UX |
| TC-17 | Requires DB read before/after submit; Dev to provide test assertion path |

## Suite activation (Stage 0 only)
Do NOT edit `qa-suites/products/forseti/suite.json` or `qa-permissions.json` until this feature is selected into release scope at Stage 0.

Planned qa-permissions.json additions at Stage 0:
- `/jobhunter/profile` → authenticated: allow; anon: deny; administrator: allow
- `/jobhunter/profile/edit` → authenticated: allow; anon: deny; administrator: allow
- `/jobhunter/profile/dashboard` → authenticated: allow; anon: deny; administrator: allow
- `/jobhunter/profile/summary` → authenticated: allow; anon: deny; administrator: allow
- `/jobhunter/resume/download/{fid}` → authenticated: ignore (parameterized, owner-only); anon: deny
- `/jobhunter/resume/delete/{resume_id}` → authenticated: ignore (parameterized, owner-only); anon: deny

### Acceptance criteria (reference)

# Acceptance Criteria — forseti-jobhunter-profile

- Feature: JobHunter Profile Page (resume upload, profile editing)
- Release target: 20260226-forseti-release-b
- PM owner: pm-forseti
- Date groomed: 2026-02-26

## Knowledgebase check
- Related: sessions/pm-forseti/artifacts/20260220-job-hunter-profile-review/pm-review.md

## Happy Path
- [ ] `[REQ-ID: REQ-01.1]` `/jobhunter/profile` redirects to `/jobhunter/profile/edit` and the edit form renders correctly.
- [ ] `[REQ-ID: REQ-01.1]` Authenticated user can upload a PDF or Word resume (under file size limit) and it persists.
- [ ] `[REQ-ID: REQ-01.4]` Consolidated profile JSON is saved correctly to `jobhunter_job_seeker.consolidated_profile_json` on form submit.
- [ ] `[REQ-ID: REQ-01.6]` Profile completeness score is displayed and updates after save.
- [ ] `[REQ-ID: REQ-01.1]` Resume download and delete actions succeed and are visible only to the owning user.

## Edge Cases
- [ ] `[REQ-ID: REQ-01.1]` Uploading an oversized or malformed file shows a clear error and does not corrupt existing data.
- [ ] `[REQ-ID: REQ-01.8]` Visiting `/jobhunter/profile` as another authenticated user (different UID) returns 403 or redirects — no cross-user data visible.
- [ ] `[REQ-ID: REQ-01.4]` Form reload after a JSON parse error does not crash; user sees a recoverable error state.

## Failure Modes
- [ ] `[REQ-ID: REQ-01.2]` Error messages are clear and actionable (no raw PHP stack traces to end users).
- [ ] `[REQ-ID: REQ-01.2]` Resume parsing queue failure surfaces a retry option or graceful "processing" state in the UI.

## Permissions / Access Control
- [ ] `[REQ-ID: REQ-08.5]` Anonymous user: redirected to login (no profile data exposed).
- [ ] `[REQ-ID: REQ-08.4, REQ-01.8]` Authenticated user: can only view/edit their own profile (enforced by `access job hunter` permission + UID check).
- [ ] `[REQ-ID: REQ-08.5]` Admin: can access any profile edit page (or explicit exception documented).

## Data Integrity
- [ ] `[REQ-ID: REQ-01.4]` Existing consolidated profile JSON is not corrupted by partial saves.
- [ ] `[REQ-ID: REQ-01.4]` Rollback path: revert to previous JSON snapshot if new save fails mid-write.

## New profile fields — ATS automation assist (added 2026-03-08, commit 7dea91e8f)

The following 6 fields were added to `UserProfileForm` for use by the Workday wizard automation. Each field stored in `consolidated_profile_json`.

### Acceptance criteria (new fields)
- [ ] `[REQ-ID: REQ-01.4]` `field_age_18_or_older` (radio: yes/no) — renders on profile form; value persists in `consolidated_profile_json.job_search_preferences.age_18_or_older` on save.
- [ ] `[REQ-ID: REQ-01.4]` `field_hear_about_us` (text) — renders on profile form; value persists in `consolidated_profile_json.job_search_preferences.hear_about_us` on save.
- [ ] `[REQ-ID: REQ-01.4]` `field_prior_company_employment` (radio: yes/no) — renders on profile form; value persists in JSON.
- [ ] `[REQ-ID: REQ-01.4]` `field_prior_company_wwid` (text) — renders on profile form; value persists in JSON. **Sensitive: this is an employer-internal ID (Intel WWID pattern).**
- [ ] `[REQ-ID: REQ-01.4]` `field_prior_company_email` (email) — renders on profile form; value persists in JSON. **PII: prior employer email address.**
- [ ] `[REQ-ID: REQ-01.4]` `field_phone_device_type` (select) — renders on profile form; value persists in JSON.
- [ ] `[REQ-ID: REQ-01.4]` `field_country` (text) — renders in core_info location section; value persists in `consolidated_profile_json.contact_info.location.country`.

### PII deletion / export / retention
- [ ] `[REQ-ID: REQ-01.4, REQ-NONE]` Deleting the user's profile (or account) deletes all `consolidated_profile_json` data including new fields (prior company email, WWID). Verify: no PII remains in `jobhunter_job_seeker` after user account deletion.
- [ ] `[REQ-ID: REQ-NONE]` **PM decision required:** confirm whether prior company email and WWID require a user-facing disclosure (privacy notice or consent prompt on profile form). Until confirmed, no disclosure change is required. This AC item must be resolved before GDPR-subject users fill the form.

### Schema consistency
- [ ] `[REQ-ID: REQ-01.4]` `field_age_18_or_older` is stored in JSON only (no separate DB column added). The `db_column: 'age_18_or_older'` fallback in `getConsolidatedValue` fails silently (no DB error). Verify: submitting profile form with `field_age_18_or_older` set returns 200 and value is readable from consolidated JSON on next load.

## Verification method
- Run QA suite: `qa-suites/products/forseti/suite.json` (profile tests).
- Manual: authenticate as test user, upload PDF, submit form, verify DB row in `jobhunter_job_seeker`.
- Cross-user check: log in as user B, attempt to access user A's profile edit URL, confirm 403.
- New fields: authenticate, navigate to `/jobhunter/profile/edit`, set each new field, save, reload — verify values are restored from JSON.
- Agent: qa-forseti
- Status: pending
