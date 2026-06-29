Neither helper function exists. I need to add them. I'll append them before the closing of the file (after the last `}`):

---

- Status: done
- Summary: Added two missing helper functions `_job_hunter_create_interview_notes_table()` and `_job_hunter_create_saved_searches_table()` to `job_hunter.install`. Schemas were copied exactly from `job_hunter_update_9042()` and `job_hunter_update_9043()` (lines 1002 and 1060), both with `tableExists` guards for idempotency. AC-1: both function definitions confirmed at lines 2789 and 2847. AC-2: PHP lint clean. AC-3: definitions present (not just call sites). Committed `e97213eb0`. Rollback: `git revert e97213eb0`.

## Next actions
- QA-forseti: re-run `forseti-jobhunter-hook-install-fix` test suite — verify AC-1: `grep -rn "function _job_hunter_create_interview_notes_table\|function _job_hunter_create_saved_searches_table" sites/forseti/` returns 2 results

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 8
- Rationale: P1 fresh-install failure; blocking Gate 2; 2-function additive change with no regression risk.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260410-hook-install-add-missing-helpers
- Generated: 2026-04-10T04:17:25+00:00
