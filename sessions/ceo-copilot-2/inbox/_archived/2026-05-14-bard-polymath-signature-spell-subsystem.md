# Bard polymath signature-spell subsystem

## Objective
Implement the missing bard repertoire/signature-spell extension and daily swap flow required for the polymath feat chain.

## Blocked feats
- `esoteric-polymath`
- `eclectic-polymath`

## Required subsystem work
- Add a chooser and persistence path for the granted cross-tradition common spell.
- Represent the granted spell as a signature-spell-capable repertoire addition.
- Add the daily swap workflow for replacing that granted spell.
- Ensure canonical state and runtime derivation can distinguish this special granted spell from normal repertoire entries.

## Verification required after implementation
- Re-run feat verification for `esoteric-polymath` and `eclectic-polymath`.
- Confirm selection, persistence, runtime repertoire state, and daily swap behavior.
- Update the feat verification plan/checklist only after end-to-end confirmation.

