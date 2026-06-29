# Command

- created_at: 2026-06-02T11:56:00+00:00
- work_item: hexmap-v2-parity-hardening-followon
- topic: hexmap-v2-parity-hardening
- requester: Board
- owner: architect-copilot

## Command text

Continue HexMap V2 parity and hardening work post-cutover. Maintain server-authoritative contracts; UI must render server state and avoid client-side authority creep. For each parity gap: capture reproduction steps, define the contract boundary, implement the fix, and land a focused regression.

## Required outcomes

- A maintained parity punch-list (P0/P1 first) with reproduction steps.
- Targeted, contract-based regressions for each fixed gap.
- No console errors in common gameplay flows (load, move, hover/select, combat loop, chat/session view, room transitions).
