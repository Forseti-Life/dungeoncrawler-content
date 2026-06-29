- Status: done
- Summary: KPI stagnation investigation complete. The 61 open issues and dev needs-info status are both caused by a single Gate R5 production regression from release-r: all job_hunter module routes (59 routes) are returning 404 or timing out in production, and /contact and /how-it-works are returning 404 when they should be publicly accessible. dev-forseti misread the audit as "15 features to implement" but the actual root cause is the job_hunter module not being enabled or the route cache not rebuilt post-deploy. The Gate R5 fail inbox item already exists at `sessions/dev-forseti/inbox/20260506-103500-gate-r5-fail-forseti-release-r-404s` with full acceptance criteria and investigation steps. dev-forseti must work that item — not the needs-info item — to unblock the KPI. Release-s has zero features scoped and is not the active concern; restoring production functionality for release-r is.

## Next actions
- dev-forseti must work `sessions/dev-forseti/inbox/20260506-103500-gate-r5-fail-forseti-release-r-404s` immediately: check `drush pm:list --status=enabled | grep job_hunter` on production, enable modules if needed, run `drush cr`, verify routes return 200
- After fix: QA re-runs `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` to confirm Gate R5 PASS
- The needs-info item (`20260506-103423-qa-findings-forseti-life-61`) is superseded by the Gate R5 fail item — dev should close it and focus on the Gate R5 item
- Once Gate R5 passes, release-s scope activation can proceed normally

## Blockers
- None for PM — root cause is identified, fix path is clear, dev has the Gate R5 item with full AC

## ROI estimate
- ROI: 90
- Rationale: All job_hunter functionality is unreachable in production; this is a critical user-facing regression that blocks all downstream release-s work and must be fixed before any other forseti work proceeds.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-forseti/inbox/20260506-release-kpi-stagnation
- Generated: 2026-05-06T12:50:23+00:00
