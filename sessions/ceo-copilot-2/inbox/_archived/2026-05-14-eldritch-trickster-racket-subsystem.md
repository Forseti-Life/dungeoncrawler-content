# Eldritch Trickster racket subsystem

## Objective
Implement the missing Eldritch Trickster racket plumbing in Dungeoncrawler so rogue creation, persistence, granted-dedication behavior, and downstream feat verification are all wired end-to-end.

## Blocked feat
- `eldritch-trickster-racket`

## Required subsystem work
- Extend rogue racket selection beyond Ruffian/Scoundrel/Thief so Eldritch Trickster is a first-class option.
- Add the granted multiclass spellcasting dedication chooser and persistence flow required at level 1.
- Ensure the granted dedication survives into canonical character state and runtime derivation.
- Wire Intelligence key ability handling and any related rogue-racket state needed by this racket.

## Verification required after implementation
- Re-run feat verification for `eldritch-trickster-racket`.
- Confirm creation flow, persistence, runtime state, and visible gameplay effect.
- Update the feat verification plan/checklist only after end-to-end confirmation.

