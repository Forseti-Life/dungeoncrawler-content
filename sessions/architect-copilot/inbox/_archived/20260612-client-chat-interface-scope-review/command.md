# Command

- created_at: 2026-06-12T15:17:00+00:00
- work_item: client-chat-interface-scope-review
- topic: client-chat-interface-scope-review
- requester: keithaumiller
- owner: architect-copilot

## Command text

Perform a subsystem review, inventory, and refactor pass for the client-side chat interface so its role is strictly: submit player input to server APIs and render server-managed output.

## Required outcomes

- Produce an explicit inventory of client submission and rendering entry points.
- Confirm client code does not own or mutate turn/round/current-actor authority.
- Refactor mixed-responsibility code paths that blur server/client authority boundaries.
- Add clear top-level comments/guardrails documenting client scope and non-scope.
- Record any remaining gaps as concrete follow-on actions.
