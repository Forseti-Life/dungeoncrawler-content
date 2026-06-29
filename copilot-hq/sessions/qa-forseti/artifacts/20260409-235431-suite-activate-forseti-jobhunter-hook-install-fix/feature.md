# Feature Brief

- Work item id: forseti-jobhunter-hook-install-fix
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260409-forseti-release-j
- Priority: P1
- Feature type: bugfix
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Release target: 20260409-forseti-release-j
- Source: Code review LOW-2 from 20260409-forseti-release-h (code review `8f87ca3c6`)

## Goal

Add the two missing table creation calls to `job_hunter_install()` in `job_hunter.install`. The helper functions `_job_hunter_create_interview_notes_table()` and `_job_hunter_create_saved_searches_table()` exist but are not called during module install, causing fresh install failures (existing sites running `drush updb` are unaffected).

## Acceptance criteria

- [ ] `_job_hunter_create_interview_notes_table()` is called inside `job_hunter_install()` alongside the other table creation helpers
- [ ] `_job_hunter_create_saved_searches_table()` is called inside `job_hunter_install()` alongside the other table creation helpers
- [ ] The two new calls are placed in the same `try` block as existing table creation calls (lines 49–67 of `job_hunter.install`)
- [ ] No changes to the helper function bodies (scope-limited to install hook only)

## Verification

```bash
grep -A40 "function job_hunter_install" sites/forseti/web/modules/custom/job_hunter/job_hunter.install | grep "_job_hunter_create_interview_notes_table\|_job_hunter_create_saved_searches_table"
```

Both function calls must appear in the output.

## Non-goals

- Modifying the table schemas themselves
- Adding update hooks (fresh install fix only; existing sites use `drush updb`)

## Risks

Very low risk — additive change to install hook that cannot affect running sites.

## Security acceptance criteria
- Authentication/permission surface: Install hook runs only for privileged Drupal admin during module install; no user-facing surface.
- CSRF expectations: Not applicable — no routes involved.
- Input validation: Not applicable — no user input involved.
- PII/logging constraints: No PII or credentials involved in table schema creation.
