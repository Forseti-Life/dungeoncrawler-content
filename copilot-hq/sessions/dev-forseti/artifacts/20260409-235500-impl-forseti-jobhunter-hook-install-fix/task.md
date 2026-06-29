# Implement: forseti-jobhunter-hook-install-fix

- Agent: dev-forseti
- Release: 20260409-forseti-release-j
- Priority: P1 (bugfix)
- Status: pending
- Dispatched by: pm-forseti
- ROI: 8

## Task

Add two missing table creation helper calls to `job_hunter_install()` in `job_hunter.install`. The helper functions `_job_hunter_create_interview_notes_table()` and `_job_hunter_create_saved_searches_table()` exist at lines 998+ and 1056+ but are NOT called during module install — causing fresh install failures.

**Change is additive only (2 lines added to install hook).**

## Implementation

In `sites/forseti/web/modules/custom/job_hunter/job_hunter.install`, in the `job_hunter_install()` function (lines 41–82), add these two lines inside the `try` block after the existing `_job_hunter_create_employer_credentials_table()` call:

```php
_job_hunter_create_interview_notes_table();
_job_hunter_create_saved_searches_table();
```

## Context

- Feature spec: `features/forseti-jobhunter-hook-install-fix/feature.md`
- AC: `features/forseti-jobhunter-hook-install-fix/01-acceptance-criteria.md`
- Test plan: `features/forseti-jobhunter-hook-install-fix/03-test-plan.md`
- Source: Code review LOW-2 from `sessions/agent-code-review/outbox/20260409-code-review-forseti.life-20260409-forseti-release-h.md`

## Verification

```bash
grep -A60 "function job_hunter_install" sites/forseti/web/modules/custom/job_hunter/job_hunter.install | grep "_job_hunter_create_interview_notes_table\|_job_hunter_create_saved_searches_table"
```

Both function names must appear in the output.

## Git rule

After implementation: `git status`, `git diff`, `git add`, `git commit` with message referencing feature ID.
