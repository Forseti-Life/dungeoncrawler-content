# Test Plan: dc-cr-dwarf-heritage-forge

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-DFR-01-05)
- Suites: playwright (character creation, resistances, environmental hazards)
- Security: Security AC exemption: passive ancestry heritage behavior only; no new routes or input surfaces beyond existing heritage assignment, resistance, and hazard-resolution handlers.

---

## TC-DFR-01 — Heritage availability and ancestry gating
- Description: Forge Dwarf is available only under the dwarf ancestry heritage list.
- Suite: playwright/character-creation
- Expected: Forge Dwarf is available only under the dwarf ancestry heritage list.
- AC: Happy Path-1

## TC-DFR-02 — Primary passive effect application
- Description: Selecting Forge Dwarf grants fire resistance equal to half the character level, with a minimum of 1.
- Suite: playwright/encounter
- Expected: Selecting Forge Dwarf grants fire resistance equal to half the character level, with a minimum of 1.; Environmental heat effects are treated as one step less severe for a Forge Dwarf character.
- AC: Happy Path-2, Happy Path-3

## TC-DFR-03 — Scaling, automation, and visible state updates
- Description: Environmental heat effects are treated as one step less severe for a Forge Dwarf character.
- Suite: playwright/encounter
- Expected: Environmental heat effects are treated as one step less severe for a Forge Dwarf character.; The fire-resistance value recalculates automatically when the character level changes.
- AC: Happy Path-3, Happy Path-4

## TC-DFR-04 — Edge-case rules interaction coverage
- Description: Level 1 characters still receive the minimum fire resistance of 1.
- Suite: playwright/encounter
- Expected: Level 1 characters still receive the minimum fire resistance of 1.; Environmental heat downgrades follow the documented one-step ladder and do not skip multiple severity bands.; Non-fire damage and non-heat environmental effects are unaffected by the heritage.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-DFR-05 — Validation errors and safe fallback behavior
- Description: Selecting Forge Dwarf on a non-dwarf character returns a validation error.
- Suite: playwright/encounter
- Expected: Selecting Forge Dwarf on a non-dwarf character returns a validation error.; If an environmental hazard lacks a heat severity tag, the hazard resolves normally instead of crashing the encounter flow.
- AC: Failure Modes-1, Failure Modes-2
