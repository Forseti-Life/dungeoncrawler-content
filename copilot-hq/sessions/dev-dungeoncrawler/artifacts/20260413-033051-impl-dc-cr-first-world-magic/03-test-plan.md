# Test Plan: dc-cr-first-world-magic

## Coverage summary
- AC items: 9 (6 happy path, 2 edge cases, 2 failure modes)
- Test cases: 7 (TC-FWM-01–07)
- Suites: playwright (character creation, spellcasting flows)
- Security: AC exemption granted (no new routes)

---

## TC-FWM-01 — Feat selectable for Gnome at level 1
- Description: First World Magic appears in level 1 gnome ancestry feat list
- Suite: playwright/character-creation
- Expected: feat_pool[gnome][level_1] includes first-world-magic
- AC: Availability

## TC-FWM-02 — Primal cantrip selected and stored
- Description: Player selects primal cantrip at feat acquisition; stored as innate at-will spell
- Suite: playwright/character-creation
- Expected: character.innate_spells includes {cantrip, tradition: primal, at_will: true, source: first-world-magic, fixed: true}
- AC: Fixed Primal Cantrip-1, Fixed Primal Cantrip-3

## TC-FWM-03 — Cantrip is fixed (no swap)
- Description: First World Magic cantrip cannot be changed after feat acquisition
- Suite: playwright/character-creation
- Expected: no swap action available for first-world-magic innate spell; UI does not offer daily swap
- AC: Fixed Primal Cantrip-2, Failure Modes-2

## TC-FWM-04 — Cantrip at will (no use counter)
- Description: Innate cantrip has no per-day use cap
- Suite: playwright/encounter
- Expected: casting the cantrip does not decrement any use counter
- AC: Failure Modes-1

## TC-FWM-05 — Cantrip heightened by character level
- Description: Cantrip spell level = ceil(character_level / 2)
- Suite: playwright/character-creation
- Expected: at level 1 → spell level 1; at level 5 → spell level 3; at level 9 → spell level 5
- AC: Fixed Primal Cantrip-4

## TC-FWM-06 — Wellspring override applied at feat acquisition
- Description: If Wellspring Gnome, cantrip tradition overridden to wellspring_tradition on feat acquisition
- Suite: playwright/character-creation
- Expected: innate_spell.tradition = character.wellspring_tradition (not primal) when Wellspring heritage active
- AC: Wellspring Override-1, Wellspring Override-2

## TC-FWM-07 — Stacks with Fey-touched Heritage cantrip
- Description: First World Magic + Fey-touched Heritage both grant separate at-will cantrips
- Suite: playwright/character-creation
- Expected: character.innate_spells includes two separate entries (one from each source); same spell allowed
- AC: Edge Case-1, Edge Case-2
