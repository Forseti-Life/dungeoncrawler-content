# Test Plan: dc-cr-first-world-adept

## Coverage summary
- AC items: 7 (3 happy path, 2 edge cases, 2 failure modes)
- Test cases: 5 (TC-FWA-01-05)
- Suites: playwright (character creation, feat progression, spellcasting)
- Security: AC exemption granted (existing feat/spell routes only)

---

## TC-FWA-01 — Prerequisite-gated feat availability
- Description: First World Adept appears only for characters with a primal innate spell.
- Suite: playwright/feat-progression
- Expected: feat locked before prerequisite, selectable after prerequisite is present
- AC: Availability, Failure Modes-1

## TC-FWA-02 — Faerie fire granted correctly
- Description: Selecting the feat grants faerie fire as a 2nd-level primal innate spell.
- Suite: playwright/character-sheet
- Expected: innate spell entry exists with `faerie fire`, rank 2, primal, uses_per_day = 1
- AC: Granted spells-1

## TC-FWA-03 — Invisibility granted correctly
- Description: Selecting the feat grants invisibility as a 2nd-level primal innate spell.
- Suite: playwright/character-sheet
- Expected: innate spell entry exists with `invisibility`, rank 2, primal, uses_per_day = 1
- AC: Granted spells-2

## TC-FWA-04 — Uses reset daily
- Description: Spend both innate spells, then complete daily preparation.
- Suite: playwright/spellcasting
- Expected: both spell uses reset to 1/day available
- AC: Edge Cases-2, Failure Modes-2

## TC-FWA-05 — Non-primal innate spell alone does not qualify
- Description: Character has innate spell access from another tradition only.
- Suite: playwright/feat-progression
- Expected: feat remains unavailable until a primal innate spell source exists
- AC: Edge Cases-1
