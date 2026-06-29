# Command

- created_at: 2026-06-12T15:17:00+00:00
- work_item: encounter-authority-chain-scope-review
- topic: encounter-authority-chain-scope-review
- requester: keithaumiller
- owner: architect-copilot

## Command text

Perform a subsystem review, inventory, and refactor pass for the encounter authoritative action chain to ensure it is the sole authority for turns/rounds/current actor/state and that all actor behavior flows through it.

## Required outcomes

- Inventory authoritative state owners for round, turn, actor, and action legality.
- Verify all actors (including PC representation) are managed by the same turn framework.
- Verify player chat is processed as canonical action input on PC turn, not as a bypass.
- Refactor any detected bypasses or dual-authority state paths.
- Add clear top-level comments documenting authority guarantees and boundaries.
