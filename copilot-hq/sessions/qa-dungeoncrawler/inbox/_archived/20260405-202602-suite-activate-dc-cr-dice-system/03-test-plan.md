# Test Plan: dc-cr-dice-system

**Owner:** qa-dungeoncrawler  
**Date:** 2026-03-27  
**Feature:** Dice System — NdX expression parser, `POST /dice/roll` API, roll logging, keep-highest/lowest  
**AC source:** `features/dc-cr-dice-system/01-acceptance-criteria.md`  
**Implementation notes:** not yet written (grooming phase; Dev notes TBD)  

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after module changes; surface regressions immediately.
- KB: none found for dice engine or NdX parser specifically. `CombatCalculator.php` referenced in AC as consumer of roll results — regression-test that consumer is unaffected at Stage 0.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` (unit) | PHPUnit unit (`tests/src/Unit/Service/NumberGenerationServiceTest.php`) | `rollPathfinderDie` validation, `rollExpression` parser, roll logging, edge cases, error handling |
| `module-test-suite` (functional) | PHPUnit functional (`tests/src/Functional/DiceRollControllerTest.php`) | `POST /dice/roll` endpoint — request/response shape, HTTP status codes |
| `role-url-audit` | `scripts/site-audit-run.sh` | ACL: anon vs. auth access to `POST /dice/roll` route |

> **ACL note:** The AC states dice rolls may be accessible to anonymous users at the roll level (auth-gated at session level). This needs PM/site-auth-policy confirmation before adding a `qa-permissions.json` rule. Flagged for Stage-0 clarification.

---

## Test cases

### TC-DS-01 — rollPathfinderDie returns integer in [1, sides] for all PF2E die types
- **AC:** `[EXTEND]` `rollPathfinderDie()` accepts d4, d6, d8, d10, d12, d20, d100 and returns integer in `[1, sides]`
- **Suite:** `module-test-suite` (unit) — `NumberGenerationServiceTest`
- **Test method:** `testRollPathfinderDieAllTypes()`
- **Approach:** For each valid die (d4, d6, d8, d10, d12, d20, d100): call `rollPathfinderDie($sides)` 20 times; assert all results are `>= 1` and `<= $sides`
- **Expected:** 100% of rolls within valid range; no exceptions thrown
- **Tags:** `[EXTEND]`, happy path

### TC-DS-02 — rollExpression parses basic NdX notation
- **AC:** `[NEW]` `rollExpression("NdX")` returns individual die results plus total
- **Suite:** `module-test-suite` (unit) — `NumberGenerationServiceTest`
- **Test method:** `testRollExpressionBasicNdX()`
- **Inputs:** `"4d6"`, `"2d8"`, `"1d20"`
- **Expected:** response contains `dice` array of length N; each element in `[1, X]`; `total` equals sum of `dice`
- **Tags:** `[NEW]`, happy path

### TC-DS-03 — rollExpression parses NdX+M modifier notation
- **AC:** `[NEW]` `rollExpression("1d20+5")` returns dice results, modifier, and total
- **Suite:** `module-test-suite` (unit) — `NumberGenerationServiceTest`
- **Test method:** `testRollExpressionWithModifier()`
- **Inputs:** `"1d20+5"`, `"2d6+3"`, `"1d8-2"`
- **Expected:** `modifier` field matches M; `total === sum(dice) + modifier`
- **Tags:** `[NEW]`, happy path

### TC-DS-04 — rollExpression handles d% notation (two d10s, 1–100)
- **AC:** `[NEW]` `d%` maps to two d10s (tens + ones) producing 1–100
- **Suite:** `module-test-suite` (unit) — `NumberGenerationServiceTest`
- **Test method:** `testRollExpressionPercentile()`
- **Input:** `"d%"`
- **Expected:** two dice returned (tens die: 0/10/20…90, ones die: 0–9); total in `[1, 100]`; matches existing `rollPercentile()` semantics
- **Tags:** `[NEW]`, happy path + edge case

### TC-DS-05 — POST /dice/roll returns correct JSON shape
- **AC:** `[NEW]` `POST /dice/roll` accepts `{ "expression": "NdX+M" }` and returns `{ "dice": [...], "modifier": M, "total": N }`
- **Suite:** `module-test-suite` (functional) — `DiceRollControllerTest`
- **Test method:** `testDiceRollEndpointResponseShape()`
- **Input:** `POST /dice/roll` with body `{ "expression": "2d6+3" }`
- **Expected:** HTTP 200; response JSON keys: `dice` (array), `modifier` (int), `total` (int); `total === sum(dice) + modifier`
- **Roles covered:** authenticated (baseline); anon TBD per policy clarification (TC-DS-15)
- **Tags:** `[NEW]`, happy path

### TC-DS-06 — Each roll is logged with timestamp, character_id, and roll_type
- **AC:** `[NEW]` Roll logging with timestamp, optional `character_id`, and `roll_type` context
- **Suite:** `module-test-suite` (unit) — `NumberGenerationServiceTest`
- **Test method:** `testRollLoggingCreatesAuditEntry()`
- **Setup:** Make a roll with `character_id = 42`, `roll_type = "attack"`
- **Expected:** Roll log record exists with correct `character_id`, `roll_type`, and non-null `timestamp`; log entry is insert-only (no update/delete)
- **Tags:** `[NEW]`, happy path

### TC-DS-07 — Roll log anonymous: character_id is null/omitted
- **AC:** Authenticated rolls log `character_id`; anonymous omit it
- **Suite:** `module-test-suite` (unit) — `NumberGenerationServiceTest`
- **Test method:** `testRollLogAnonymousOmitsCharacterId()`
- **Setup:** Make a roll without providing `character_id`
- **Expected:** Log entry created; `character_id` is null or absent
- **Tags:** happy path (logging)

### TC-DS-08 — Unsupported die type returns explicit error
- **AC:** `[EXTEND]` d7 (or any non-PF2E die) returns explicit error, not silent incorrect result
- **Suite:** `module-test-suite` (unit) — `NumberGenerationServiceTest`
- **Test method:** `testUnsupportedDieTypeThrowsError()`
- **Inputs:** d7, d3, d0, d13, d99
- **Expected:** exception thrown or error returned (not a silently wrong integer); error message identifies the invalid die type
- **Tags:** `[EXTEND]`, edge case

### TC-DS-09 — Expression with N=0 or N<0 returns error
- **AC:** `[NEW]` N=0 or N<0 in expression returns error
- **Suite:** `module-test-suite` (unit) — `NumberGenerationServiceTest`
- **Test method:** `testExpressionInvalidDiceCountReturnsError()`
- **Inputs:** `"0d6"`, `"-1d6"`, `"-2d20"`
- **Expected:** parse error returned; no roll executed; clear error message
- **Tags:** `[NEW]`, edge case

### TC-DS-10 — Modifier +0 is handled gracefully
- **AC:** `[NEW]` Expression with `+0` modifier handled same as no modifier
- **Suite:** `module-test-suite` (unit) — `NumberGenerationServiceTest`
- **Test method:** `testExpressionZeroModifierHandled()`
- **Input:** `"2d6+0"`
- **Expected:** no exception; `modifier === 0`; `total === sum(dice)`
- **Tags:** `[NEW]`, edge case

### TC-DS-11 — Keep-highest modifier (4d6kh3) returns kept subset and total
- **AC:** `[NEW]` `"4d6kh3"` returns kept 3 dice (highest) plus total
- **Suite:** `module-test-suite` (unit) — `NumberGenerationServiceTest`
- **Test method:** `testKeepHighestModifier()`
- **Input:** `"4d6kh3"`
- **Expected:** response contains 4 rolled dice values; `kept` array = 3 highest values; `total === sum(kept)`; no exception
- **Tags:** `[NEW]`, edge case

### TC-DS-12 — Keep-lowest modifier (4d6kl3) returns kept subset and total
- **AC:** `[NEW]` Keep-lowest variant
- **Suite:** `module-test-suite` (unit) — `NumberGenerationServiceTest`
- **Test method:** `testKeepLowestModifier()`
- **Input:** `"4d6kl3"`
- **Expected:** `kept` array = 3 lowest values; `total === sum(kept)`
- **Tags:** `[NEW]`, edge case

### TC-DS-13 — POST /dice/roll with invalid expression returns HTTP 400
- **AC:** `[TEST-ONLY]` Invalid expression string returns HTTP 400 with human-readable message
- **Suite:** `module-test-suite` (functional) — `DiceRollControllerTest`
- **Test method:** `testDiceRollInvalidExpressionReturns400()`
- **Inputs:** `"abc"`, `""`, `"4d"`, `"dd6"`, `null`
- **Expected:** HTTP 400; response body contains `error` or `message` field with parse error description
- **Tags:** `[TEST-ONLY]`, failure mode

### TC-DS-14 — Roll log entries are immutable (insert-only)
- **AC:** Roll log entries are insert-only; no update/delete of historical rolls
- **Suite:** `module-test-suite` (unit) — `NumberGenerationServiceTest`
- **Test method:** `testRollLogIsInsertOnly()`
- **Setup:** Create a roll log entry; attempt to update or delete it via service layer
- **Expected:** service throws exception or returns error on update/delete attempt; entry unchanged in DB
- **Tags:** data integrity

### TC-DS-15 — ACL: POST /dice/roll anon vs. auth access
- **AC:** Anonymous accessible in game context (session-level auth-gate); authenticated logs `character_id`
- **Suite:** `role-url-audit`
- **Rule type:** `qa-permissions.json` entry — TBD pending PM site-auth-policy confirmation
- **Roles covered:** anonymous, authenticated
- **Note to PM:** AC says dice rolls may be accessible to anonymous users with auth at the session level. Need PM to confirm: is `POST /dice/roll` open to anonymous (HTTP 200) or always auth-required (HTTP 401/403)? Add correct `qa-permissions.json` rule at Stage 0 once confirmed.
- **Tags:** permissions/access control — **Stage-0 confirmation required**

### TC-DS-16 — Rollback: roll log table can be dropped and recreated
- **AC:** Roll log table has no foreign key dependencies in this release; safe to drop/recreate
- **Suite:** `module-test-suite` (functional)
- **Test method:** `testRollLogTableRollback()`
- **Setup:** Uninstall module (or run schema drop); verify existing character and game nodes are intact
- **Expected:** character and game data unaffected; roll log table absent or empty after rollback
- **Tags:** data integrity, rollback

### TC-DS-17 — CombatCalculator regression: existing degree-of-success logic unaffected
- **AC:** KB note — `CombatCalculator.php` consumes roll results; must not regress
- **Suite:** `module-test-suite` (unit/functional)
- **Test method:** `testCombatCalculatorUnaffectedByDiceSystemChanges()`
- **Setup:** Run existing `CombatCalculator` tests (if present) after dice system changes deployed
- **Expected:** all existing `CombatCalculator` tests still PASS; no interface changes to `rollPathfinderDie()` that break callers
- **Tags:** regression risk area
- **Note:** If `CombatCalculatorTest` does not exist yet, flag to Dev to add a smoke test before Stage 0.

---

## AC items that cannot be fully expressed as automation

| AC item | Limitation | Note to PM |
|---|---|---|
| TC-DS-15 ACL for `POST /dice/roll` | AC says anon accessible "at session level" but session auth policy unclear | Need PM to confirm: is this route anon-open or always auth-required? |
| TC-DS-11/12 keep-highest/lowest response shape | Exact JSON response key names for `kept` array TBD by Dev impl | Confirm field names (`kept`, `dropped`, `all_dice`?) with Dev in implementation notes |
| TC-DS-06 roll_type enum | AC lists `attack`, `skill`, `damage`, `save`, `initiative` — is this a closed enum or free-text? | Confirm valid `roll_type` values with Dev; affects validation test |
| TC-DS-17 CombatCalculator regression | `CombatCalculatorTest.php` may not exist yet | Dev to confirm or add smoke test for caller regression |

---

## Pre-activation checklist (Stage 0, do not activate now)

- [ ] Get PM confirmation on `POST /dice/roll` anon/auth ACL policy (TC-DS-15)
- [ ] Confirm keep-highest/lowest JSON response field names with Dev (TC-DS-11/12)
- [ ] Confirm `roll_type` enum or free-text with Dev (TC-DS-06)
- [ ] Confirm `CombatCalculatorTest` coverage or request Dev add smoke test (TC-DS-17)
- [ ] Add `module-test-suite` test class `NumberGenerationServiceTest` extensions (TC-DS-01 through TC-DS-14, TC-DS-16)
- [ ] Add `DiceRollControllerTest.php` (TC-DS-05, TC-DS-13)
- [ ] Add `qa-permissions.json` rule for `POST /dice/roll` once ACL policy confirmed (TC-DS-15)
- [ ] Validate suite: `python3 scripts/qa-suite-validate.py`
- [ ] Run full `role-url-audit` baseline (0 violations) after Dev deploys to local
