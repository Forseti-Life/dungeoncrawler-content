# Test Plan: dc-cr-halfling-ancestry

## Coverage summary
- AC items: 10 (6 happy path, 2 edge cases, 2 failure modes)
- Test cases: 6 (TC-HAL-01-06)
- Suites: playwright (character creation, character sheet)
- Security: AC exemption granted (existing character routes only)

---

## TC-HAL-01 — Halfling ancestry selectable
- Description: Halfling appears in the ancestry picker.
- Suite: playwright/character-creation
- Expected: ancestry list includes Halfling
- AC: Character creation availability

## TC-HAL-02 — Core ancestry stats applied
- Description: Selecting Halfling assigns HP, size, speed, and boosts.
- Suite: playwright/character-creation
- Expected: character ancestry data = {hp: 6, size: Small, speed: 25, boosts include Dex and Wis}
- AC: Core ancestry stats-1, Core ancestry stats-2

## TC-HAL-03 — Halfling Luck granted
- Description: Character gains the baseline halfling luck ancestry benefit.
- Suite: playwright/character-sheet
- Expected: halfling luck passive appears on character summary
- AC: Automatic ancestry traits-1

## TC-HAL-04 — Keen Eyes granted automatically
- Description: Halfling selection auto-grants Keen Eyes.
- Suite: playwright/character-sheet
- Expected: keen-eyes trait/effect present with no extra player selection
- AC: Automatic ancestry traits-2, Edge Cases-1

## TC-HAL-05 — Heritage and feat tree unlocks
- Description: Halfling-specific heritages and feats appear after ancestry selection.
- Suite: playwright/character-creation
- Expected: halfling heritage list and feat list are visible and valid
- AC: Ancestry integration-1

## TC-HAL-06 — Non-halfling access blocked
- Description: Non-halfling character tries to access halfling-only options.
- Suite: playwright/character-creation
- Expected: halfling-only heritages/feats rejected by server
- AC: Failure Modes-1, Failure Modes-2, Edge Cases-2
