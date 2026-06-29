# Test Plan: dc-cr-dwarf-heritage-rock

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-DRK-01-05)
- Suites: playwright (character creation, maneuvers, forced movement)
- Security: Security AC exemption: passive ancestry heritage behavior only; no new routes or input surfaces beyond existing heritage assignment and combat-resolution handlers.

---

## TC-DRK-01 — Heritage availability and ancestry gating
- Description: Rock Dwarf is selectable only for dwarf characters within the heritage system.
- Suite: playwright/character-creation
- Expected: Rock Dwarf is selectable only for dwarf characters within the heritage system.
- AC: Happy Path-1

## TC-DRK-02 — Primary passive effect application
- Description: The heritage grants a +2 circumstance bonus to the relevant Fortitude or Reflex DC / save checks against Shove, Trip, and knock-prone effects.
- Suite: playwright/encounter
- Expected: The heritage grants a +2 circumstance bonus to the relevant Fortitude or Reflex DC / save checks against Shove, Trip, and knock-prone effects.; Forced movement affecting the character is reduced by half when the pushed or pulled distance is 10 feet or more.
- AC: Happy Path-2, Happy Path-3

## TC-DRK-03 — Scaling, automation, and visible state updates
- Description: Forced movement affecting the character is reduced by half when the pushed or pulled distance is 10 feet or more.
- Suite: playwright/encounter
- Expected: Forced movement affecting the character is reduced by half when the pushed or pulled distance is 10 feet or more.; The passive applies automatically during maneuver resolution without any manual toggle.
- AC: Happy Path-3, Happy Path-4

## TC-DRK-04 — Edge-case rules interaction coverage
- Description: Voluntary movement is never halved by the heritage.
- Suite: playwright/encounter
- Expected: Voluntary movement is never halved by the heritage.; Small forced movements below the threshold stay at their normal distance unless the movement engine already rounds them under existing rules.; The bonus applies only to the targeted anti-displacement effects and not to unrelated Reflex or Fortitude saves.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-DRK-05 — Validation errors and safe fallback behavior
- Description: Invalid ancestry/heritage combinations are rejected.
- Suite: playwright/encounter
- Expected: Invalid ancestry/heritage combinations are rejected.; Combat resolution falls back to the normal forced-movement rules if the action is not tagged as Shove, Trip, knock-prone, or forced movement.
- AC: Failure Modes-1, Failure Modes-2
