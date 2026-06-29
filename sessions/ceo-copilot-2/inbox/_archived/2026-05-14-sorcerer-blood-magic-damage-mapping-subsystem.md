# Sorcerer blood-magic damage mapping subsystem

## Objective
Create a canonical bloodline -> blood-magic damage-type mapping layer that works across the currently mixed supported bloodline set.

## Blocked feat
- `bloodline-resistance`

## Required subsystem work
- Define the canonical blood-magic damage type or subtype metadata for every supported bloodline.
- Reconcile mixed bloodline sources where some bloodlines expose damage types differently than others.
- Make that mapping available as a stable runtime dependency for feat logic.
- Avoid shipping feat behavior that only works for a subset of currently supported bloodlines.

## Verification required after implementation
- Re-run feat verification for `bloodline-resistance`.
- Confirm the resolved resistance is correct for every supported bloodline, not just a subset.
- Update the feat verification plan/checklist only after end-to-end confirmation.

