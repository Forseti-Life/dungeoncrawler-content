# Test Plan: forseti-jobhunter-job-board-preferences

- Feature: forseti-jobhunter-job-board-preferences
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12
- QA owner: qa-forseti

## Prerequisites

- Authenticated session cookie in `$FORSETI_COOKIE_AUTHENTICATED`
- `jobhunter_source_preferences` table exists: `drush sql:query "DESCRIBE jobhunter_source_preferences"`
- Update hook run: `drush updb -y`

## Test cases

### TC-1: Save new preferences row (smoke)

- **Type:** functional / smoke
- **Given:** authenticated user with no prior preferences row
- **When:** submit preferences form with `sources_enabled=["indeed"]`, `min_salary=75000`, `remote_preference=remote_only`
- **Then:** HTTP 200; row created with correct values
- **Verify:** `SELECT sources_enabled, min_salary, remote_preference FROM jobhunter_source_preferences WHERE uid=<uid>` → `["indeed"] | 75000 | remote_only`

---

### TC-2: Preferences page renders

- **Type:** functional / smoke
- **When:** GET `/jobhunter/preferences/sources`
- **Then:** HTTP 200; form contains source checkboxes and salary/remote fields
- **Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/preferences/sources` → HTTP 200; contains `source-preferences-form`

---

### TC-3: Preferences pre-populate on revisit

- **Type:** functional / state persistence
- **Given:** prior row with `remote_preference=hybrid`
- **When:** GET `/jobhunter/preferences/sources`
- **Then:** remote_preference field shows `hybrid`
- **Verify:** form HTML contains selected `hybrid` option

---

### TC-4: Update preferences (idempotency — no duplicate rows)

- **Type:** functional / update
- **Given:** existing preferences row
- **When:** submit form with changed `min_salary=90000`
- **Then:** row updated; `SELECT COUNT(*) FROM jobhunter_source_preferences WHERE uid=<uid>` → 1
- **Verify:** `SELECT min_salary FROM jobhunter_source_preferences WHERE uid=<uid>` → 90000

---

### TC-5: JobDiscoveryService skips disabled source

- **Type:** functional / integration
- **Given:** user preferences have `sources_enabled=["indeed"]` (LinkedIn excluded)
- **When:** `JobDiscoveryService::discoverJobs($uid)` called
- **Then:** LinkedIn adapter not invoked; only Indeed adapter runs
- **Verify:** service integration log or unit test shows LinkedIn call count = 0

---

### TC-6: Unknown source key rejected

- **Type:** security / input validation
- **Given:** authenticated user
- **When:** submit form with `sources_enabled=["evil_source"]`
- **Then:** form validation error; row NOT saved with unknown source
- **Verify:** `SELECT sources_enabled FROM jobhunter_source_preferences WHERE uid=<uid>` does not contain `evil_source`

---

### TC-7: Salary out-of-bounds rejected

- **Type:** security / bounds validation
- **Given:** authenticated user
- **When:** submit `min_salary=-1` or `min_salary=9999999999`
- **Then:** form validation error; row NOT saved with invalid value
- **Verify:** form returns error message; DB row unchanged

---

### TC-8: Unauthenticated GET returns 403

- **Type:** security / auth gate
- **When:** GET `/jobhunter/preferences/sources` without session cookie
- **Then:** HTTP 403 or redirect to login
- **Verify:** `curl -s -o /dev/null -w "%{http_code}" https://forseti.life/jobhunter/preferences/sources` → `403` or `302`

---

### TC-9: CSRF required on POST

- **Type:** security / CSRF
- **Given:** authenticated session
- **When:** POST without valid CSRF token
- **Then:** HTTP 403
- **Verify:** POST without `X-CSRF-Token` → 403

---

### TC-10: Cross-user isolation — preferences are uid-scoped

- **Type:** security / data isolation
- **Given:** user A has `min_salary=100000`; user B has `min_salary=50000`
- **When:** each user loads the preferences page
- **Then:** each user sees only their own values; user B cannot see user A's salary
- **Verify:** `SELECT min_salary FROM jobhunter_source_preferences WHERE uid=<uid_B>` → 50000 (not 100000)
