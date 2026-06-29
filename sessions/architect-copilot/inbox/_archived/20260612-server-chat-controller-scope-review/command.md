# Command

- created_at: 2026-06-12T15:17:00+00:00
- work_item: server-chat-controller-scope-review
- topic: server-chat-controller-scope-review
- requester: keithaumiller
- owner: architect-copilot

## Command text

Perform a subsystem review, inventory, and refactor pass for the server-side chat layer (controller + chat service) so it remains the client I/O boundary and not the authority for encounter turn/round state.

## Required outcomes

- Inventory room-chat API routes, controller branches, and response payload paths.
- Confirm server chat accepts input/emits output while delegating authority to coordinator/phase handler.
- Refactor any boundary leaks between transport and encounter authority logic.
- Add clear comments documenting server-chat scope and non-scope.
- Record residual risks and follow-up implementation tasks.
