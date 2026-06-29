# Test Plan: dc-cr-dwarf-heritage-death-warden

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-DWD-01-05)
- Suites: playwright (character creation, save resolution, combat log)
- Security: Security AC exemption: passive ancestry heritage behavior only; no new routes or input surfaces beyond existing heritage assignment and combat-resolution handlers.

---

## TC-DWD-01 — Heritage availability and ancestry gating
- Description: The Death Warden heritage exists as a selectable dwarf heritage and is unavailable to non-dwarf ancestries.
- Suite: playwright/character-creation
- Expected: The Death Warden heritage exists as a selectable dwarf heritage and is unavailable to non-dwarf ancestries.
- AC: Happy Path-1

## TC-DWD-02 — Primary passive effect application
- Description: When a Death Warden dwarf succeeds on a saving throw against a necromancy effect, the final result is upgraded to a critical success.
- Suite: playwright/encounter
- Expected: When a Death Warden dwarf succeeds on a saving throw against a necromancy effect, the final result is upgraded to a critical success.; Necromancy critical successes remain critical successes rather than being double-upgraded or otherwise altered.
- AC: Happy Path-2, Happy Path-3

## TC-DWD-03 — Scaling, automation, and visible state updates
- Description: Necromancy critical successes remain critical successes rather than being double-upgraded or otherwise altered.
- Suite: playwright/encounter
- Expected: Necromancy critical successes remain critical successes rather than being double-upgraded or otherwise altered.; The heritage effect is passive and automatic; no extra player action or toggle is required during save resolution.
- AC: Happy Path-3, Happy Path-4

## TC-DWD-04 — Edge-case rules interaction coverage
- Description: The save upgrade only applies to effects tagged as necromancy and does not modify non-necromancy saves.
- Suite: playwright/encounter
- Expected: The save upgrade only applies to effects tagged as necromancy and does not modify non-necromancy saves.; Characters can hold only one dwarf heritage at a time, so Death Warden cannot stack with another dwarf heritage bonus.; Save logs or combat resolution output clearly show the upgraded outcome for QA traceability.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-DWD-05 — Validation errors and safe fallback behavior
- Description: Invalid heritage selection for the wrong ancestry is rejected rather than persisted.
- Suite: playwright/encounter
- Expected: Invalid heritage selection for the wrong ancestry is rejected rather than persisted.; If an effect lacks the necromancy tag, the save resolver falls back to the baseline success result without throwing an error.
- AC: Failure Modes-1, Failure Modes-2
