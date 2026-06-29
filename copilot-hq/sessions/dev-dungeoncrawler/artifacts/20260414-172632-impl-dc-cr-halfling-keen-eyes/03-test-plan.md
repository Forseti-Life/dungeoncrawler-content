# Test Plan: dc-cr-halfling-keen-eyes

## Coverage summary
- AC items: 8 (4 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-HKE-01-05)
- Suites: playwright (encounter, visibility, character sheet)
- Security: AC exemption granted (existing combat routes only)

---

## TC-HKE-01 — Keen Eyes auto-granted
- Description: Halfling ancestry automatically grants Keen Eyes.
- Suite: playwright/character-sheet
- Expected: Keen Eyes passive appears without extra choice
- AC: Automatic grant

## TC-HKE-02 — Seek bonus within 30 feet
- Description: Halfling uses Seek on a hidden target within 30 feet.
- Suite: playwright/encounter
- Expected: Seek check includes +2 circumstance bonus
- AC: Seek bonus

## TC-HKE-03 — Concealed target flat-check reduced
- Description: Halfling attacks a concealed target.
- Suite: playwright/encounter
- Expected: flat-check DC = 3
- AC: Flat-check reduction-1

## TC-HKE-04 — Hidden target flat-check reduced
- Description: Halfling attacks a hidden target.
- Suite: playwright/encounter
- Expected: flat-check DC = 9
- AC: Flat-check reduction-2

## TC-HKE-05 — Defaults preserved outside scope
- Description: Test non-halfling or target beyond 30 feet.
- Suite: playwright/encounter
- Expected: default modifiers/DCs remain in place
- AC: Edge Cases-1, Edge Cases-2, Failure Modes-1, Failure Modes-2
