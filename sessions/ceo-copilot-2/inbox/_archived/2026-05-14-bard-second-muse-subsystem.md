# Bard second-muse subsystem

## Objective
Implement the missing second-muse chooser, persistence, granted-benefit plumbing, and prerequisite unlock behavior for late-game bard muse expansion.

## Blocked feat
- `true-facets`

## Required subsystem work
- Add a chooser for selecting a second muse.
- Persist the second muse distinctly from the primary muse.
- Grant and track the second muse's feat and bonus spell.
- Unlock prerequisite access for the second muse's feat graph in a way later systems can consume.

## Verification required after implementation
- Re-run feat verification for `true-facets`.
- Confirm selection, persistence, runtime granted benefits, and prerequisite unlock behavior.
- Update the feat verification plan/checklist only after end-to-end confirmation.

