# Test Plan: dc-cr-gnome-heritage-umbral

## Coverage summary
- AC items: 5 (3 happy path, 1 edge case, 1 failure mode)
- Test cases: 4 (TC-UMB-01–04)
- Suites: playwright (character creation, encounter lighting flows)
- Security: AC exemption granted (no new routes)

---

## TC-UMB-01 — Heritage selectable for Gnome
- Description: Umbral Gnome appears in heritage options when Gnome is chosen
- Suite: playwright/character-creation
- Expected: heritage_options includes umbral-gnome
- AC: Heritage Availability

## TC-UMB-02 — Darkvision sense granted
- Description: Umbral Gnome character has darkvision in sense list
- Suite: playwright/character-creation
- Expected: character.senses includes {type: darkvision}; uses shared darkvision sense type
- AC: Darkvision-1, Darkvision-2

## TC-UMB-03 — Darkvision in complete darkness
- Description: Character can see normally in completely dark environments (no light source)
- Suite: playwright/encounter
- Expected: when lighting = complete_darkness, Umbral Gnome has no vision penalty; standard sight range applies
- AC: Failure Modes-1

## TC-UMB-04 — No duplicate sense entry from stacking
- Description: If darkvision already present from another source, no duplicate entry added
- Suite: playwright/character-creation
- Expected: character.senses.count(type: darkvision) = 1 regardless of how many darkvision sources apply
- AC: Edge Case-1
