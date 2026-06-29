# Test Plan: dc-cr-elf-heritage-arctic

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-EAR-01-05)
- Suites: playwright (character creation, resistances, environmental hazards)
- Security: Security AC exemption: passive ancestry heritage behavior only; no new routes or input surfaces beyond existing heritage assignment, resistance, and hazard-resolution handlers.

---

## TC-EAR-01 — Heritage availability and ancestry gating
- Description: Arctic Elf is present as an elf-only heritage option.
- Suite: playwright/character-creation
- Expected: Arctic Elf is present as an elf-only heritage option.
- AC: Happy Path-1

## TC-EAR-02 — Primary passive effect application
- Description: Selecting Arctic Elf grants cold resistance equal to half the character level, minimum 1.
- Suite: playwright/encounter
- Expected: Selecting Arctic Elf grants cold resistance equal to half the character level, minimum 1.; Environmental cold effects are treated as one step less severe for the character.
- AC: Happy Path-2, Happy Path-3

## TC-EAR-03 — Scaling, automation, and visible state updates
- Description: Environmental cold effects are treated as one step less severe for the character.
- Suite: playwright/encounter
- Expected: Environmental cold effects are treated as one step less severe for the character.; The cold-resistance value recalculates when the character level changes.
- AC: Happy Path-3, Happy Path-4

## TC-EAR-04 — Edge-case rules interaction coverage
- Description: Level 1 characters still receive the minimum cold resistance of 1.
- Suite: playwright/encounter
- Expected: Level 1 characters still receive the minimum cold resistance of 1.; Only cold/environmental-cold effects are downgraded; unrelated environmental hazards stay unchanged.; One-step severity downgrades follow the documented ladder without skipping directly to harmless.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-EAR-05 — Validation errors and safe fallback behavior
- Description: Non-elf characters cannot select Arctic Elf heritage.
- Suite: playwright/encounter
- Expected: Non-elf characters cannot select Arctic Elf heritage.; If an environmental hazard lacks cold-severity metadata, the hazard resolves normally instead of producing an implementation error.
- AC: Failure Modes-1, Failure Modes-2
