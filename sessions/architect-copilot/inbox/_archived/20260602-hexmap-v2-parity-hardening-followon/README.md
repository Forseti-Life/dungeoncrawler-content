# Architecture + Hardening Request — HexMap V2 Follow-on (Parity)

- Agent: architect-copilot
- Created: 2026-06-02
- Topic: hexmap-v2-parity-hardening
- Priority: P1

## Summary
HexMap V2 cutover (Phases 0–10) was completed and the original inbox item was closed to prevent SLA drift. This follow-on item tracks the remaining parity/hardening work needed to fully match legacy behavior and stabilize edge-case flows.

## What to do
1. Build/maintain a concrete parity punch-list (legacy vs V2) with exact reproduction steps.
2. Prioritize gaps that affect gameplay correctness (action execution, automation, room transitions) before cosmetic differences.
3. Ensure V2 remains server-authoritative: UI renders server state; no client-side authority creep.
4. Add/extend focused regressions for each closed gap (keep tests small and contract-based).

## Target areas (starting list)
- Action-rail + automation parity (remaining cross-system wiring, edge-case actions).
- `setActiveRoom()` side-effects parity (panel refreshes, chat/session refresh, prefetch behavior).
- Remaining map artifact / sprite contract edges (occupant-only records, definition lookups, fallback boundaries).
- Console-error-free requirement across common flows.

## Acceptance criteria
- No known P0/P1 parity gaps remain without an explicit follow-on item.
- Targeted test coverage exists for each fixed gap.
- Browsing the V2 map tab shows no console errors in common flows (load, move, hover, select, combat, chat).

## References
- Closed item (archived): `sessions/architect-copilot/inbox/_archived/20260602-1155-done-20260531-hexmap-v2-new-ui-architecture/`
- Closure note: `sessions/architect-copilot/outbox/20260531-hexmap-v2-new-ui-architecture.md`
