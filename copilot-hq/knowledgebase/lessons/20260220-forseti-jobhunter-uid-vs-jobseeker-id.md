# Lesson Learned: Do not conflate uid with custom profile IDs

- Date: 2026-02-20
- Agent(s): pm-forseti, dev-forseti, qa-forseti, ceo-copilot
- Website: forseti.life
- Module(s): job_hunter

## What happened
Multiple code paths treated the Drupal user ID (uid) as if it were the primary key used in custom tables (e.g., job_seeker_id), causing incorrect selects/inserts/deletes risk.

## Root cause
- Custom table schema uses its own primary key, while also storing uid.
- Large form file encouraged copy/paste without schema verification.

## Impact
- Potential data integrity issues (wrong resume record association or deletion).
- Harder QA due to inconsistent behaviors.

## Detection / Signals
- Queries using `condition('job_seeker_id', $uid)` against schema-defined `job_seeker_id` that refers to `jobhunter_job_seeker.id`.

## Fix applied (if any)
- Normalize job_seeker_id usage to reference the job seeker profile ID, with legacy fallback where needed.

## Prevention (process + code)
- PM acceptance criteria must include a "data model notes" section for custom tables.
- Dev must verify schema before choosing identifiers.
- Prefer a helper method for deriving the correct IDs to avoid repeated inline logic.

## References
- Related area: JobHunter profile page resume workflow
