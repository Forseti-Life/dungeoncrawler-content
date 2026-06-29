- Status: done
- Completed: 2026-04-14T17:42:02Z

# Suite Activation: forseti-jobhunter-job-board-preferences

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
   **CRITICAL: tag every new entry with `"feature_id": "forseti-jobhunter-job-board-preferences"`**  
   This links the test to the living requirements doc at `features/forseti-jobhunter-job-board-preferences/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "forseti-jobhunter-job-board-preferences-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "forseti-jobhunter-job-board-preferences",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/forseti.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "forseti-jobhunter-job-board-preferences"`**  
   Example:
   ```json
   {
     "id": "forseti-jobhunter-job-board-preferences-<route-slug>",
     "feature_id": "forseti-jobhunter-job-board-preferences",
     "path_regex": "/your-new-route",
     "notes": "Added for feature forseti-jobhunter-job-board-preferences",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: forseti-jobhunter-job-board-preferences

- Feature: forseti-jobhunter-job-board-preferences
- Module: job_hunter
- Author: ba-forseti
- Date: 2026-04-12

## Summary

Per-user job-board source preferences. New table `jobhunter_source_preferences` keyed by `uid` with fields: `sources_enabled` (JSON array of source keys, e.g. `["linkedin","indeed"]`), `min_salary` (int, nullable), `remote_preference` (enum: any/remote_only/hybrid/onsite), `location_radius_km` (smallint, nullable). `JobDiscoveryService` loads these preferences before dispatching source-specific searches, filtering out disabled sources and applying salary/location constraints.

## Acceptance criteria

### AC-1: Preferences form saves a new preferences row

**Given** an authenticated user with no prior preferences row,
**When** they submit the preferences form with `sources_enabled=["linkedin","indeed"]`, `min_salary=80000`, `remote_preference=remote_only`,
**Then** a row is created in `jobhunter_source_preferences` with those values and `uid=currentUser()->id()`.

**Verify:**
```sql
SELECT sources_enabled, min_salary, remote_preference
FROM jobhunter_source_preferences WHERE uid=<uid>;
-- Expected: ["linkedin","indeed"] | 80000 | remote_only
```

---

### AC-2: Preferences page at /jobhunter/preferences/sources

**Given** an authenticated user,
**When** they navigate to `/jobhunter/preferences/sources`,
**Then** the page renders HTTP 200 with a form containing: source checkboxes, salary minimum field, remote preference selector, and location radius field.

**Verify:** `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/preferences/sources` → HTTP 200; contains `source-preferences-form`.

---

### AC-3: JobDiscoveryService respects disabled sources

**Given** user has `sources_enabled=["indeed"]` (LinkedIn disabled),
**When** `JobDiscoveryService::discoverJobs($uid)` is called,
**Then** only Indeed is queried; LinkedIn adapter is NOT invoked.

**Verify:** Service unit test or integration trace showing LinkedIn adapter call count = 0 when not in `sources_enabled`.

---

### AC-4: DB schema — jobhunter_source_preferences table

**Given** the module update hook has run,
**When** querying the schema,
**Then** the table `jobhunter_source_preferences` exists with columns: `id` (serial PK), `uid` (int, unique), `sources_enabled` (text/JSON), `min_salary` (int, nullable), `remote_preference` (varchar 16), `location_radius_km` (smallint, nullable), `created` (int), `changed` (int). Unique key on `uid`.

**Verify:** `drush sql:query "DESCRIBE jobhunter_source_preferences"` → all columns; `SHOW INDEX FROM jobhunter_source_preferences` → unique on `uid`.

---

### AC-5: Preferences update (idempotency)

**Given** user has an existing row with `remote_preference=any`,
**When** they submit the form with `remote_preference=hybrid`,
**Then** the row is updated in-place (not duplicated); `SELECT COUNT(*) FROM jobhunter_source_preferences WHERE uid=<uid>` → 1; `remote_preference` → `hybrid`.

---

## Security acceptance criteria

### SEC-1: Authentication required
All preferences routes require `_user_is_logged_in: 'TRUE'`. Unauthenticated access returns 403.

### SEC-2: CSRF protection on POST
Save route is POST-only with `_csrf_token: 'TRUE'` (split-route pattern; GET page has no CSRF to avoid GET 403 regression).

**Verify:** `grep -A5 "job_hunter.source_preferences" sites/forseti/web/modules/custom/job_hunter/job_hunter.routing.yml | grep "_csrf_token"` → present on POST entry only.

### SEC-3: UID from session only
`uid` written to `jobhunter_source_preferences` must always be `currentUser()->id()`. No uid accepted from POST body or URL.

### SEC-4: Input validation — sources_enabled allowlist
`sources_enabled` values are validated against a hardcoded allowlist (e.g., `["linkedin","indeed","glassdoor","ziprecruiter"]`). Unknown source keys are rejected with a form validation error; they are NOT stored.

### SEC-5: Salary and radius bounds
`min_salary` must be ≥ 0 and ≤ 999999999 (sanitized int). `location_radius_km` must be ≥ 1 and ≤ 500 (smallint). Values outside these bounds return form validation error.
