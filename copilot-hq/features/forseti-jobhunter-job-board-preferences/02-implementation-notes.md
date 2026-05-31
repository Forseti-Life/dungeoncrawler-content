# Implementation Notes: forseti-jobhunter-job-board-preferences

- Feature: forseti-jobhunter-job-board-preferences
- Author: ba-forseti
- Date: 2026-04-12
- Status: implemented — dev-forseti (2026-04-14)

## Approach

Add a per-user preferences page that persists default search filters and source
toggles, then have `SearchAggregatorService` consult those preferences for each
search request.

## Data model

Primary table: `jobhunter_user_preferences`
- `id`
- `uid` (unique)
- `source_prefs_json`
- `remote_only`
- `location_radius_miles`
- `min_salary`
- `created`
- `changed`

## UI surfaces

- `/jobhunter/preferences`:
  - source toggle checkboxes
  - remote-only checkbox
  - location radius
  - minimum salary

Defaults for users with no row:
- all known sources enabled
- remote-only false
- location radius NULL
- min salary NULL

## Service integration notes

- Add one normalization method in `SearchAggregatorService` to load preferences
  for the current user.
- Unknown source keys must be rejected at save time via allowlist validation.
- If no row exists, service should return safe defaults and continue without
  special handling by callers.

## Implementation completed (dev-forseti 2026-04-14)

### Actual table: `jobhunter_source_preferences` (differs from draft notes)
The draft above used `jobhunter_user_preferences` — the actual implementation uses `jobhunter_source_preferences` with columns: id, uid (unique), sources_enabled (mediumtext/JSON), min_salary (int unsigned), remote_preference (varchar 16), location_radius_km (smallint), created, changed.

### Valid source keys (allowlist)
`['forseti', 'serpapi', 'adzuna', 'usajobs']` — AC example used "linkedin"/"indeed" as placeholders; the actual live adapters are the above.

### remote_preference enum deviation
The AC says `remote_only`; the live code uses `remote` as the stored value (with a backward-compat translation `remote_only`→`remote` on form load). VALID_REMOTE_PREFS: `['any', 'remote', 'hybrid', 'onsite']`.

### Routes (all verified)
- GET `/jobhunter/preferences/sources` → `sourcePreferencesForm()` (no CSRF, auth required)
- POST `/jobhunter/preferences/sources/save` → `sourcePreferencesSave()` (CSRF split-route)
- Legacy alias: POST `/jobhunter/preferences/save` → same controller

### AC coverage
| AC | Status |
|---|---|
| AC-1: save new preferences row | ✅ |
| AC-2: GET /jobhunter/preferences/sources → 200 with form | ✅ |
| AC-3: sources_enabled allowlist filtering | ✅ (VALID_SOURCE_KEYS checked) |
| AC-4: DB schema verified | ✅ |
| AC-5: upsert idempotency | ✅ |
| SEC-1: auth required | ✅ |
| SEC-2: CSRF on POST only (split-route) | ✅ |
| SEC-3: uid from session only | ✅ |
| SEC-4: sources_enabled allowlist (400 on unknown key) | ✅ |
| SEC-5: salary 0–999999999, radius 1–500 (400 on violation) | ✅ |

### Verification commands
```bash
drush sql:query "DESCRIBE jobhunter_source_preferences"
drush sql:query "SHOW INDEX FROM jobhunter_source_preferences"
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/preferences/sources | grep -q 'source-preferences-form'
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
