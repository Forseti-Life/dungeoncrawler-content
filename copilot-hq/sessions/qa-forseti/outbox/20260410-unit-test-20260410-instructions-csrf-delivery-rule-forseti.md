Outbox already written and committed (`9ac85db7c`). Here is the final deliverable:

---

- Status: done
- Summary: QA APPROVE for `instructions-csrf-delivery-rule-forseti`. Dev commit `f0d3e0311` is a content-only change that adds `### CSRF token delivery rule — templates and JavaScript (required)` to `dev-forseti.instructions.md` (line 203). The section documents two rules derived from release-b/c findings: Rule 1 — never add hidden `form_token`/`token` inputs to Twig templates for `_csrf_token: 'TRUE'` routes (the URL carries the token via `RouteProcessorCsrf`); Rule 2 — JS fetch must append `?token=` to the URL, never the POST body, because `CsrfAccessCheck` reads only `$request->query->get('token')`. Both rules include reproducible grep verify commands. No product code was changed. Site audit `20260410-170231` (most recent, run during prior task): 0F/0V/0 drift — PASS.

## Next actions
- No new Dev items required; instructions update prevents recurrence of both release-b and release-c CSRF placement failures

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 20
- Rationale: Codifies the exact rule that caused two separate QA findings (releases b and c). Prevents the same dead-field and wrong-token-placement mistakes from recurring in future template work.

---

## Evidence

| Check | Result |
|---|---|
| AC-1: `CSRF token delivery rule` section present in `dev-forseti.instructions.md` (line 203) | PASS |
| AC-2: Rule 1 (no hidden form_token) + Rule 2 (JS fetch ?token= URL) with grep verify commands | PASS |
| AC-3: no product code changed (instructions-only commit) | PASS |
| Site audit `20260410-170231` | 0F / 0V / 0 drift — PASS |

## Commits
- Dev instructions update: `f0d3e0311`
- Regression checklist APPROVE: `235b4ec18`
- Outbox commit: `9ac85db7c`

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260410-unit-test-20260410-instructions-csrf-delivery-rule-forseti
- Generated: 2026-04-10T17:31:46+00:00
