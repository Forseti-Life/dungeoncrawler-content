# Suite Activation: dc-cr-difficulty-class

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T18:12:10+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-difficulty-class"`**  
   This links the test to the living requirements doc at `features/dc-cr-difficulty-class/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-difficulty-class-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-difficulty-class",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-difficulty-class"`**  
   Example:
   ```json
   {
     "id": "dc-cr-difficulty-class-<route-slug>",
     "feature_id": "dc-cr-difficulty-class",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-difficulty-class",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-difficulty-class

**Owner:** qa-dungeoncrawler  
**Date:** 2026-03-27  
**Feature:** Difficulty Class — `determineDegreOfSuccess`, Simple DC table, task DC guidelines, `POST /rules/check`  
**AC source:** `features/dc-cr-difficulty-class/01-acceptance-criteria.md`  
**Implementation notes:** not yet written (grooming phase; Dev notes TBD)  

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after module changes; surface regressions immediately.
- KB: none found for DC/degree-of-success specifically. AC notes `CombatCalculator.php` references PF2E Core Rulebook p. 445 — same pattern applies here.
- Regression dependency: `calculateMultipleAttackPenalty()` in `CombatCalculator.php` is already fully implemented and must not regress.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` (unit) | PHPUnit unit (`tests/src/Unit/Service/CombatCalculatorTest.php`) | `determineDegreOfSuccess` logic matrix, Simple DC table, task DC guidelines, edge cases, error handling |
| `module-test-suite` (functional) | PHPUnit functional (`tests/src/Functional/RulesCheckControllerTest.php`) | `POST /rules/check` endpoint — request/response shape, HTTP status codes |
| `role-url-audit` | `scripts/site-audit-run.sh` | ACL: anon vs. auth access to `POST /rules/check`; confirm same policy as dice rolls (TC-DC-14) |

> **ACL note:** AC says `POST /rules/check` is accessible to anonymous users "in game context" — same policy stated for dice rolls in `dc-cr-dice-system`. If that policy is confirmed anon-open, apply the same `qa-permissions.json` rule here at Stage 0. Flag for PM confirmation.

---

## Test cases

### TC-DC-01 — determineDegreOfSuccess: roll ≥ DC+10 → critical_success
- **AC:** `[EXTEND]` Degree logic — roll ≥ DC+10 = `critical_success`
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testDetermineDegreOfSuccessCriticalSuccess()`
- **Inputs:** roll=25, dc=15 (25 ≥ 15+10); roll=20, dc=10; roll=31, dc=20
- **Expected:** returns `"critical_success"` for all inputs
- **Tags:** `[EXTEND]`, happy path

### TC-DC-02 — determineDegreOfSuccess: roll ≥ DC (but < DC+10) → success
- **AC:** `[EXTEND]` roll ≥ DC = `success`
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testDetermineDegreOfSuccessSuccess()`
- **Inputs:** roll=15, dc=15; roll=20, dc=15; roll=24, dc=15
- **Expected:** returns `"success"`
- **Tags:** `[EXTEND]`, happy path

### TC-DC-03 — determineDegreOfSuccess: roll ≤ DC−10 → critical_failure
- **AC:** `[EXTEND]` roll ≤ DC−10 = `critical_failure`
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testDetermineDegreOfSuccessCriticalFailure()`
- **Inputs:** roll=5, dc=15 (5 ≤ 15-10); roll=1, dc=15; roll=10, dc=20
- **Expected:** returns `"critical_failure"`
- **Tags:** `[EXTEND]`, happy path

### TC-DC-04 — determineDegreOfSuccess: DC−9 ≤ roll < DC → failure
- **AC:** `[EXTEND]` otherwise = `failure`
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testDetermineDegreOfSuccessFailure()`
- **Inputs:** roll=10, dc=15; roll=14, dc=15; roll=6, dc=15 (boundary: DC-9=6)
- **Expected:** returns `"failure"`
- **Tags:** `[EXTEND]`, happy path

### TC-DC-05 — Natural 20 bumps degree one step up
- **AC:** `[EXTEND]` Natural 20 bumps one degree up
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testNaturalTwentyBumpsDegreeUp()`
- **Inputs (before bump → after bump):**
  - `failure` + nat20 → `success`
  - `success` + nat20 → `critical_success`
  - `critical_failure` + nat20 → `failure`
- **Expected:** degree bumped one step in each case
- **Tags:** `[EXTEND]`, happy path

### TC-DC-06 — Natural 1 bumps degree one step down
- **AC:** `[EXTEND]` Natural 1 bumps one degree down
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testNaturalOneBumpsDegreeDown()`
- **Inputs:**
  - `success` + nat1 → `failure`
  - `critical_success` + nat1 → `success`
  - `failure` + nat1 → `critical_failure`
- **Expected:** degree bumped one step down in each case
- **Tags:** `[EXTEND]`, happy path

### TC-DC-07 — Natural 20 on critical_success stays at critical_success
- **AC:** `[EXTEND]` No bump beyond max
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testNaturalTwentyAtMaxStaysCriticalSuccess()`
- **Setup:** roll ≥ DC+10 AND natural_twenty=true
- **Expected:** returns `"critical_success"` (not higher)
- **Tags:** `[EXTEND]`, edge case

### TC-DC-08 — Natural 1 on critical_failure stays at critical_failure
- **AC:** `[EXTEND]` No bump below min
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testNaturalOneAtMinStaysCriticalFailure()`
- **Setup:** roll ≤ DC−10 AND natural_one=true
- **Expected:** returns `"critical_failure"` (not lower)
- **Tags:** `[EXTEND]`, edge case

### TC-DC-09 — getSimpleDC returns correct DC for levels 1–20
- **AC:** `[NEW]` `getSimpleDC(int $level)` returns PF2E Core Rulebook Simple DC per level
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testGetSimpleDCAllLevels()`
- **Inputs:** levels 1–20
- **Expected:** each returns canonical PF2E Simple DC value (spot-check: level 1 = DC 15, level 10 = DC 27, level 20 = DC 40 per CRB table)
- **Note:** Exact values to be confirmed against `CharacterManager` or CRB constants in Dev impl notes
- **Tags:** `[NEW]`, happy path

### TC-DC-10 — getSimpleDC level 0 or negative returns error
- **AC:** `[NEW]` Level 0 or negative returns error
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testGetSimpleDCInvalidLevelReturnsError()`
- **Inputs:** level=0, level=-1, level=-5
- **Expected:** exception thrown or error returned; clear message; no DC value returned
- **Tags:** `[NEW]`, edge case

### TC-DC-11 — getSimpleDC level > 20 returns level-20 DC (capped)
- **AC:** `[NEW]` Levels > 20 return the level-20 DC (capped)
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testGetSimpleDCCapAtLevelTwenty()`
- **Inputs:** level=21, level=25, level=100
- **Expected:** all return same value as `getSimpleDC(20)` (no error, capped)
- **Tags:** `[NEW]`, edge case

### TC-DC-12 — getTaskDC returns correct DC for all difficulty tiers
- **AC:** `[NEW]` `getTaskDC(string $difficulty)` returns DC for: `trivial`, `low`, `moderate`, `high`, `extreme`, `incredible`
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testGetTaskDCAllTiers()`
- **Inputs:** all 6 tier strings
- **Expected:** each returns a valid positive integer DC; values are monotonically increasing (trivial < low < moderate < high < extreme < incredible)
- **Note:** Exact DC values to be confirmed from Dev impl notes / CRB reference
- **Tags:** `[NEW]`, happy path

### TC-DC-13 — getTaskDC unknown difficulty returns explicit error
- **AC:** `[NEW]` Unknown difficulty string returns explicit error
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testGetTaskDCUnknownDifficultyReturnsError()`
- **Inputs:** `"easy"`, `"hard"`, `""`, `null`, `"MODERATE"` (case mismatch)
- **Expected:** exception thrown or error returned; clear message listing valid tier names
- **Tags:** `[NEW]`, edge case

### TC-DC-14 — POST /rules/check returns correct degree JSON
- **AC:** `[NEW]` `POST /rules/check` accepts roll/dc/natural_twenty/natural_one; returns `{ "degree": "..." }`
- **Suite:** `module-test-suite` (functional) — `RulesCheckControllerTest`
- **Test method:** `testRulesCheckEndpointResponseShape()`
- **Inputs:** `{ "roll": 20, "dc": 15, "natural_twenty": false, "natural_one": false }`
- **Expected:** HTTP 200; response JSON has key `degree` with value one of: `critical_success`, `success`, `failure`, `critical_failure`; matches expected degree for given inputs
- **Roles covered:** authenticated (baseline); anon pending policy confirmation
- **Tags:** `[NEW]`, happy path

### TC-DC-15 — POST /rules/check with invalid input returns HTTP 400
- **AC:** `[NEW]` Invalid input (non-integer roll or DC) rejected with explicit feedback
- **Suite:** `module-test-suite` (functional) — `RulesCheckControllerTest`
- **Test method:** `testRulesCheckInvalidInputReturns400()`
- **Inputs:** roll=`"abc"`, dc=`null`, roll missing entirely, dc=-999
- **Expected:** HTTP 400; response body contains `error` or `message` field with clear description
- **Tags:** `[NEW]`, failure mode

### TC-DC-16 — ACL: POST /rules/check anon vs. auth access
- **AC:** Anonymous accessible in game context (same policy as dice rolls)
- **Suite:** `role-url-audit`
- **Rule type:** `qa-permissions.json` entry — TBD pending PM confirmation of anon-open policy
- **Note to PM:** AC states same anon-accessible policy as dice rolls. If confirmed anon-open, add same `qa-permissions.json` rule as `POST /dice/roll` at Stage 0. Coordinate with `dc-cr-dice-system` ACL decision (TC-DS-15).
- **Tags:** permissions/access control — **Stage-0 confirmation required**

### TC-DC-17 — calculateMultipleAttackPenalty regression
- **AC:** `calculateMultipleAttackPenalty()` already fully implemented; must not regress
- **Suite:** `module-test-suite` (unit) — `CombatCalculatorTest`
- **Test method:** `testMultipleAttackPenaltyRegression()` (existing or verify exists)
- **Setup:** Confirm existing test coverage; if absent, add smoke test before Stage 0
- **Expected:** all existing `calculateMultipleAttackPenalty` test cases still PASS after DC changes
- **Tags:** regression risk area

---

## AC items that cannot be fully expressed as automation

| AC item | Limitation | Note to PM |
|---|---|---|
| TC-DC-09 Simple DC exact values | PF2E CRB table values not included in AC | Dev to include exact table (levels 1–20 → DC) in impl notes; QA will spot-check |
| TC-DC-12 Task DC exact values | DC values per tier not included in AC | Dev to include exact tier→DC mapping in impl notes |
| TC-DC-16 ACL policy | "Same as dice rolls" — anon policy for dice rolls still unconfirmed (see `dc-cr-dice-system` TC-DS-15) | One PM decision covers both features; coordinate ACL confirmation together |
| TC-DC-13 case sensitivity | AC doesn't specify if `difficulty` is case-sensitive | Dev to confirm; if case-insensitive, remove `"MODERATE"` error expectation from TC-DC-13 |

---

## Pre-activation checklist (Stage 0, do not activate now)

- [ ] Get Dev impl notes with exact Simple DC table values (levels 1–20) for TC-DC-09
- [ ] Get Dev impl notes with exact task DC tier values for TC-DC-12
- [ ] Confirm case-sensitivity of `difficulty` string for TC-DC-13
- [ ] Get PM confirmation on `POST /rules/check` anon ACL policy (TC-DC-16) — coordinate with `dc-cr-dice-system` decision
- [ ] Add `module-test-suite` extensions to `CombatCalculatorTest` (TC-DC-01 through TC-DC-13, TC-DC-17)
- [ ] Add `RulesCheckControllerTest.php` (TC-DC-14, TC-DC-15)
- [ ] Add `qa-permissions.json` rule for `POST /rules/check` once ACL policy confirmed (TC-DC-16)
- [ ] Validate suite: `python3 scripts/qa-suite-validate.py`
- [ ] Run full `role-url-audit` baseline (0 violations) after Dev deploys to local

### Acceptance criteria (reference)

# Acceptance Criteria (PM-owned)
# Feature: dc-cr-difficulty-class

## Gap analysis reference

Gap analysis performed against `dungeoncrawler_content/src/Service/CombatCalculator.php`.

Coverage findings:
- `calculateDegreeOfSuccess(int $roll, int $dc, bool $naturalTwenty, bool $naturalOne)` — degree-of-success enum — **Partial** (method referenced in docblock; impl may be stubbed with TODO)
- `calculateMultipleAttackPenalty()` — **Full** (implemented correctly per PF2E spec)
- Simple DC table by level (1–20) — **None** (no lookup table found)
- Task DC guidelines (Trivial/Low/Moderate/High/Extreme/Incredible) — **None**
- Fixed DCs for specific tasks (e.g., DC 15 for Recall Knowledge on common topics) — **None**
- Non-combat skill check resolution (input roll+DC → degree) — **Partial** (CombatCalculator exists but scoped to combat)

Feature type: **enhancement** (complete stubbed degree-of-success; add Simple DC table and task DC guidelines as data)

## Happy Path
- [ ] `[EXTEND]` `determineDegreOfSuccess(int $rollTotal, int $dc, bool $naturalTwenty = false, bool $naturalOne = false): string` returns one of: `critical_success`, `success`, `failure`, `critical_failure`.
- [ ] `[EXTEND]` Degree logic: roll ≥ DC+10 = `critical_success`; roll ≥ DC = `success`; roll ≤ DC−10 = `critical_failure`; otherwise `failure`. Natural 20 bumps one degree up; natural 1 bumps one degree down.
- [ ] `[NEW]` A `getSimpleDC(int $level): int` method returns the Simple DC for a given level (1–20) per PF2E Core Rulebook table.
- [ ] `[NEW]` A `getTaskDC(string $difficulty): int` method returns a DC for named difficulty tiers: `trivial`, `low`, `moderate`, `high`, `extreme`, `incredible`.
- [ ] `[NEW]` A `POST /rules/check` API endpoint accepts `{ "roll": N, "dc": N, "natural_twenty": bool, "natural_one": bool }` and returns `{ "degree": "..." }`.

## Edge Cases
- [ ] `[EXTEND]` Natural 20 on a result already at `critical_success` stays at `critical_success` (no bump beyond max).
- [ ] `[EXTEND]` Natural 1 on a result already at `critical_failure` stays at `critical_failure` (no bump below min).
- [ ] `[NEW]` `getSimpleDC` for level 0 or negative returns an error; for levels > 20, returns the level-20 DC (capped).
- [ ] `[NEW]` Unknown `difficulty` string to `getTaskDC` returns an explicit error.

## Failure Modes
- [ ] `[TEST-ONLY]` Error messages are clear and actionable.
- [ ] `[NEW]` Invalid input (non-integer roll or DC) is rejected with explicit feedback.

## Permissions / Access Control
- [ ] Anonymous user behavior: rules check endpoint is accessible to anonymous users in game context (same as dice rolls).
- [ ] Authenticated user behavior: no additional behavior.
- [ ] Admin behavior: no special admin surface required.

## Data Integrity
- [ ] Simple DC table and task DC data are hardcoded constants (no DB storage required).
- [ ] Rollback path: no DB migrations; this is a pure logic/data change.

## Knowledgebase check
- Related lessons/playbooks: none found; `CombatCalculator.php` already references PF2E Core Rulebook p. 445 for degree-of-success rules — follow the same pattern.

## Test path guidance (for QA)
| Requirement | Test path |
|---|---|
| `determineDegreOfSuccess` all combinations | `tests/src/Unit/Service/CombatCalculatorTest.php` |
| Simple DC table | `tests/src/Unit/Service/CombatCalculatorTest.php` (extend) |
| Task DC guidelines | `tests/src/Unit/Service/CombatCalculatorTest.php` (extend) |
| `POST /rules/check` endpoint | `tests/src/Functional/RulesCheckControllerTest.php` (new) |
- Agent: qa-dungeoncrawler
- Status: pending
