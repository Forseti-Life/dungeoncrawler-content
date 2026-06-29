# Suite Activation: dc-cr-halfling-heritage-gutsy

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T20:35:41+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-halfling-heritage-gutsy"`**  
   This links the test to the living requirements doc at `features/dc-cr-halfling-heritage-gutsy/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-halfling-heritage-gutsy-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-halfling-heritage-gutsy",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-halfling-heritage-gutsy"`**  
   Example:
   ```json
   {
     "id": "dc-cr-halfling-heritage-gutsy-<route-slug>",
     "feature_id": "dc-cr-halfling-heritage-gutsy",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-halfling-heritage-gutsy",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-halfling-heritage-gutsy

## Coverage summary
- AC items: 6 (2 happy path, 2 edge cases, 2 failure modes)
- Test cases: 4 (TC-HGU-01-04)
- Suites: playwright (character creation, encounter/save resolution)
- Security: AC exemption granted (existing save-resolution routes only)

---

## TC-HGU-01 — Heritage selectable
- Description: Gutsy Halfling appears in the halfling heritage picker.
- Suite: playwright/character-creation
- Expected: heritage list includes Gutsy Halfling
- AC: Heritage selection

## TC-HGU-02 — Success upgrades on emotion save
- Description: Character succeeds on a save against an emotion effect.
- Suite: playwright/encounter
- Expected: save result upgraded from success to critical success
- AC: Emotion save upgrade, Edge Cases-1

## TC-HGU-03 — Failure does not upgrade
- Description: Character fails the save against an emotion effect.
- Suite: playwright/encounter
- Expected: result remains failure
- AC: Failure Modes-1

## TC-HGU-04 — Non-emotion effect unaffected
- Description: Character succeeds on a non-emotion saving throw.
- Suite: playwright/encounter
- Expected: result remains ordinary success
- AC: Edge Cases-2, Failure Modes-2

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-halfling-heritage-gutsy

## Gap analysis reference
- DB sections: core/ch02 (Halfling heritages)
- Depends on: dc-cr-halfling-ancestry, dc-cr-heritage-system

---

## Happy Path

### Heritage selection
- [ ] `[NEW]` Gutsy Halfling is selectable as a halfling heritage at character creation.

### Emotion save upgrade
- [ ] `[NEW]` When a Gutsy Halfling rolls a success on a saving throw against an emotion effect, the result upgrades to a critical success.

---

## Edge Cases
- [ ] `[NEW]` The upgrade applies only to effects with the `emotion` trait.
- [ ] `[NEW]` A critical success remains a critical success; the heritage does not create a new higher result tier.

## Failure Modes
- [ ] `[TEST-ONLY]` A failed or critically failed save is not improved by this heritage.
- [ ] `[TEST-ONLY]` Non-emotion effects resolve with normal save outcomes.

## Security acceptance criteria
- Security AC exemption: passive heritage resolution only; no new route surface
- Agent: qa-dungeoncrawler
- Status: pending
