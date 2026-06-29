Done. Here's the outbox:

---

- Status: done
- Summary: Added `### CSRF token delivery rule — templates and JavaScript (required)` subsection to `dev-forseti.instructions.md` under the existing CSRF routing constraint section (now at line 203). Documents two rules with grep verify commands and code examples: Rule 1 — never add hidden `form_token`/`token` inputs to Twig templates for `_csrf_token: 'TRUE'` routes (the form URL already carries the token via `RouteProcessorCsrf`); Rule 2 — JS fetch/XHR must append `?token=` to the URL, never to the POST body, because `CsrfAccessCheck` reads only `$request->query->get('token')`. Commit: `f0d3e0311` (HQ).

## Next actions
- No follow-on code changes required; rule is now in seat instructions for all future template/fetch work.

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 20
- Rationale: Two separate findings from releases b and c had the same root gap — no documented rule for CSRF token placement in templates and JS. Codifying this prevents both failure modes from recurring in future releases.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260410-instructions-csrf-delivery-rule-forseti
- Generated: 2026-04-10T16:59:05+00:00
