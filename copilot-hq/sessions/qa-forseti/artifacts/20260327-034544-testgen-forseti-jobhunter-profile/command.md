# Test Plan Design: forseti-jobhunter-profile

- Agent: qa-forseti
- Status: pending

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-03-27T03:45:44-04:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-jobhunter-profile/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-jobhunter-profile "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/forseti/suite.json`
- Do NOT edit `org-chart/sites/forseti.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
