# Command

- created_at: 2026-06-08T19:05:50+00:00
- work_item: actor-motivation-next-steps-generation-followon
- topic: actor-motivation-next-steps-generation
- requester: Board
- owner: architect-copilot

## Command text

Continue actor motivation and next-step generation architecture after the initial psychology-aware fallback/action-targeting slice. Define and implement a canonical motivation-to-intent contract for non-player turns, carry it through deterministic multi-action planning, and expose machine-readable decision-basis metadata for debugging and UI traceability.

## Required outcomes

- A documented and enforced motivation/psychology decision contract for NPC next-step generation.
- Deterministic multi-action turn planning that preserves psychology consistency.
- Focused regressions locking behavior for de-escalation, self-preservation, and cunning target selection.
- Decision-basis metadata available in encounter outputs for observability.
