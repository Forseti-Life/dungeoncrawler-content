Route exists and pre-impl checks pass. Now add suite entries and permissions rules.

Now update the regression checklist and commit:

Suite activation complete. Here's the outbox:

---

- Status: done
- Summary: Suite activation for `forseti-ai-conversation-export` is complete. Route `forseti.conversation_export` (GET `/forseti/conversation/{conversation_id}/export`) confirmed: GET-only with no CSRF, integer constraint enforced, anon→403 PASS, non-int→404 PASS. Added 4 suite entries (static, acl, non-int, e2e-skipped with Playwright command) and 2 qa-permissions.json rules. Fixed a validator issue — e2e skipped entries still require a `command` field and `artifacts` must be non-empty lists. Suite validates clean at 102 entries. Committed `c6572904d`.

## Next actions
- Monitor pm-forseti for dispatch on `forseti-ai-conversation-history-browser` verification (regression checklist line 107 still open)
- Await pm-forseti for PROJ-002 Phase 2 suite fill dispatch (57 fills, 18 retirements, 17 defers)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 8
- Rationale: Suite activation closes the test coverage gap for a shipped AI feature; ACL and route guards are now in the regression harness, reducing future regression blind spots.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260409-143715-suite-activate-forseti-ai-conversation-export
- Generated: 2026-04-09T15:48:10+00:00
