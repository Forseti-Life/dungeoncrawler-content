# Architecture Work Item — client-chat-interface-scope-review

- Agent: architect-copilot
- Created: 2026-06-12
- Topic: client-chat-interface-scope-review
- Priority: P1

## Summary
Review and harden the client-side chat interface so it remains a submission/display surface only, with no local authority over encounter turn/round/state contracts.

## Scope
1. Inventory chat submission/render entry points and all payload fields used by client code.
2. Verify client behavior is server-authoritative (no local turn advancement or round ownership).
3. Refactor any mixed-responsibility logic that fabricates or mutates authority state.
4. Add/update comments and guardrails around client non-authority boundaries.

## Acceptance criteria
- Client only submits intents/messages and renders server-managed transcript/state.
- No client path mutates authoritative turn/round/current-actor contracts.
- Any local-only UX behavior is explicitly labeled non-authoritative.
- Follow-up test/validation targets are listed for refactor safety.
