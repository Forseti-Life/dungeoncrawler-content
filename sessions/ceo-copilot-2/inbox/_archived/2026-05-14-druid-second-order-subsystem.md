# Druid second-order subsystem

## Objective
Implement the missing second-order chooser, persistence, runtime access, and anathema-aware state required for Druid order expansion.

## Blocked feat
- `order-explorer`

## Required subsystem work
- Add a chooser for the granted second druid order.
- Persist second-order state distinctly from the primary order.
- Surface granted order access, focus effects, and order-specific runtime flags from that saved state.
- Represent any anathema-relevant state or rule intersections clearly enough for later systems to consume.

## Verification required after implementation
- Re-run feat verification for `order-explorer`.
- Confirm selection, persistence, runtime granted benefits, and visible gameplay effect.
- Update the feat verification plan/checklist only after end-to-end confirmation.

