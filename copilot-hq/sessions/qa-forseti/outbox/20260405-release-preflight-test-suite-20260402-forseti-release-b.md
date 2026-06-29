Now I need to add rules for:
1. `GenAiDebugController` routes (admin-only, not content_editor)  
2. Short `/application-submission/*` paths not covered by existing rules

Now I'll update `qa-permissions.json` to add the two missing rules:

Preflight complete. Here's the outbox update:

---

- Status: done
- Summary: Release preflight for `20260402-forseti-release-b` (now tracking as `20260405-forseti-release-c`) is complete. Reviewed all five QA scripts (`site-audit-run.sh`, `site-full-audit.py`, `site-validate-urls.py`, `drupal-custom-routes-audit.py`, `role-permissions-validate.py`) — no stale paths found. `qa-forseti.instructions.md` and `suite.json` were already corrected in a prior cycle. `qa-permissions.json` was updated with two new rules covering in-scope release features: `ai-debug-routes` (GenAiDebugController routes, admin-only, inserted before `admin-area`) and `application-submission-short` (short-form `/application-submission/*` paths not previously covered). Suite manifest validates clean (5 suites). Committed as `c0b01ac1`.

## Next actions
- Proceed with Gate 2 QA verification for `forseti-csrf-fix`, `forseti-ai-debug-gate`, and `forseti-jobhunter-application-submission` as each is handed off from dev.
- Run role-based audit against production to verify `ai-debug-routes` and `application-submission-short` rules resolve correctly: `ALLOW_PROD_QA=1 FORSETI_BASE_URL=https://forseti.life scripts/site-audit-run.sh forseti-life`

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 15
- Rationale: Preflight catches rule gaps before Gate 2 runs blind; `ai-debug-routes` ensures security check for exposed debug endpoints fires at first audit; `application-submission-short` closes a route coverage gap for the WorkdayWizard feature under test.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260405-release-preflight-test-suite-20260402-forseti-release-b
- Generated: 2026-04-05T23:02:34+00:00
