# Suite Activation: dc-cr-halfling-resolve

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-20T19:55:17+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-halfling-resolve"`**  
   This links the test to the living requirements doc at `features/dc-cr-halfling-resolve/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-halfling-resolve-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-halfling-resolve",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-halfling-resolve"`**  
   Example:
   ```json
   {
     "id": "dc-cr-halfling-resolve-<route-slug>",
     "feature_id": "dc-cr-halfling-resolve",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-halfling-resolve",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-halfling-resolve

- Feature: Halfling Resolve (Halfling Feat 9)
- Module: dungeoncrawler_content
- Agent: qa-dungeoncrawler
- Target release: 20260412-dungeoncrawler-release-s
- Created: 2026-04-20

---

## Test Cases

### Feat Availability

| ID | Scenario | Expected Result | Result |
|----|----------|----------------|--------|
| TC-01 | A halfling character views feat selection at level 9. | Halfling Resolve appears as a selectable feat option. | PASS/FAIL |
| TC-02 | A halfling character selects Halfling Resolve with no prerequisite feat. | Feat is granted; no additional prerequisite is required beyond halfling ancestry. | PASS/FAIL |

---

### Emotion Saving Throw Upgrade

| ID | Scenario | Expected Result | Result |
|----|----------|----------------|--------|
| TC-03 | A halfling with Halfling Resolve rolls a success on a saving throw against an emotion effect. | Result is upgraded to a critical success. | PASS/FAIL |
| TC-04 | A halfling with Halfling Resolve rolls a failure on a saving throw against an emotion effect. | Result remains failure; no upgrade occurs. | PASS/FAIL |
| TC-05 | A halfling with Halfling Resolve rolls a critical failure on a saving throw against an emotion effect (no Gutsy heritage). | Result remains critical failure; no upgrade or mitigation occurs. | PASS/FAIL |
| TC-06 | A halfling with Halfling Resolve already rolls a critical success on an emotion saving throw. | Result remains critical success; no change. | PASS/FAIL |
| TC-07 | A non-halfling character (e.g., Human) rolls a success on a saving throw against an emotion effect. | No upgrade; result stays success. | PASS/FAIL |
| TC-08 | A halfling without Halfling Resolve rolls a success on a saving throw against an emotion effect. | No upgrade; result stays success. | PASS/FAIL |

---

### Gutsy Halfling Critical Failure Mitigation

| ID | Scenario | Expected Result | Result |
|----|----------|----------------|--------|
| TC-09 | A halfling with Halfling Resolve AND Gutsy Halfling heritage rolls a critical failure on an emotion saving throw. | Result is treated as a failure instead of critical failure. | PASS/FAIL |
| TC-10 | A halfling with Halfling Resolve but WITHOUT Gutsy Halfling heritage rolls a critical failure on an emotion saving throw. | Result remains critical failure; no mitigation. | PASS/FAIL |

---

### Edge Cases

| ID | Scenario | Expected Result | Result |
|----|----------|----------------|--------|
| TC-11 | A halfling with Halfling Resolve AND Gutsy Halfling rolls a success on an emotion saving throw. | Both effects apply: success upgrades to critical success; Gutsy mitigation also active but irrelevant for this outcome. | PASS/FAIL |
| TC-12 | A halfling with Halfling Resolve rolls a success on a non-emotion saving throw (e.g., poison, disease). | No upgrade; saving throw result unchanged. | PASS/FAIL |

---

### Failure Modes

| ID | Scenario | Expected Result | Result |
|----|----------|----------------|--------|
| TC-13 | A non-halfling character attempts to select Halfling Resolve. | Selection is blocked; feat restricted to halfling ancestry. | PASS/FAIL |
| TC-14 | A halfling with Halfling Resolve but no Gutsy Halfling heritage; Gutsy mitigation is checked at resolution. | Gutsy mitigation does not fire; critical failure on emotion save remains critical failure. | PASS/FAIL |

---

## Coverage Map

| AC Item | Test Case(s) |
|---------|-------------|
| [NEW] Halfling Resolve appears as selectable Halfling Feat 9 | TC-01 |
| [NEW] No prerequisite feat required beyond halfling ancestry | TC-02 |
| [NEW] Success on emotion save upgraded to critical success | TC-03 |
| [NEW] Other outcomes (failure, crit fail, crit success) not altered | TC-04, TC-05, TC-06 |
| [NEW] Non-halfling / halfling without feat receive no upgrade | TC-07, TC-08 |
| [NEW] Gutsy + Resolve: critical failure on emotion save → failure | TC-09 |
| [NEW] Resolve without Gutsy: no critical-failure mitigation | TC-10 |
| [NEW] Both effects apply simultaneously when both conditions met | TC-11 |
| [NEW] Non-emotion saves are unaffected | TC-12 |
| [TEST-ONLY] Non-halfling cannot select feat | TC-13 |
| [TEST-ONLY] Gutsy mitigation absent without Gutsy heritage | TC-14 |

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-halfling-resolve

## Gap analysis reference
- DB sections: PF2E Core Rulebook (Fourth Printing), Halfling Ancestry Feats (Feat 9)
- Depends on: dc-cr-halfling-ancestry, dc-cr-ancestry-system, dc-cr-halfling-heritage-gutsy

---

## Happy Path

### Feat availability
- [ ] `[NEW]` Halfling Resolve appears as a selectable Halfling Feat 9 when the character is a halfling.
- [ ] `[NEW]` No prerequisite feat is required beyond halfling ancestry.

### Emotion saving throw upgrade (all halflings with feat)
- [ ] `[NEW]` When a halfling with Halfling Resolve rolls a success on a saving throw against an emotion effect, the result is upgraded to a critical success.
- [ ] `[NEW]` Other saving throw outcomes (failure, critical failure, critical success) are not altered by this upgrade rule.
- [ ] `[NEW]` Non-halfling characters and halflings without this feat receive no saving throw upgrade (no regression).

### Gutsy Halfling critical failure mitigation
- [ ] `[NEW]` When a halfling with Halfling Resolve AND the Gutsy Halfling heritage rolls a critical failure on a saving throw against an emotion effect, the result is treated as a failure instead.
- [ ] `[NEW]` Halflings with Halfling Resolve but WITHOUT Gutsy Halfling do not receive the critical-failure-to-failure mitigation.

---

## Edge Cases
- [ ] `[NEW]` Both Halfling Resolve effects (success upgrade and Gutsy critical-failure mitigation) apply simultaneously when both conditions are met.
- [ ] `[NEW]` Emotion-effect identification is correctly scoped — non-emotion saving throws are not affected.

## Failure Modes
- [ ] `[TEST-ONLY]` Attempting to select Halfling Resolve on a non-halfling character is blocked.
- [ ] `[TEST-ONLY]` The Gutsy mitigation does not fire when the Gutsy Halfling heritage is absent.

## Security acceptance criteria
- Security AC exemption: saving throw mechanic logic only; no new route surface beyond existing character/combat flows.
- Status: pending
