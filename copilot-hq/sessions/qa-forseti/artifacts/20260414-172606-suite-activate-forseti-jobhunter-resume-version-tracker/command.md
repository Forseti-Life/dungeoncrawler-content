- Status: done
- Completed: 2026-04-14T17:55:26Z

# Suite Activation: forseti-jobhunter-resume-version-tracker

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-14T17:26:06+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-resume-version-tracker"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-resume-version-tracker/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-resume-version-tracker-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-resume-version-tracker",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-resume-version-tracker"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-resume-version-tracker-<route-slug>",
     "feature_id": "forseti-jobhunter-resume-version-tracker",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-resume-version-tracker",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-resume-version-tracker

- Feature: forseti-jobhunter-resume-version-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- At least one application row for the test user
- At least one base resume and one tailored resume
- Updated `jobhunter_applications` schema

## Test cases

### TC-1: Submitted resume section renders on application detail

- **Type:** functional / smoke
- **When:** open application detail
- **Then:** `Resume submitted` section is visible for linked applications

---

### TC-2: Updating linked resume persists id/type

- **Type:** functional / happy path
- **When:** select a different owned resume and save
- **Then:** `submitted_resume_id` and `submitted_resume_type` update in DB

---

### TC-3: Resume detail shows where-used list

- **Type:** functional / render
- **When:** open a resume detail page for a resume used by applications
- **Then:** `resume-used-in` section lists matching applications/jobs

---

### TC-4: Cross-user ownership enforced

- **Type:** security / authorization
- **When:** attempt to link another user's resume
- **Then:** HTTP 403 and no DB change

---

### TC-5: Unauthenticated and CSRF failures blocked

- **Type:** security
- **When:** GET/POST without auth or POST without valid CSRF token
- **Then:** requests are blocked appropriately

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-resume-version-tracker

- Feature: forseti-jobhunter-resume-version-tracker
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Track which resume version was submitted with each application by adding
`submitted_resume_id` and `submitted_resume_type` to `jobhunter_applications`,
showing the selected resume on the application detail view, and surfacing a
`Used in applications` list on resume detail pages.

## Acceptance criteria

### AC-1: Application detail shows submitted resume

**Given** an authenticated user who linked a resume to an application,
**When** they view the application or saved-job detail,
**Then** a `Resume submitted` section displays the resume name and type
(`base` or `tailored`).

**Verify:** query `jobhunter_applications.submitted_resume_id` and
`submitted_resume_type` for the application row.

---

### AC-2: User can set/update submitted resume for an application

**Given** an authenticated user viewing an application detail,
**When** they choose a resume from their own resume inventory and save,
**Then** `submitted_resume_id` and `submitted_resume_type` update for that
application.

**Verify:** re-query the application row after save.

---

### AC-3: "Where used" list on resume detail page

**Given** a resume has been used in one or more applications,
**When** the resume detail page renders,
**Then** a `Used in applications` section lists jobs where that resume was used.

**Verify:** rendered count matches a DB count filtered by resume id/type and uid.

---

### AC-4: DB migration — new columns on jobhunter_applications

**Given** the update hook has run,
**When** querying the schema,
**Then** `jobhunter_applications` includes nullable
`submitted_resume_id` and `submitted_resume_type` columns.

**Verify:** `drush sql:query "DESCRIBE jobhunter_applications"`

---

### AC-5: Resume selection is scoped to current user

**Given** an update request references another user's resume,
**When** the controller validates ownership,
**Then** the request returns HTTP 403 and the application row is unchanged.

**Verify:** POST with чужой resume id returns 403; DB remains unchanged.

---

## Security acceptance criteria

### SEC-1: Authentication required

All resume-tracking routes require `_user_is_logged_in: 'TRUE'`.

### SEC-2: CSRF on POST save route

Resume-link updates use split-route CSRF protection.

### SEC-3: Double ownership check

Controller validates ownership of both the application row and the referenced
resume row before updating.

### SEC-4: Minimized display surface

The `where used` view shows resume name/type and job metadata only, not full
resume text.

### SEC-5: No sensitive debug logging

Do not log resume content or job titles at debug severity for this feature.
