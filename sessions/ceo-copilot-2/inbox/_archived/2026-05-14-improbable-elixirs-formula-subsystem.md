# Improbable Elixirs formula subsystem

## Objective
Implement the missing formula-selection and runtime conversion flow needed for Improbable Elixirs.

## Blocked feat
- `improbable-elixirs`

## Required subsystem work
- Add a chooser for the eligible potion formulas up to the feat's allowed level band.
- Persist the selected formulas in character state.
- Add runtime conversion so selected potion formulas are treated as alchemical elixir formulas where appropriate.
- Ensure formula display and usage state can surface this special source cleanly.

## Verification required after implementation
- Re-run feat verification for `improbable-elixirs`.
- Confirm selection, persistence, runtime conversion, and visible gameplay effect.
- Update the feat verification plan/checklist only after end-to-end confirmation.

