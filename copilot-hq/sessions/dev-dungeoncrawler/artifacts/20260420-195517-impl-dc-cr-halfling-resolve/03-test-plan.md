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
