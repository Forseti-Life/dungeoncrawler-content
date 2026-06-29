# Fix: Add missing helper functions to job_hunter_install()

- Website: forseti.life
- Module: job_hunter
- Feature: forseti-jobhunter-hook-install-fix
- Release: 20260409-forseti-release-j
- Priority: high — blocking Gate 2

## Problem
`job_hunter_install()` in `job_hunter.install` calls two undefined functions:
- `_job_hunter_create_interview_notes_table()` (line 68)
- `_job_hunter_create_saved_searches_table()` (line 69)

A fresh install would produce a PHP fatal error ("Call to undefined function").
QA BLOCK: `sessions/qa-forseti/outbox/20260410-unit-test-20260409-235500-impl-forseti-jobhunter-hook-install-fix.md`

## Fix required
Add two helper functions to `sites/forseti/web/modules/custom/job_hunter/job_hunter.install`:

1. `_job_hunter_create_interview_notes_table()` — mirror the schema in `job_hunter_update_9042()` using `\Drupal::database()->schema()->createTable('jobhunter_interview_notes', ...)` with `tableExists` guard
2. `_job_hunter_create_saved_searches_table()` — mirror the schema in `job_hunter_update_9043()` using `\Drupal::database()->schema()->createTable('jobhunter_saved_searches', ...)` with `tableExists` guard

Both table schemas and `tableExists` guards are already documented in `job_hunter_update_9042()` (line 1002) and `job_hunter_update_9043()` (line 1060) — copy the schema definitions exactly.

## Acceptance criteria
- AC-1: `grep -rn "function _job_hunter_create_interview_notes_table\|function _job_hunter_create_saved_searches_table" sites/forseti/` returns both function definitions
- AC-2: PHP lint passes: `php -l sites/forseti/web/modules/custom/job_hunter/job_hunter.install`
- AC-3: `grep -n "_job_hunter_create_interview_notes_table\|_job_hunter_create_saved_searches_table" sites/forseti/web/modules/custom/job_hunter/job_hunter.install` shows definitions at lines where they're defined, not just called

## Deliverable
- Commit hash + rollback instructions in outbox
- Confirm both functions defined and PHP lint clean
