# Test Plan: dc-cr-dwarf-heritage-strong-blooded

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-DSB-01-05)
- Suites: playwright (character creation, afflictions, level scaling)
- Security: Security AC exemption: passive ancestry heritage behavior only; no new routes or input surfaces beyond existing heritage assignment and affliction-resolution handlers.

---

## TC-DSB-01 — Heritage availability and ancestry gating
- Description: Strong-Blooded is available as a dwarf-only heritage selection.
- Suite: playwright/character-creation
- Expected: Strong-Blooded is available as a dwarf-only heritage selection.
- AC: Happy Path-1

## TC-DSB-02 — Primary passive effect application
- Description: The heritage grants poison resistance equal to half the character level, minimum 1.
- Suite: playwright/encounter
- Expected: The heritage grants poison resistance equal to half the character level, minimum 1.; On a successful save against a poison affliction, the poison stage is reduced by 2, or by 1 if the poison is virulent.
- AC: Happy Path-2, Happy Path-3

## TC-DSB-03 — Scaling, automation, and visible state updates
- Description: On a successful save against a poison affliction, the poison stage is reduced by 2, or by 1 if the poison is virulent.
- Suite: playwright/encounter
- Expected: On a successful save against a poison affliction, the poison stage is reduced by 2, or by 1 if the poison is virulent.; On a critical success, the poison stage is reduced by 3, or by 2 if the poison is virulent.
- AC: Happy Path-3, Happy Path-4

## TC-DSB-04 — Edge-case rules interaction coverage
- Description: Level-up recalculates the poison-resistance value without requiring the heritage to be re-selected.
- Suite: playwright/encounter
- Expected: Level-up recalculates the poison-resistance value without requiring the heritage to be re-selected.; Non-poison afflictions such as disease do not receive the Strong-Blooded stage-reduction benefit.; Virulent-poison handling still uses the reduced stage-drop values rather than the standard success/critical-success drops.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-DSB-05 — Validation errors and safe fallback behavior
- Description: Selecting the heritage for a non-dwarf ancestry is rejected.
- Suite: playwright/encounter
- Expected: Selecting the heritage for a non-dwarf ancestry is rejected.; If the affliction is missing poison metadata, resolution falls back safely instead of applying the Strong-Blooded adjustment incorrectly.
- AC: Failure Modes-1, Failure Modes-2
