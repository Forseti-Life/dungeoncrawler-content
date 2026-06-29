# Test Plan: dc-cr-gnome-heritage-chameleon

## Coverage summary
- AC items: 8 (5 happy path, 2 edge cases, 1 failure mode)
- Test cases: 6 (TC-CHM-01–06)
- Suites: playwright (character creation, encounter/stealth flows)
- Security: AC exemption granted (no new routes)

---

## TC-CHM-01 — Heritage selectable for Gnome
- Description: Chameleon Gnome appears in heritage options when Gnome is chosen
- Suite: playwright/character-creation
- Expected: heritage_options includes chameleon-gnome
- AC: Heritage Availability

## TC-CHM-02 — Stealth bonus in matching terrain
- Description: Character in terrain with matching coloration-tag receives +2 circumstance bonus to Stealth
- Suite: playwright/encounter
- Expected: stealth_roll.circumstance_bonus = 2 when terrain_tag matches character.coloration_tag
- AC: Passive Stealth Bonus-1, Passive Stealth Bonus-3

## TC-CHM-03 — Stealth bonus lost on terrain change
- Description: Bonus is removed when environment changes to non-matching terrain
- Suite: playwright/encounter
- Expected: stealth_roll.circumstance_bonus = 0 after terrain changes to non-matching type
- AC: Passive Stealth Bonus-2, Failure Modes-1

## TC-CHM-04 — 1-action color shift grants bonus
- Description: Spending 1 action enables the Stealth bonus (sets coloration to matching)
- Suite: playwright/encounter
- Expected: action color-shift sets character.coloration_tag to current terrain_tag; next Stealth check gets +2
- AC: Minor Color Shift

## TC-CHM-05 — Circumstance bonus does not stack
- Description: Multiple circumstance bonuses to Stealth — only highest applies
- Suite: playwright/encounter
- Expected: when two circumstance bonuses exist, character.stealth_circumstance_bonus = max(both)
- AC: Edge Case-2

## TC-CHM-06 — Dramatic shift takes 1 hour
- Description: Dramatic full-body coloration change is a downtime activity taking up to 1 hour
- Suite: playwright/downtime
- Expected: dramatic-color-shift action classified as downtime; duration = 60 min
- AC: Dramatic Color Shift
