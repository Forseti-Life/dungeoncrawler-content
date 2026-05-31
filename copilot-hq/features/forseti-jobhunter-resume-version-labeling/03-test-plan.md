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
