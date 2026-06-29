Done. Here's the outbox:

---

- Status: done
- Summary: Added the two missing helper calls `_job_hunter_create_interview_notes_table()` and `_job_hunter_create_saved_searches_table()` to `job_hunter_install()` in `job_hunter.install` (after `_job_hunter_create_employer_credentials_table()` at line 67). Both helper functions existed but were never invoked during install, causing fresh install failures. Fix is 2 additive lines. Committed `74456b477`.

## Next actions
- QA-forseti: verify AC — `grep -A70 "function job_hunter_install" sites/forseti/web/modules/custom/job_hunter/job_hunter.install | grep "_job_hunter_create_interview_notes_table\|_job_hunter_create_saved_searches_table"` — both must appear

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 8
- Rationale: P1 bugfix; fresh installs fail without this; 2-line additive change with zero regression risk.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260409-235500-impl-forseti-jobhunter-hook-install-fix
- Generated: 2026-04-10T00:20:34+00:00
