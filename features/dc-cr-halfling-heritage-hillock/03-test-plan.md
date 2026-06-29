# Test Plan: dc-cr-halfling-heritage-hillock

## Coverage summary
- AC items: 7 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-HHI-01-05)
- Suites: playwright (character creation, rest, medicine/healing)
- Security: AC exemption granted (existing healing routes only)

---

## TC-HHI-01 — Heritage selectable
- Description: Hillock Halfling appears in the halfling heritage picker.
- Suite: playwright/character-creation
- Expected: heritage list includes Hillock Halfling
- AC: Heritage selection

## TC-HHI-02 — Overnight rest grants level bonus
- Description: Character completes overnight rest.
- Suite: playwright/rest
- Expected: HP regained includes base recovery + character level
- AC: Overnight recovery bonus, Edge Cases-2

## TC-HHI-03 — Treat Wounds snack rider adds level
- Description: Another character Treats Wounds on the patient and the snack rider is used.
- Suite: playwright/medicine
- Expected: healing total includes +character level
- AC: Treat Wounds snack rider

## TC-HHI-04 — Bonus not applied outside allowed sources
- Description: Apply a non-Treat-Wounds healing source.
- Suite: playwright/medicine
- Expected: no snack rider or heritage bonus added
- AC: Edge Cases-1, Failure Modes-1

## TC-HHI-05 — Snack rider cannot double-apply
- Description: Attempt to reapply the rider to the same Treat Wounds resolution.
- Suite: playwright/medicine
- Expected: only one heritage bonus instance is counted
- AC: Failure Modes-2
