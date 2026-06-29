# Test Plan: dc-cr-burrow-elocutionist

## Coverage summary
- AC items: 7 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 4 (TC-BEL-01-04)
- Suites: playwright (character creation, exploration/dialogue)
- Security: AC exemption granted (existing interaction routes only)

---

## TC-BEL-01 — Feat availability
- Description: Burrow Elocutionist appears in the Gnome ancestry feat list.
- Suite: playwright/character-creation
- Expected: feat picker includes `dc-cr-burrow-elocutionist`
- AC: Availability

## TC-BEL-02 — Burrowing creature dialogue enabled
- Description: Character attempts to converse with a burrowing creature.
- Suite: playwright/exploration
- Expected: dialogue option is available and responses are understandable
- AC: Communication effect-1, Communication effect-2

## TC-BEL-03 — Non-burrowing creature unaffected
- Description: Character tries the same interaction with a non-burrowing creature.
- Suite: playwright/exploration
- Expected: no special communication channel is granted
- AC: Edge Cases-1, Failure Modes-1

## TC-BEL-04 — Character without feat blocked
- Description: Non-qualified character attempts burrow-language interaction.
- Suite: playwright/exploration
- Expected: interaction not available
- AC: Failure Modes-2, Edge Cases-2
