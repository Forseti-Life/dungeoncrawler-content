- Status: done
- Completed: 2026-04-12T16:33:38Z

# Suite Activation: dc-cr-gnome-heritage-sensate

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-12T13:45:31+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-gnome-heritage-sensate"`**  
   This links the test to the living requirements doc at `features/dc-cr-gnome-heritage-sensate/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-gnome-heritage-sensate-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-gnome-heritage-sensate",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-gnome-heritage-sensate"`**  
   Example:
   ```json
   {
     "id": "dc-cr-gnome-heritage-sensate-<route-slug>",
     "feature_id": "dc-cr-gnome-heritage-sensate",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-gnome-heritage-sensate",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-gnome-heritage-sensate

## Gap analysis reference
- DB sections: core/ch02 (Gnome Heritages)
- Depends on: dc-cr-gnome-ancestry ✓, dc-cr-darkvision (shared sense framework), encounter system (wind-direction model)

---

## Happy Path

### Imprecise Scent Sense
- [ ] `[NEW]` Sensate Gnome has imprecise scent with 30-foot base range.
- [ ] `[NEW]` Sense type is "imprecise" — the creature's position is approximated, not pinpointed.

### Wind Direction Modifier
- [ ] `[NEW]` Range is doubled (60 ft) when the creature is downwind from the character.
- [ ] `[NEW]` Range is halved (15 ft) when the creature is upwind from the character.
- [ ] `[NEW]` System models at minimum a binary wind state (upwind / downwind / neutral) per encounter.

### Perception Bonus vs. Undetected
- [ ] `[NEW]` +2 circumstance bonus to Perception checks specifically to locate an undetected creature within scent range.
- [ ] `[NEW]` Bonus does not apply to Perception checks outside scent range.

---

## Edge Cases
- [ ] `[NEW]` Imprecise scent does not grant the ability to precisely locate invisible creatures — only narrows position to a square.
- [ ] `[NEW]` If the encounter has no wind-direction model, treat range as base 30 ft (neutral; apply no modifier).

## Failure Modes
- [ ] `[TEST-ONLY]` Perception bonus is conditional on scent range — does not apply to all Perception checks.
- [ ] `[TEST-ONLY]` Wind halving applies upwind (not downwind); doubling applies downwind (not upwind).

## Security acceptance criteria
- Security AC exemption: game-mechanic sense; no new routes or user-facing input
- Agent: qa-dungeoncrawler
- Status: pending
