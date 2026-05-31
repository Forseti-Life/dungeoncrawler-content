# Test Plan: dc-cr-goblin-ancestry

## Coverage summary
- AC items: 9 (6 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GOB-01-05)
- Suites: playwright (character creation, character sheet)
- Security: AC exemption granted (existing character routes only)

---

## TC-GOB-01 — Goblin ancestry selectable
- Description: Goblin appears in the ancestry picker during character creation.
- Suite: playwright/character-creation
- Expected: ancestry list includes Goblin
- AC: Character creation availability

## TC-GOB-02 — Core ancestry stats applied
- Description: Selecting Goblin assigns HP, size, speed, boosts, and flaw correctly.
- Suite: playwright/character-creation
- Expected: character ancestry data = {hp: 6, size: Small, speed: 25, boosts: [Dex, Cha, Free], flaw: Wis}
- AC: Core ancestry stats-1, Core ancestry stats-2, Core ancestry stats-3

## TC-GOB-03 — Heritage and feat tree linked
- Description: Goblin heritages and ancestry feats become available after choosing Goblin.
- Suite: playwright/character-creation
- Expected: goblin heritage list and goblin feat list are shown
- AC: Ancestry integration-1

## TC-GOB-04 — Goblin ancestry persists to sheet
- Description: Completed character shows Goblin ancestry data on the sheet.
- Suite: playwright/character-sheet
- Expected: ancestry block renders Goblin with correct stats and linked options
- AC: Ancestry integration-2

## TC-GOB-05 — Invalid ancestry payload rejected
- Description: Attempt to submit altered goblin stats from client.
- Suite: playwright/character-creation
- Expected: server rejects mismatched stat payload and reuses canonical ancestry values
- AC: Failure Modes-1, Failure Modes-2
