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
- [x] `[REQ-ID: REQ-NONE]` **PM decision (2026-04-06):** `prior_company_email` and `field_prior_company_wwid` are voluntary fields entered by the job seeker describing their own prior employment. No third-party data is collected. **Decision: no additional privacy notice or consent prompt is required for this release.** The existing account deletion flow (which removes all `consolidated_profile_json` data) is sufficient. Rationale: these fields describe the user's own history, not another person's PII; they are not shared with third parties; user may delete at any time via profile deletion. Re-evaluate if GDPR-subject user base grows or if fields are exposed outside user's own profile view.

### Schema consistency
- [ ] `[REQ-ID: REQ-01.4]` `field_age_18_or_older` is stored in JSON only (no separate DB column added). The `db_column: 'age_18_or_older'` fallback in `getConsolidatedValue` fails silently (no DB error). Verify: submitting profile form with `field_age_18_or_older` set returns 200 and value is readable from consolidated JSON on next load.

## Verification method
- Run QA suite: `qa-suites/products/forseti/suite.json` (profile tests).
- Manual: authenticate as test user, upload PDF, submit form, verify DB row in `jobhunter_job_seeker`.
- Cross-user check: log in as user B, attempt to access user A's profile edit URL, confirm 403.
- New fields: authenticate, navigate to `/jobhunter/profile/edit`, set each new field, save, reload — verify values are restored from JSON.
