# Mastermind racket subsystem

## Objective
Implement the missing Mastermind racket plumbing so rogue creation, key ability handling, extra skill choice, runtime effects, and feat verification work end-to-end.

## Blocked feat
- `mastermind-racket`

## Required subsystem work
- Extend rogue racket selection beyond Ruffian/Scoundrel/Thief so Mastermind is a first-class option.
- Remove the current hard-coded Dexterity-only rogue key ability assumption where it blocks Mastermind.
- Add the required extra knowledge-skill chooser and persistence path.
- Wire Society training and the Recall Knowledge -> flat-footed runtime behavior into derived state.

## Verification required after implementation
- Re-run feat verification for `mastermind-racket`.
- Confirm creation flow, persistence, runtime state, and visible gameplay effect.
- Update the feat verification plan/checklist only after end-to-end confirmation.

