# Test Plan: dc-cr-dwarven-weapon-familiarity

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-DWF-01-05)
- Suites: playwright (character creation, inventory, weapon proficiency)
- Security: Security AC exemption: ancestry-feat and proficiency-calculation scope only; no new routes or input surfaces beyond existing feat assignment and character build handlers.

---

## TC-DWF-01 — Feat availability and prerequisite gating
- Description: The feat exists as a level-1 dwarf ancestry feat and is only available through a valid ancestry-feat slot.
- Suite: playwright/character-creation
- Expected: The feat exists as a level-1 dwarf ancestry feat and is only available through a valid ancestry-feat slot.
- AC: Happy Path-1

## TC-DWF-02 — Primary granted benefit application
- Description: Selecting the feat grants trained proficiency with battle axe, pick, and warhammer.
- Suite: playwright/inventory
- Expected: Selecting the feat grants trained proficiency with battle axe, pick, and warhammer.; Uncommon dwarf weapons become available to the character once the feat is selected.
- AC: Happy Path-2, Happy Path-3

## TC-DWF-03 — Recalculation, retraining, and later progression behavior
- Description: Uncommon dwarf weapons become available to the character once the feat is selected.
- Suite: playwright/inventory
- Expected: Uncommon dwarf weapons become available to the character once the feat is selected.; Martial dwarf weapons count as simple and advanced dwarf weapons count as martial for this character's proficiency calculations.
- AC: Happy Path-3, Happy Path-4

## TC-DWF-04 — Edge-case rules interaction coverage
- Description: Non-dwarf characters and characters without an open ancestry-feat slot cannot select the feat.
- Suite: playwright/inventory
- Expected: Non-dwarf characters and characters without an open ancestry-feat slot cannot select the feat.; If the character later gains broader proficiency from class progression, the familiarity remapping still resolves correctly.; Removing or retraining the feat restores the baseline weapon-access rules.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-DWF-05 — Validation errors and malformed-data handling
- Description: Malformed or non-dwarf weapon tags are rejected during content validation.
- Suite: playwright/inventory
- Expected: Malformed or non-dwarf weapon tags are rejected during content validation.; The proficiency remapping never exposes unrelated uncommon weapons outside the dwarf weapon group.
- AC: Failure Modes-1, Failure Modes-2
