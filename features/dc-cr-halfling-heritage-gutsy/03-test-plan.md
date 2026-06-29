# Test Plan: dc-cr-halfling-heritage-gutsy

## Coverage summary
- AC items: 6 (2 happy path, 2 edge cases, 2 failure modes)
- Test cases: 4 (TC-HGU-01-04)
- Suites: playwright (character creation, encounter/save resolution)
- Security: AC exemption granted (existing save-resolution routes only)

---

## TC-HGU-01 — Heritage selectable
- Description: Gutsy Halfling appears in the halfling heritage picker.
- Suite: playwright/character-creation
- Expected: heritage list includes Gutsy Halfling
- AC: Heritage selection

## TC-HGU-02 — Success upgrades on emotion save
- Description: Character succeeds on a save against an emotion effect.
- Suite: playwright/encounter
- Expected: save result upgraded from success to critical success
- AC: Emotion save upgrade, Edge Cases-1

## TC-HGU-03 — Failure does not upgrade
- Description: Character fails the save against an emotion effect.
- Suite: playwright/encounter
- Expected: result remains failure
- AC: Failure Modes-1

## TC-HGU-04 — Non-emotion effect unaffected
- Description: Character succeeds on a non-emotion saving throw.
- Suite: playwright/encounter
- Expected: result remains ordinary success
- AC: Edge Cases-2, Failure Modes-2
