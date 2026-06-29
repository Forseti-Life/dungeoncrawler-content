# Test Plan: dc-cr-unburdened-iron

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-UBI-01-05)
- Suites: playwright (feat progression, equipment, speed calculation)
- Security: Security AC exemption: ancestry-feat and movement-math scope only; no new routes or input surfaces beyond existing feat assignment and movement handlers.

---

## TC-UBI-01 — Feat availability and prerequisite gating
- Description: Unburdened Iron exists as a level-1 dwarf ancestry feat.
- Suite: playwright/feat-progression
- Expected: Unburdened Iron exists as a level-1 dwarf ancestry feat.
- AC: Happy Path-1

## TC-UBI-02 — Primary granted benefit application
- Description: Worn armor no longer applies its Speed penalty to a character with the feat.
- Suite: playwright/encounter
- Expected: Worn armor no longer applies its Speed penalty to a character with the feat.; The largest single other Speed penalty affecting the character is reduced by 5 feet.
- AC: Happy Path-2, Happy Path-3

## TC-UBI-03 — Recalculation, retraining, and later progression behavior
- Description: The largest single other Speed penalty affecting the character is reduced by 5 feet.
- Suite: playwright/encounter
- Expected: The largest single other Speed penalty affecting the character is reduced by 5 feet.; Speed calculations remain deterministic when armor penalties and other penalties are combined.
- AC: Happy Path-3, Happy Path-4

## TC-UBI-04 — Edge-case rules interaction coverage
- Description: Only the largest non-armor penalty is reduced; multiple non-armor penalties are not each reduced by 5 feet.
- Suite: playwright/encounter
- Expected: Only the largest non-armor penalty is reduced; multiple non-armor penalties are not each reduced by 5 feet.; A character with no armor equipped still receives the largest-other-penalty reduction if one exists.; Speed can never become negative as a result of this adjustment logic.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-UBI-05 — Validation errors and malformed-data handling
- Description: Selecting the feat without a valid dwarf ancestry slot is rejected.
- Suite: playwright/encounter
- Expected: Selecting the feat without a valid dwarf ancestry slot is rejected.; Malformed speed modifiers do not crash movement calculations; they surface a validation issue instead.
- AC: Failure Modes-1, Failure Modes-2
