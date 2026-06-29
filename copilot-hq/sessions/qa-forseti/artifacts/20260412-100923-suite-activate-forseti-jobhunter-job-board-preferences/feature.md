# Feature Brief

- Work item id: forseti-jobhunter-job-board-preferences
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260412-forseti-release-e
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: low
- Source: CEO feature brief request 2026-04-12 (release-e)

## Summary

The Job Hunter module currently uses multiple job sources (Google Jobs/Cloud Talent Solution, Adzuna API, AbbVie scraper, USA Jobs) but users have no way to configure which sources are active for their searches or set preferences (e.g., "only remote jobs," "minimum salary threshold"). This feature adds a per-user job board preferences page at `/jobhunter/preferences` backed by a `jobhunter_user_preferences` table. Users can toggle which job sources are active and set global search filters (remote-only, location radius, minimum salary). The search aggregator service (`SearchAggregatorService`) reads these preferences when building queries.

## User story

As a job seeker who only wants remote positions and is not interested in job boards that don't match my criteria, I want to configure my job search preferences once so that every search automatically applies my filters without me having to re-enter them.

## Non-goals

- Per-search overrides (global preferences only for this feature)
- Admin-level source enable/disable (that is separate platform configuration)
- Job alert email notifications (separate feature)
- A/B testing of source configurations

## Acceptance criteria

### AC-1: Preferences page renders at /jobhunter/preferences

Given an authenticated user, when they navigate to `/jobhunter/preferences`, then the page displays a preferences form with: source toggles (checkbox per active source: google_jobs, adzuna, usa_jobs, abbvie), remote-only toggle (boolean), location radius (integer, miles, optional), minimum salary (integer, optional). On first visit, all sources are toggled on and other fields are empty.

Verify: `curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/preferences` → HTTP 200; page contains source toggle checkboxes.

### AC-2: Preferences are saved and reloaded on revisit

Given an authenticated user saves preferences with google_jobs=on, adzuna=off, remote_only=true, when they revisit `/jobhunter/preferences`, then the form is pre-populated with the saved values.

Verify: `drush sql:query "SELECT source_prefs_json, remote_only, min_salary FROM jobhunter_user_preferences WHERE uid=<uid>"` → returns the saved values; form fields match on page reload.

### AC-3: SearchAggregatorService respects enabled sources

Given user A has `adzuna=off` in their preferences, when a job search is triggered for user A, then `SearchAggregatorService` does not call the Adzuna API for that request.

Verify: enable debug logging; trigger a search for user A; confirm no Adzuna API call appears in the watchdog log or debug output for that request.

### AC-4: DB schema — jobhunter_user_preferences table

Given the module update hook has run, when querying the schema, then the table `jobhunter_user_preferences` exists with columns: `id` (serial PK), `uid` (int, unique), `source_prefs_json` (text — JSON object mapping source name to boolean), `remote_only` (int tiny, default 0), `location_radius_miles` (int, nullable), `min_salary` (int, nullable), `created` (int), `changed` (int).

Verify: `drush sql:query "DESCRIBE jobhunter_user_preferences"` → all expected columns; unique key on `uid`.

### AC-5: Default preferences applied for users with no saved preferences

Given an authenticated user has no row in `jobhunter_user_preferences`, when a job search runs, then all sources are treated as enabled and no salary/location filters are applied (safe default — no search results dropped).

Verify: delete user's preferences row; trigger a search; confirm all configured sources are queried (check logs/debug); no exceptions thrown.

## Security acceptance criteria

- `/jobhunter/preferences` route and POST endpoint require `_user_is_logged_in: 'TRUE'`
- CSRF token required on POST save route (split-route pattern)
- Controller verifies `uid = currentUser()->id()` on all reads/writes; no uid accepted from URL
- `source_prefs_json` values validated against an allowlist of known source names before storage; unknown source names are rejected with HTTP 422 to prevent injection of arbitrary source keys
- No preference values logged to watchdog at debug severity; log only `uid` and operation type (save/reset)
