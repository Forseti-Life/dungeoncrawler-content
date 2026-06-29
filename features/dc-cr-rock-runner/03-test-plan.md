# Test Plan: dc-cr-rock-runner

## Coverage summary
- AC items: 9 (4 happy path, 3 edge cases, 2 failure modes)
- Test cases: 5 (TC-RRN-01-05)
- Suites: playwright (feat progression, terrain movement, balance resolution)
- Security: Security AC exemption: ancestry-feat and terrain-resolution scope only; no new routes or input surfaces beyond existing feat assignment and movement handlers.

---

## TC-RRN-01 — Feat availability and prerequisite gating
- Description: Rock Runner exists as a level-1 dwarf ancestry feat.
- Suite: playwright/feat-progression
- Expected: Rock Runner exists as a level-1 dwarf ancestry feat.
- AC: Happy Path-1

## TC-RRN-02 — Primary granted benefit application
- Description: Stone or earth rubble no longer imposes its normal movement penalty on a character with Rock Runner.
- Suite: playwright/encounter
- Expected: Stone or earth rubble no longer imposes its normal movement penalty on a character with Rock Runner.; The character is not flat-footed when balancing on stone or earth narrow surfaces.
- AC: Happy Path-2, Happy Path-3

## TC-RRN-03 — Recalculation, retraining, and later progression behavior
- Description: The character is not flat-footed when balancing on stone or earth narrow surfaces.
- Suite: playwright/encounter
- Expected: The character is not flat-footed when balancing on stone or earth narrow surfaces.; A successful Balance check on stone or earth upgrades to a critical success for the feat owner.
- AC: Happy Path-3, Happy Path-4

## TC-RRN-04 — Edge-case rules interaction coverage
- Description: The feat only changes behavior on terrain or surfaces tagged as stone or earth; wood, metal, ice, and other materials remain baseline.
- Suite: playwright/encounter
- Expected: The feat only changes behavior on terrain or surfaces tagged as stone or earth; wood, metal, ice, and other materials remain baseline.; If the tactical grid omits a surface-material tag, balance and movement resolve with the default rules.; Only the feat owner receives the benefits; adjacent characters on the same tile do not.
- AC: Edge Cases-1, Edge Cases-2, Edge Cases-3

## TC-RRN-05 — Validation errors and malformed-data handling
- Description: Selecting the feat without a valid dwarf ancestry slot is rejected.
- Suite: playwright/encounter
- Expected: Selecting the feat without a valid dwarf ancestry slot is rejected.; Unknown or malformed terrain tags do not crash movement/balance resolution.
- AC: Failure Modes-1, Failure Modes-2
