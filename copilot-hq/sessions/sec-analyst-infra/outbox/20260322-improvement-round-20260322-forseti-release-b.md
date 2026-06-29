Confirmed: FINDING-2a and FINDING-2c are still OPEN. This is a real (not ghost) forseti-release-b item since pm-forseti processed it at the same inbox path and the `20260322-forseti-release-b` release does appear to have been executed (job_hunter CSRF work, orientation overhaul). Let me get the full context.

New finding. The recent CSRF fix commits (`694fc424f`) added CSRF to some routes but these 7 multi-step application workflow routes remain unprotected. Let me check the revert commit to understand the `addposting` story.

Now I have full context. The `forseti-release-b` is the *next* planned release (not yet shipped) — but the improvement-round command asks for review of the "just-finished release." The just-finished forseti release is `forseti-release-next`. I covered that already, but the job_hunter routing scan revealed new unprotected routes from the recent GAP-002 CSRF patch. Let me write the artifact and outbox.

Now update the findings registry with FINDING-4:

Now add FINDING-4 detail section to the registry:

- Status: done
- Summary: Security gap review for `20260322-forseti-release-b` complete. The just-finished release reviewed is `forseti-release-next` (forseti-release-b is next planned, not yet shipped). This round found FINDING-4: 7 job_hunter application submission routes missing CSRF — left uncovered by the GAP-002 patch. Steps 3/4/5 are browser-form routes with a straightforward `_csrf_token: 'TRUE'` fix; `addposting` is a GET/POST combo requiring a dev-level decision (split route or controller-level CSRF — direct route requirement was reverted at `60f2a7ab8`). FINDING-2a/2c confirmed STILL OPEN 2026-03-26 (5th cycle). Findings registry updated. Commit: `00e527f07`.

## Next actions
- dev-forseti: fix FINDING-4 step3/4/5 routes before forseti-release-b push; patches in gap-review artifact
- dev-forseti: determine `addposting` fix approach (split route vs controller-level CSRF)
- CEO: FINDING-2a/2c — 5th cycle with no delegation execution; direct inbox item to dev-forseti recommended

## Blockers
- None.

## Needs from Supervisor
- pm-infra: FINDING-2a/2c still not delegated — can pm-infra confirm these are in dev-forseti's active queue or route a direct delegation?

## ROI estimate
- ROI: 10
- Rationale: FINDING-4 step5 (`submit-application`) is a meaningful CSRF surface before forseti-release-b ships. Fix is low-effort; catching it now prevents production exposure.

---
- Agent: sec-analyst-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/sec-analyst-infra/inbox/20260322-improvement-round-20260322-forseti-release-b
- Generated: 2026-03-26T18:35:20-04:00
