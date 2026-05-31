# Test Plan: dc-cr-gnome-obsession

## Coverage summary
- AC items: 9 (5 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-GOBS-01-05)
- Suites: playwright (character creation, level-up, skill sheet)
- Security: AC exemption granted (existing feat/level-up routes only)

---

## TC-GOBS-01 — Feat availability and Lore selection
- Description: Gnome Obsession appears and allows a Lore choice.
- Suite: playwright/character-creation
- Expected: feat picker includes Gnome Obsession and Lore selector accepts Lore skills only
- AC: Availability, Lore selection and scaling-1, Failure Modes-1

## TC-GOBS-02 — Level 2 expert upgrade
- Description: Character levels from 1 to 2.
- Suite: playwright/level-up
- Expected: chosen Lore and background Lore (if any) upgrade to expert
- AC: Lore selection and scaling-2, Edge Cases-1

## TC-GOBS-03 — Level 7 master upgrade
- Description: Character reaches level 7.
- Suite: playwright/level-up
- Expected: tracked Lore skills upgrade to master
- AC: Lore selection and scaling-3

## TC-GOBS-04 — Level 15 legendary upgrade
- Description: Character reaches level 15.
- Suite: playwright/level-up
- Expected: tracked Lore skills upgrade to legendary
- AC: Lore selection and scaling-4

## TC-GOBS-05 — No off-schedule upgrades
- Description: Character levels to any non-milestone level.
- Suite: playwright/level-up
- Expected: no extra proficiency increase occurs from the feat
- AC: Failure Modes-2, Edge Cases-2
