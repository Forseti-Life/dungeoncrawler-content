# Command

- created_at: 2026-06-11T13:58:00+00:00
- work_item: actor-turn-action-context-canonicalization
- topic: actor-turn-action-context-canonicalization
- requester: Board
- owner: architect-copilot

## Command text

Canonicalize actor turn action availability context so every actor decision surface receives the same deterministic server-derived contract. Eliminate static action-list drift in turn-context builders and enforce one canonical action-availability payload shape.

## Required outcomes

- Actor turn context includes deterministic `actions_available_to_me_this_turn` data.
- Canonical actor-scoped available actions + structured contract metadata are present where turn planning executes.
- Static/hardcoded allowed-action lists replaced where canonical derivation exists.
- Focused regressions lock payload shape and actor-turn scoping.
