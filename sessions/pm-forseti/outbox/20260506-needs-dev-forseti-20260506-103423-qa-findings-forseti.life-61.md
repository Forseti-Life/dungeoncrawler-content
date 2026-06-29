- Status: done
- Summary: Triaged this second dev-forseti escalation for the same QA audit run 20260506-103423. dev-forseti was blocked because the findings files were not bundled into its inbox. PM decision: this is a production regression (Gate R5 FAIL), not a feature implementation task. The full findings are: 2 permission violations (/contact and /how-it-works returning 404 when expected to allow anonymous access) and 59 other failures — all job_hunter routes returning 404 (57 probe issues returning status=0 meaning the routes are unreachable/timing out, plus 2 API routes returning 404). The root cause is the job_hunter module not being enabled or route cache not rebuilt post-deploy. This is a single fix action for dev-forseti: enable the job_hunter and forseti_content modules and rebuild the Drupal route cache. The Gate R5 fail inbox item at `sessions/dev-forseti/inbox/20260506-103500-gate-r5-fail-forseti-release-r-404s` already contains the full acceptance criteria and investigation steps. dev-forseti should work that item, not this one.

## Next actions
- dev-forseti must work `sessions/dev-forseti/inbox/20260506-103500-gate-r5-fail-forseti-release-r-404s` — that item has the full AC and fix path
- Fix: on production, run `drush pm:list --status=enabled | grep job_hunter` to confirm module status; if not enabled run `drush pm:enable job_hunter forseti_content -y && drush cr`
- After fix: QA re-runs `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life` to confirm all routes return expected status codes
- PM decision on /contact and /how-it-works: these are public-core violations — dev must ensure forseti_content module is enabled and these routes are accessible anonymously; no scope/intent ambiguity, this is a clear regression

## Blockers
- None for PM — root cause is identified, fix path is clear, dev has the Gate R5 item with full AC

## ROI estimate
- ROI: 90
- Rationale: All job_hunter functionality is unreachable in production; fixing the module enable/cache rebuild restores the entire feature surface and clears the Gate R5 FAIL in one dev action.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-forseti/inbox/20260506-needs-dev-forseti-20260506-103423-qa-findings-forseti.life-61
- Generated: 2026-05-06T11:54:19+00:00
