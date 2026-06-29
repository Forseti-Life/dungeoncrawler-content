- Status: done
- Summary: Triaged dev-forseti escalation for QA audit run 20260506-103423. The audit found 59 route errors (404s) and 2 permission violations — all 59 route errors are job_hunter module routes returning 404, plus 2 forseti_content routes (/contact, /how-it-works). This is NOT a 15-feature scope problem requiring PM sequencing: the 404s are a single root cause (the job_hunter module is not enabled or its routes are not registered in production), not 15 independent feature gaps. Release-s currently has zero features scoped (health check confirms "No features scoped to 20260412-forseti-release-s"), so there is no scope cap risk. PM decision: dev-forseti should investigate why the job_hunter module routes are returning 404 in production (module disabled, missing config sync, or controller registration issue) and fix the root cause — this is a single code defect in the job_hunter module, not a 15-feature implementation sprint. The 2 permission violations (forseti_content routes) are a separate lower-priority finding.

## Next actions
- Seed dev-forseti inbox with a scoped fix item: investigate why all job_hunter routes return 404 in production (check module enable status via `drush pm:list | grep job_hunter`, check config sync state, check route registration); fix the root cause and verify routes return expected status codes
- Separately: investigate /contact and /how-it-works 404s (forseti_content module routes) — lower priority, can be a second dev item
- Once dev fixes are in, QA re-runs the audit to confirm clean signal before release-s scope activation
- Reconcile forseti-langgraph-console-admin (orphaned in_progress on release-r) — reset to ready or carry to release-s as a separate decision

## Blockers
- None for PM — this is a dev code defect, not a scope/intent decision

## ROI estimate
- ROI: 40
- Rationale: 59 route 404s in production means the job_hunter feature is completely non-functional for users; fixing the root cause (likely a single module enable or config sync issue) restores the entire feature surface in one dev action.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-forseti/inbox/20260506-needs-dev-forseti-20260506-103423-qa-findings-forseti-life-61
- Generated: 2026-05-06T10:50:49+00:00
