# Test Plan: dc-cr-mountains-stoutness

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-MST-01-05)
- Suites: playwright (feat progression, HP state, dying/recovery)
- Security: Security AC exemption: ancestry-feat and character-state math scope only; no new routes or input surfaces beyond existing feat assignment and dying-state handlers.

---

## TC-MST-01 — Feat availability and prerequisite gating
- Description: Mountain's Stoutness exists as a level-9 dwarf ancestry feat.
- Suite: playwright/feat-progression
- Expected: Mountain's Stoutness exists as a level-9 dwarf ancestry feat.
- AC: Happy Path-1

## TC-MST-02 — Primary granted benefit application
- Description: Selecting the feat adds the character's current level to maximum Hit Points.
- Suite: playwright/encounter
- Expected: Selecting the feat adds the character's current level to maximum Hit Points.; While dying, the recovery-check DC becomes `9 + dying_value` instead of the baseline `10 + dying_value`.
- AC: Happy Path-2, Happy Path-3

## TC-MST-03 — Recalculation, retraining, and later progression behavior
- Description: While dying, the recovery-check DC becomes `9 + dying_value` instead of the baseline `10 + dying_value`.
- Suite: playwright/encounter
- Expected: While dying, the recovery-check DC becomes `9 + dying_value` instead of the baseline `10 + dying_value`.; If the character also has Toughness, the HP bonuses stack and the recovery-check DC becomes `6 + dying_value`.
- AC: Happy Path-3, Happy Path-4

## TC-MST-04 — Edge-case rules interaction coverage
- Description: Level changes recalculate the added max HP automatically.
- Suite: playwright/feat-progression
- Expected: Level changes recalculate the added max HP automatically.; Characters without Toughness still receive the Mountain's Stoutness recovery-check adjustment without any extra flags.; Retraining or removing the feat restores the baseline HP and recovery-check formulas.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-MST-05 — Validation errors and malformed-data handling
- Description: Selecting the feat below level 9 or without a valid dwarf ancestry slot is rejected.
- Suite: playwright/feat-progression
- Expected: Selecting the feat below level 9 or without a valid dwarf ancestry slot is rejected.; The feat never changes unrelated death-and-dying rules beyond the documented recovery-check DC adjustment.
- AC: Failure Modes-1, Failure Modes-2
