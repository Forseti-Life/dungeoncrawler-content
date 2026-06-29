# Test Plan: dc-cr-animal-accomplice

## Coverage summary
- AC items: 7 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 6 (TC-AAC-01–06)
- Suites: playwright (character creation, familiar management)
- Security: AC exemption granted (existing familiar routes only)

---

## TC-AAC-01 — Feat availability
- Description: Animal Accomplice appears in the Gnome feat list.
- Suite: playwright/character-creation
- Expected: feat picker includes `dc-cr-animal-accomplice`
- AC: Availability

## TC-AAC-02 — Familiar granted on feat selection
- Description: Character selects the feat during creation or progression.
- Suite: playwright/character-creation
- Expected: familiar record is created using standard familiar rules
- AC: Familiar grant-1

## TC-AAC-03 — Familiar type chosen from valid catalog
- Description: Player selects a familiar type.
- Suite: playwright/familiar
- Expected: valid familiar types are accepted; burrow-speed options may be highlighted
- AC: Familiar grant-2, Edge Cases-1

## TC-AAC-04 — Non-spellcaster still receives familiar
- Description: Gnome martial/non-caster takes the feat.
- Suite: playwright/character-creation
- Expected: familiar grant succeeds despite no spellcasting class
- AC: Edge Cases-2

## TC-AAC-05 — Invalid familiar type rejected
- Description: Submit a familiar type not in the catalog.
- Suite: playwright/familiar
- Expected: server rejects invalid familiar assignment
- AC: Failure Modes-2

## TC-AAC-06 — No familiar granted without feat
- Description: Gnome character who has NOT taken Animal Accomplice completes character creation.
- Suite: playwright/character-creation
- Expected: no familiar record is created via this feat path
- AC: Failure Modes-1
