# Escalation: qa-forseti is blocked

- Website: forseti.life
- Module: 
- Role: tester
- Agent: qa-forseti
- Item: 20260410-unit-test-20260409-235500-impl-forseti-jobhunter-hook-install-fix
- Status: blocked
- Supervisor: pm-forseti
- Outbox file: sessions/qa-forseti/outbox/20260410-unit-test-20260409-235500-impl-forseti-jobhunter-hook-install-fix.md
- Created: 2026-04-10T03:30:45+00:00

## Decision needed
- No scope decision needed — clear defect, fix required before release ships


## Recommendation
- Dev should add the two helper function definitions (using `jobhunter_` prefix to match update hook schema) as the lowest-risk fix; the install path then matches the update path exactly


## ROI estimate
- ROI: 7
- Rationale: P1 correctness bug in install hook; no current production impact but blocks any future fresh deployment; quick fix (add 2 function definitions mirroring update_9042/9043).

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260410-unit-test-20260409-235500-impl-forseti-jobhunter-hook-install-fix
- Generated: 2026-04-10T03:30:45+00:00

## Needs from Supervisor (up-chain)
- Dev-forseti must close this defect before APPROVE can be issued for this item


## Blockers
- `_job_hunter_create_interview_notes_table()` and `_job_hunter_create_saved_searches_table()` called in `job_hunter_install()` but not defined anywhere; fresh install would fatal-error


## Full outbox (context)
- Status: blocked
- Summary: Targeted QA verification for `hook-install-fix` is BLOCK. Dev commit `74456b477` adds calls to `_job_hunter_create_interview_notes_table()` and `_job_hunter_create_saved_searches_table()` inside `job_hunter_install()` (lines 68–69), but neither helper function is defined anywhere in the module — confirmed with `grep -rn "function _job_hunter_create_interview_notes_table|function _job_hunter_create_saved_searches_table"` returning 0 results. A fresh install would produce a PHP fatal error ("Call to undefined function"). The tables DO exist on production (`jobhunter_interview_notes` and `jobhunter_saved_searches` — note `jobhunter_` prefix, not `job_hunter_`) created by `job_hunter_update_9042`/`9043` via inline SQL. No immediate production regression since hook_install only runs on fresh installs and the live site is already past install. PHP lint passes (the undefined function call isn't a parse error). Site audit `20260410-015633`: 0F/0V. Regression checklist updated, commit `bf8436ca3`.

## Next actions
- Dev-forseti: fix `job_hunter_install()` — two options: (1) add the two missing helper function definitions using the same `jobhunter_*` schema/prefix as update_9042/9043, OR (2) replace the undefined-function calls with direct inline table creation matching the update hook schemas
- QA: re-verify after dev fix is committed

## Blockers
- `_job_hunter_create_interview_notes_table()` and `_job_hunter_create_saved_searches_table()` called in `job_hunter_install()` but not defined anywhere; fresh install would fatal-error

## Needs from Supervisor
- Dev-forseti must close this defect before APPROVE can be issued for this item

## Decision needed
- No scope decision needed — clear defect, fix required before release ships

## Recommendation
- Dev should add the two helper function definitions (using `jobhunter_` prefix to match update hook schema) as the lowest-risk fix; the install path then matches the update path exactly

## ROI estimate
- ROI: 7
- Rationale: P1 correctness bug in install hook; no current production impact but blocks any future fresh deployment; quick fix (add 2 function definitions mirroring update_9042/9043).

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260410-unit-test-20260409-235500-impl-forseti-jobhunter-hook-install-fix
- Generated: 2026-04-10T03:30:45+00:00
