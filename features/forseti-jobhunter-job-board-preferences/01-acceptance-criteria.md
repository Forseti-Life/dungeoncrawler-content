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
