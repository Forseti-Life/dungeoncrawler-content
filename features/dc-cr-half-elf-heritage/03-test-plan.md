# Test Plan: dc-cr-half-elf-heritage

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-HEF-01-05)
- Suites: playwright (character creation, ancestry feat picker, validation)
- Security: Security AC exemption: ancestry heritage and feat-eligibility scope only; no new routes or input surfaces beyond existing heritage assignment and ancestry-feat handlers.

---

## TC-HEF-01 — Heritage availability and ancestry gating
- Description: Half-Elf is implemented as a selectable Human heritage rather than a standalone ancestry.
- Suite: playwright/character-creation
- Expected: Half-Elf is implemented as a selectable Human heritage rather than a standalone ancestry.
- AC: Happy Path-1

## TC-HEF-02 — Primary passive effect application
- Description: Selecting the heritage grants the elf trait, the half-elf trait, and low-light vision.
- Suite: playwright/character-creation
- Expected: Selecting the heritage grants the elf trait, the half-elf trait, and low-light vision.; Ancestry-feat selection for a Half-Elf character can draw from human, elf, and half-elf feat pools while still enforcing feat prerequisites.
- AC: Happy Path-2, Happy Path-3

## TC-HEF-03 — Scaling, automation, and visible state updates
- Description: Ancestry-feat selection for a Half-Elf character can draw from human, elf, and half-elf feat pools while still enforcing feat prerequisites.
- Suite: playwright/feat-progression
- Expected: Ancestry-feat selection for a Half-Elf character can draw from human, elf, and half-elf feat pools while still enforcing feat prerequisites.; The expanded feat-pool behavior is visible anywhere the character gains an ancestry feat slot.
- AC: Happy Path-3, Happy Path-4

## TC-HEF-04 — Edge-case rules interaction coverage
- Description: If the character already has low-light vision from another valid source, the heritage does not create duplicate sense flags.
- Suite: playwright/feat-progression
- Expected: If the character already has low-light vision from another valid source, the heritage does not create duplicate sense flags.; Half-Elf remains mutually exclusive with other Human heritages.; Feat browsing clearly indicates why an elf, half-elf, or human feat is or is not selectable for the current character.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-HEF-05 — Validation errors and safe fallback behavior
- Description: Non-Human characters cannot select the Half-Elf heritage.
- Suite: playwright/feat-progression
- Expected: Non-Human characters cannot select the Half-Elf heritage.; The feat picker rejects ancestry feats outside the allowed human/elf/half-elf pools instead of silently accepting them.
- AC: Failure Modes-1, Failure Modes-2
