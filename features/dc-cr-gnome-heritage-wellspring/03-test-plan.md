# Test Plan: dc-cr-gnome-heritage-wellspring

## Coverage summary
- AC items: 10 (7 happy path, 2 edge cases, 2 failure modes)
- Test cases: 8 (TC-WEL-01–08)
- Suites: playwright (character creation, spellcasting flows)
- Security: AC exemption granted (no new routes)

---

## TC-WEL-01 — Tradition selection stored
- Description: Player chooses arcane, divine, or occult; stored as wellspring_tradition
- Suite: playwright/character-creation
- Expected: character.wellspring_tradition ∈ {arcane, divine, occult} after heritage selection
- AC: Tradition Selection-1, Tradition Selection-2

## TC-WEL-02 — Primal not available as tradition choice
- Description: Primal is not an option when selecting Wellspring tradition
- Suite: playwright/character-creation
- Expected: tradition_options = [arcane, divine, occult]; primal absent
- AC: Edge Case-1

## TC-WEL-03 — Cantrip from chosen tradition stored at will
- Description: Cantrip from wellspring_tradition list stored as innate at-will spell
- Suite: playwright/character-creation
- Expected: character.innate_spells includes {cantrip, tradition: wellspring_tradition, at_will: true}
- AC: At-Will Cantrip-2, At-Will Cantrip-3

## TC-WEL-04 — Cantrip heightened by character level
- Description: Cantrip spell level = ceil(character_level / 2)
- Suite: playwright/character-creation
- Expected: at level 1 → spell level 1; at level 7 → spell level 4
- AC: At-Will Cantrip-4

## TC-WEL-05 — Cantrip cast at will (unlimited)
- Description: Wellspring cantrip has no per-day use cap
- Suite: playwright/encounter
- Expected: casting cantrip does not decrement any use counter
- AC: Failure Modes-1

## TC-WEL-06 — First World Magic tradition overridden
- Description: If character takes First World Magic feat, its primal innate spell is overridden to wellspring_tradition
- Suite: playwright/character-creation
- Expected: first-world-magic innate spell tradition = wellspring_tradition (not primal) when Wellspring heritage active
- AC: Tradition Override-1, Tradition Override-2

## TC-WEL-07 — Override applies to all gnome-ancestry primal innate spells
- Description: Every gnome ancestry feat that grants primal innate spells has its tradition overridden
- Suite: playwright/character-creation
- Expected: all gnome-ancestry innate spell records with tradition = primal are updated to wellspring_tradition
- AC: Tradition Override-3

## TC-WEL-08 — Override does not affect class spells
- Description: Tradition override is scoped to gnome ancestry feat innate spells only
- Suite: playwright/character-creation
- Expected: class innate spells and non-gnome feat innate spells retain their original tradition
- AC: Failure Modes-2
