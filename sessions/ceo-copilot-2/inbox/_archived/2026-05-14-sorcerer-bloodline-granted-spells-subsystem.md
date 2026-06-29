# Sorcerer bloodline granted-spells subsystem

## Objective
Create a canonical bloodline granted-spell layer that can feed repertoire state and runtime behavior by spell rank.

## Blocked feats
- `bloodline-breadth`
- `greater-bloodline`

## Required subsystem work
- Define a canonical per-bloodline granted-spell list keyed by spell rank.
- Surface those granted spells into persisted repertoire/runtime state.
- Make highest-rank bloodline spell resolution available to feat logic.
- Ensure the subsystem supports both current bloodline and cross-feat consumers without ad hoc mappings.

## Verification required after implementation
- Re-run feat verification for `bloodline-breadth` and `greater-bloodline`.
- Confirm rank-aware granted-spell resolution, persistence, runtime repertoire state, and visible gameplay effect.
- Update the feat verification plan/checklist only after end-to-end confirmation.

