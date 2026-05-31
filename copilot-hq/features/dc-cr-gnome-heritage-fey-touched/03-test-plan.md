# Test Plan: dc-cr-gnome-heritage-fey-touched

## Coverage summary
- AC items: 10 (7 happy path, 2 edge cases, 2 failure modes)
- Test cases: 8 (TC-FTG-01–08)
- Suites: playwright (character creation, spellcasting flows)
- Security: AC exemption granted (no new routes)

---

## TC-FTG-01 — fey trait added
- Description: Fey-touched Gnome gains the fey trait
- Suite: playwright/character-creation
- Expected: character.traits includes [Gnome, Humanoid, fey]
- AC: Traits

## TC-FTG-02 — Primal cantrip selectable at character creation
- Description: Player must choose one primal cantrip from spell list; stored as innate spell
- Suite: playwright/character-creation
- Expected: character.innate_spells includes selected primal cantrip; tradition = primal; at_will = true
- AC: At-Will Primal Cantrip-1, At-Will Primal Cantrip-2

## TC-FTG-03 — Cantrip heightened by character level
- Description: Cantrip spell level = ceil(character_level / 2)
- Suite: playwright/character-creation
- Expected: at level 1 → spell level 1; at level 5 → spell level 3; at level 9 → spell level 5
- AC: At-Will Primal Cantrip-3, Failure Modes-2

## TC-FTG-04 — Cantrip cast at will (unlimited)
- Description: Innate cantrip has no use-per-day cap
- Suite: playwright/encounter
- Expected: casting the cantrip does not decrement a use counter; can be cast multiple times per encounter
- AC: Failure Modes-1

## TC-FTG-05 — Daily cantrip swap: 10-minute activity
- Description: Swap action is 10 min, concentrate; replacement must be from primal list
- Suite: playwright/downtime
- Expected: swap_action.duration = 10 min; swap_action.traits includes concentrate; replacement from primal list only
- AC: Daily Cantrip Swap-1, Daily Cantrip Swap-2

## TC-FTG-06 — Daily swap resets at preparation
- Description: Only one swap per day; resets on long rest / daily preparation
- Suite: playwright/downtime
- Expected: swap_used_today = true blocks second swap; resets to false on new day
- AC: Daily Cantrip Swap-3, Edge Case-2

## TC-FTG-07 — Second swap attempt blocked
- Description: Attempting a second same-day swap triggers a system block message
- Suite: playwright/downtime
- Expected: second swap attempt returns error "Daily cantrip swap already used"
- AC: Edge Case-2

## TC-FTG-08 — fey trait does not replace other traits
- Description: fey trait is additive, not a replacement
- Suite: playwright/character-creation
- Expected: character.traits = [Gnome, Humanoid, fey] (all three present)
- AC: Edge Case-1
