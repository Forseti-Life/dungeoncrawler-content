# Test Plan: dc-cr-gnome-heritage-sensate

## Coverage summary
- AC items: 9 (6 happy path, 2 edge cases, 2 failure modes)
- Test cases: 7 (TC-SEN-01–07)
- Suites: playwright (character creation, encounter/perception flows)
- Security: AC exemption granted (no new routes)

---

## TC-SEN-01 — Imprecise scent sense registered
- Description: Sensate Gnome has imprecise scent at 30 ft in character data
- Suite: playwright/character-creation
- Expected: character.senses includes {type: imprecise-scent, range: 30}
- AC: Imprecise Scent-1, Imprecise Scent-2

## TC-SEN-02 — Downwind range doubled
- Description: Scent range 60 ft when creature is downwind
- Suite: playwright/encounter
- Expected: effective_scent_range = 60 when wind_state = downwind
- AC: Wind Direction-2

## TC-SEN-03 — Upwind range halved
- Description: Scent range 15 ft when creature is upwind
- Suite: playwright/encounter
- Expected: effective_scent_range = 15 when wind_state = upwind
- AC: Wind Direction-3, Failure Modes-2

## TC-SEN-04 — Neutral/no-wind defaults to base range
- Description: When encounter has no wind model, scent range = 30 ft
- Suite: playwright/encounter
- Expected: effective_scent_range = 30 when wind_state = neutral or absent
- AC: Edge Case-2

## TC-SEN-05 — Perception +2 within scent range
- Description: Locating undetected creature within scent range gets +2 circumstance bonus
- Suite: playwright/encounter
- Expected: perception_check.circumstance_bonus = 2 when target is undetected and distance ≤ effective_scent_range
- AC: Perception Bonus-1, Failure Modes-1

## TC-SEN-06 — Perception bonus does not apply outside scent range
- Description: No +2 bonus when undetected creature is beyond scent range
- Suite: playwright/encounter
- Expected: perception_check.circumstance_bonus = 0 when distance > effective_scent_range
- AC: Perception Bonus-2

## TC-SEN-07 — Imprecise localization (not precise)
- Description: Scent only approximates position; does not reveal exact square of invisible creature
- Suite: playwright/encounter
- Expected: scent detection returns "position approximate" status; not "position known"
- AC: Edge Case-1
