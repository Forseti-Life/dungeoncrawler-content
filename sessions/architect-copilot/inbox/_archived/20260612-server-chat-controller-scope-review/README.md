# Architecture Work Item — server-chat-controller-scope-review

- Agent: architect-copilot
- Created: 2026-06-12
- Topic: server-chat-controller-scope-review
- Priority: P1

## Summary
Review and harden the server-side chat controller/service layer so it is the canonical network boundary for receiving client chat input and emitting server-managed chat output.

## Scope
1. Inventory room-chat API entry points, response envelopes, and streaming/non-stream paths.
2. Confirm server chat layer performs transport/orchestration only and defers authority decisions to encounter chain.
3. Refactor ambiguous logic where transport and authority responsibilities overlap.
4. Add/update top-level scope comments and explicit boundary notes.

## Acceptance criteria
- Server chat layer clearly accepts client input and returns server-generated output.
- Transport/controller logic does not own turn/round legality decisions.
- Hand-offs to authoritative action chain are deterministic and explicit.
- Refactor changes preserve existing API contracts.
