- Status: done
- Completed: 2026-04-14T17:46:42Z

# Suite Activation: forseti-jobhunter-resume-version-labeling

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-14T17:26:05+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/forseti/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-resume-version-labeling"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-resume-version-labeling/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-resume-version-labeling-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-resume-version-labeling",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-resume-version-labeling"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-resume-version-labeling-<route-slug>",
     "feature_id": "forseti-jobhunter-resume-version-labeling",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-resume-version-labeling",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: forseti-jobhunter-resume-version-labeling

- Feature: forseti-jobhunter-resume-version-labeling
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- At least one resume in `jobhunter_job_seeker_resumes` for the test user
- At least one application in `jobhunter_applications` for the test user
- Update hook run: `drush updb -y`
- Schema check: `drush sql:query "SHOW COLUMNS FROM jobhunter_job_seeker_resumes LIKE 'version%'"` → returns `version_label` and `version_notes`

## Test cases

### TC-1: Schema migration — new columns exist

- **Type:** infrastructure / migration
- **When:** `drush updb` completes
- **Then:** `version_label` and `version_notes` exist on `jobhunter_job_seeker_resumes`; `source_resume_id` exists on `jobhunter_applications`
- **Verify:** `SHOW COLUMNS FROM jobhunter_job_seeker_resumes LIKE 'version%'` → 2 rows; `SHOW COLUMNS FROM jobhunter_applications LIKE 'source_resume_id'` → 1 row

---

### TC-2: Save version label via edit form (smoke)

- **Type:** functional / smoke
- **Given:** existing resume id=<N>; no version_label set
- **When:** submit edit form with `version_label='Tech Lead v2'`
- **Then:** DB updated; resume list page shows label
- **Verify:** `SELECT version_label FROM jobhunter_job_seeker_resumes WHERE id=<N> AND uid=<uid>` → `Tech Lead v2`

---

### TC-3: Resume list page shows version labels

- **Type:** functional / display
- **Given:** resume with version_label='Tech Lead v2'
- **When:** GET `/jobhunter/resume/list`
- **Then:** page shows "Tech Lead v2" next to that resume
- **Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/resume/list` → HTTP 200; response contains `Tech Lead v2`

---

### TC-4: Link resume to application

- **Type:** functional / association
- **Given:** resume id=5 belongs to current user; application id=10 belongs to current user
- **When:** edit application and select resume id=5 as "Resume used"
- **Then:** `source_resume_id=5` stored on application row
- **Verify:** `SELECT source_resume_id FROM jobhunter_applications WHERE id=10 AND uid=<uid>` → 5

---

### TC-5: Application detail shows resume label

- **Type:** functional / display
- **Given:** application id=10 with `source_resume_id=5`; resume 5 has `version_label='Tech Lead v2'`
- **When:** GET application detail page
- **Then:** "Resume used" section shows "Tech Lead v2"
- **Verify:** page HTML contains `Tech Lead v2`

---

### TC-6: Null version_label (unlabeled resume) handled gracefully

- **Type:** functional / edge case
- **Given:** resume with no version_label set (NULL)
- **When:** resume list or application detail renders
- **Then:** no PHP warning; page renders normally; label field shows empty or placeholder
- **Verify:** page HTTP 200 with no Drupal error messages

---

### TC-7: Ownership check on source_resume_id prevents cross-user assignment

- **Type:** security / ownership
- **Given:** resume id=99 belongs to user B
- **When:** user A submits application edit form with `source_resume_id=99`
- **Then:** HTTP 403; `source_resume_id` NOT updated to 99
- **Verify:** `SELECT source_resume_id FROM jobhunter_applications WHERE uid=<uid_A>` → not 99

---

### TC-8: CSRF required on resume edit POST

- **Type:** security / CSRF
- **Given:** authenticated session
- **When:** POST resume edit without valid CSRF token
- **Then:** HTTP 403
- **Verify:** POST without `X-CSRF-Token` → 403

---

### TC-9: version_notes XSS sanitization

- **Type:** security / input sanitization
- **Given:** authenticated user
- **When:** submit version_notes='<script>alert("xss")</script>'
- **Then:** stored as plain text; rendered without script execution
- **Verify:** `SELECT version_notes FROM jobhunter_job_seeker_resumes WHERE uid=<uid>` → no `<script>` tag in stored value

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-resume-version-labeling

- Feature: forseti-jobhunter-resume-version-labeling
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Enhancement to the existing resume management flow. Adds `version_label` (varchar 128, nullable) and `version_notes` (text, nullable) to `jobhunter_job_seeker_resumes`. Adds `source_resume_id` (int, nullable, soft-FK to `jobhunter_job_seeker_resumes.id`) to `jobhunter_applications` so users can track which stored resume version was submitted. Resume list shows labels and notes; application detail page shows the linked resume label.

## Acceptance criteria

### AC-1: Resume list displays version labels

**Given** a user has two resumes — one with `version_label='Software Engineer v3'` and one with no label,
**When** they visit `/jobhunter/resume/list` (or the existing resume management page),
**Then** the labeled resume shows "Software Engineer v3" next to its name; the unlabeled one shows no label or a default placeholder.

**Verify:** `SELECT version_label FROM jobhunter_job_seeker_resumes WHERE uid=<uid> AND resume_name='my_resume'` → `Software Engineer v3`

---

### AC-2: Version label and notes saved via resume edit form

**Given** an authenticated user editing an existing resume,
**When** they enter `version_label='Backend Focus v2'` and `version_notes='Emphasizes Go/Python; minimal frontend'` and submit,
**Then** the resume row is updated with those values.

**Verify:**
```sql
SELECT version_label, version_notes FROM jobhunter_job_seeker_resumes
WHERE uid=<uid> AND id=<resume_id>;
-- Expected: Backend Focus v2 | Emphasizes Go/Python; minimal frontend
```

---

### AC-3: Application detail page shows linked resume label

**Given** an application with `source_resume_id=7` pointing to a resume with `version_label='Tech Lead v1'`,
**When** the user views the application detail page,
**Then** a "Resume used" section appears showing "Tech Lead v1" and a link to the resume detail.

**Verify:** Application detail HTML contains `Tech Lead v1` when `source_resume_id=7`.

---

### AC-4: DB schema — columns added to existing tables

**Given** the module update hook has run,
**When** querying the schema,
**Then** `jobhunter_job_seeker_resumes` contains columns `version_label` (varchar 128, nullable) and `version_notes` (text, nullable); `jobhunter_applications` contains column `source_resume_id` (int, nullable).

**Verify:**
```bash
drush sql:query "SHOW COLUMNS FROM jobhunter_job_seeker_resumes LIKE 'version%'"
drush sql:query "SHOW COLUMNS FROM jobhunter_applications LIKE 'source_resume_id'"
```

---

### AC-5: Linking an application to a resume (save path)

**Given** user has resume id=5 and is creating/editing an application,
**When** they select resume id=5 from the "Resume used" dropdown and submit,
**Then** `source_resume_id=5` is stored on the application row; `tailored_resume_used` is unchanged.

**Verify:** `SELECT source_resume_id FROM jobhunter_applications WHERE id=<app_id> AND uid=<uid>` → 5

---

## Security acceptance criteria

### SEC-1: Authentication required
All resume edit and application detail routes already require auth; new fields must not bypass existing auth gates.

### SEC-2: CSRF protection on POST
Resume edit form POST follows existing CSRF pattern (split-route: POST-only `_csrf_token: 'TRUE'`).

### SEC-3: Ownership check on source_resume_id
When saving `source_resume_id`, the server verifies the referenced resume belongs to the current user: `SELECT id FROM jobhunter_job_seeker_resumes WHERE id=? AND uid=currentUser()->id()`. Reject with HTTP 403 if ownership fails.

### SEC-4: Input sanitization
`version_label` and `version_notes` stored as plain text (no HTML). Display uses Twig auto-escaping only (no `|raw`).

### SEC-5: No resume content in logs
`version_notes` and extracted resume text must NOT appear in watchdog logs. Log only `uid` and `resume_id`.
