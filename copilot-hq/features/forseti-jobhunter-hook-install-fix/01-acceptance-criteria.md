# Acceptance Criteria — forseti-jobhunter-hook-install-fix

- Feature: Add missing table creation calls to job_hunter_install()
- Release target: 20260409-forseti-release-j
- PM owner: pm-forseti
- Date groomed: 2026-04-09
- Priority: P1

## Gap analysis reference

Feature type: `bugfix` — Fresh Drupal install of the job_hunter module will fail to create `jobhunter_interview_notes` and `jobhunter_saved_searches` tables because the two helper functions are defined but not called in `hook_install()`. Existing sites are unaffected (tables created via `drush updb`). Fix is additive only.

## Knowledgebase check
- No prior lessons on this specific pattern found.
- Pattern: ensure all `_job_hunter_create_*` helpers are invoked in `job_hunter_install()` alongside the other table creation calls at lines 49–67.

## Happy Path

- [ ] `_job_hunter_create_interview_notes_table()` is called inside `job_hunter_install()` in the table-creation block
- [ ] `_job_hunter_create_saved_searches_table()` is called inside `job_hunter_install()` in the table-creation block
- [ ] Both calls are inside the existing `try` block, after existing table creation calls

## Edge Cases

- [ ] Function calls placed after all existing `_job_hunter_create_*` calls (preserve ordering convention)
- [ ] No duplicate calls — verify functions are not already called elsewhere in `hook_install()` before adding

## Failure Modes

- [ ] If dev adds calls but does not commit `job_hunter.install`, fresh install test will fail — require commit before Gate 2
- [ ] If helper functions throw exceptions, install would fail — test functions are defensive (skip if table exists)

## Permissions / Access Control

- Not applicable — install hook only.

## Data Integrity

- [ ] No DB migration or schema change required for existing sites.
- [ ] Rollback: revert the two lines from `job_hunter_install()`.

## Dev definition of done
- [ ] Both helper function calls appear in `job_hunter_install()` within the table-creation `try` block
- [ ] Verified via: `grep -A40 "function job_hunter_install" sites/forseti/web/modules/custom/job_hunter/job_hunter.install | grep "_job_hunter_create_interview_notes_table\|_job_hunter_create_saved_searches_table"`
- [ ] Both lines appear in grep output

## QA test path
- Verify via static grep that both functions are now called in `job_hunter_install()`.
- Functional test: simulate fresh install (or mock invocation) and confirm both tables are created.
