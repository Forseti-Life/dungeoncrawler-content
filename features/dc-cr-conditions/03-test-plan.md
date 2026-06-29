# Test Plan: dc-cr-conditions

**Owner:** qa-dungeoncrawler  
**Date:** 2026-03-28  
**Feature:** Conditions System — condition catalog, tick/decrement, dying/recovery rules, restriction enforcement, permissions  
**AC source:** `features/dc-cr-conditions/01-acceptance-criteria.md`  
**Implementation notes:** `features/dc-cr-conditions/02-implementation-notes.md`  

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit immediately after module changes; surface regressions quickly.
- KB: no prior lessons found for conditions system specifically; reference PF2E Core Rulebook Conditions Appendix for the full catalog.

## Gap analysis summary (from AC)
- DB layer (`applyCondition`, `removeCondition`, `getCurrentRound`): **Full** — tests verify existing behavior holds
- Valued-condition enforcement on stat calculations: **Partial** — test plan covers this gap
- Full condition catalog (~40 conditions): **Partial** — tests cover catalog existence and structure; full-catalog accuracy requires PM-signed-off data
- End-of-turn tick (`tickConditions`): **None** — new; tests cover the new method
- Dying/recovery rules (`processDying`): **None** — new; tests cover the new method
- `RulesEngine::checkConditionRestrictions()`: **None** — TODO stub; tests cover the implementation

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit unit (`./vendor/bin/phpunit tests/src/Unit/Service/`) | All ConditionManager and RulesEngine business logic |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: condition-apply endpoint auth-required, condition-state readable by all |

> **Note:** Conditions are service-layer logic (PHP). No Playwright suite needed at this scope. URL access-control tests are limited because most condition mutations are encounter-context operations (parameterized paths) — those are `ignore` in route audit; permissions are validated via unit tests.

---

## Test cases

### TC-COND-01 — Condition catalog defines all required fields per entry
- **AC:** `[NEW]` Catalog defines: name, is_valued (bool), max_value, effects (stat modifiers, action restrictions), end_trigger
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testCatalogEntryHasRequiredFields()`
- **Setup:** Load condition catalog constant/config; inspect a known entry (e.g., `frightened`)
- **Expected:** Entry contains `name`, `is_valued=true`, `max_value=4`, `effects`, `end_trigger=end_of_turn`; no required fields missing
- **Roles:** n/a (structural check)

### TC-COND-02 — Condition catalog includes all core PF2E condition types
- **AC:** `[NEW]` All ~40 PF2E conditions defined in catalog
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testCatalogContainsCoreConditions()`
- **Setup:** Load catalog; check for presence of key conditions: blinded, clumsy, confused, dazzled, deafened, doomed, drained, dying, enfeebled, fascinated, fatigued, fleeing, frightened, grabbed, immobilized, off-guard, paralyzed, petrified, prone, quickened, restrained, sickened, slowed, stunned, stupefied, unconscious
- **Expected:** All listed conditions present; count ≥ 26 core entries
- **Roles:** n/a (data check)

### TC-COND-03 — applyCondition validates condition type against catalog
- **AC:** `[EXTEND]` `applyCondition()` validates condition type exists in catalog before inserting
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testApplyConditionValidatesTypeInCatalog()`
- **Setup:** Call `applyCondition()` with a valid type (`frightened`, value=2); verify DB insert occurs
- **Expected:** No exception; condition row created; `type=frightened`, `value=2`
- **Roles:** n/a (service layer)

### TC-COND-04 — applyCondition rejects unknown condition type
- **AC:** `[NEW]` Applying an unknown condition type returns an explicit error
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testApplyUnknownConditionTypeReturnsError()`
- **Setup:** Call `applyCondition()` with `type=nonexistent_condition`
- **Expected:** Exception or error response containing "Unknown condition type"; no DB insert
- **Roles:** n/a (service layer)

### TC-COND-05 — tickConditions decrements valued condition by 1 at end of turn
- **AC:** `[NEW]` `tickConditions()` decrements valued conditions by 1 at end of participant's turn
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testTickConditionsDecrementsValuedCondition()`
- **Setup:** Apply `frightened 2` to participant; call `tickConditions($participant_id, $encounter_id)`
- **Expected:** Condition value decremented to 1; row updated, not removed
- **Roles:** n/a (service layer)

### TC-COND-06 — tickConditions removes valued condition when decremented to 0
- **AC:** `[NEW]` `tickConditions()` removes condition when value reaches 0 (frightened 1 → removed)
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testTickConditionsRemovesAtZero()`
- **Setup:** Apply `frightened 1` to participant; call `tickConditions()`
- **Expected:** Condition soft-deleted (`removed_at_round` set); no active frightened condition remains
- **Roles:** n/a (service layer)

### TC-COND-07 — tickConditions does not decrement non-valued conditions
- **AC:** `[NEW]` Non-valued conditions (e.g., blinded) are not affected by `tickConditions()`
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testTickConditionsIgnoresNonValuedConditions()`
- **Setup:** Apply non-valued `blinded` condition; call `tickConditions()`
- **Expected:** Blinded condition remains active; no soft-delete, no value change
- **Roles:** n/a (service layer)

### TC-COND-08 — processDying: success reduces dying value by 1
- **AC:** `[NEW]` At start of turn: success on flat DC 10 reduces dying by 1
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testProcessDyingSuccessDecreasesByOne()`
- **Setup:** Apply `dying 2`; mock flat check roll to return success (11–19 range); call `processDying()`
- **Expected:** `dying` value decremented to 1; participant still in encounter
- **Roles:** n/a (service layer)

### TC-COND-09 — processDying: critical success reduces dying by 2 and removes condition
- **AC:** `[NEW]` Critical success reduces dying by 2 and removes dying condition
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testProcessDyingCriticalSuccessRemovesDying()`
- **Setup:** Apply `dying 2`; mock roll to return critical success (≥20 + 10); call `processDying()`
- **Expected:** `dying` condition removed; participant active
- **Roles:** n/a (service layer)

### TC-COND-10 — processDying: failure increases dying value by 1
- **AC:** `[NEW]` Failure increases dying by 1
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testProcessDyingFailureIncreasesByOne()`
- **Setup:** Apply `dying 1`; mock roll to return failure (≤9); call `processDying()`
- **Expected:** `dying` value incremented to 2; participant still in encounter
- **Roles:** n/a (service layer)

### TC-COND-11 — processDying: critical failure increases dying by 2
- **AC:** `[NEW]` Critical failure increases dying by 2
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testProcessDyingCriticalFailureIncreasesByTwo()`
- **Setup:** Apply `dying 1`; mock roll to return critical failure (≤1 or ≤0); call `processDying()`
- **Expected:** `dying` value incremented to 3
- **Roles:** n/a (service layer)

### TC-COND-12 — processDying: dying 4 transitions participant to dead status
- **AC:** `[NEW]` `dying 4` transitions participant to `dead` status and ends their encounter participation
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testDyingFourTransitionsToDeadStatus()`
- **Setup:** Apply `dying 3`; mock roll to return critical failure; call `processDying()`
- **Expected:** Participant status set to `dead`; `dying` condition soft-deleted; participant removed from active encounter
- **Roles:** n/a (service layer)

### TC-COND-13 — checkConditionRestrictions: paralyzed returns cannot-act restriction
- **AC:** `[EXTEND]` `RulesEngine::checkConditionRestrictions()` returns correct restriction for paralyzed
- **Suite:** `module-test-suite`
- **Test class/method:** `RulesEngineTest::testParalyzedRestrictionCannotAct()`
- **Setup:** Participant with `paralyzed` condition; call `checkConditionRestrictions($participant_id)`
- **Expected:** Result contains `cannot_act = true`; action budget validation blocked
- **Roles:** n/a (service layer)

### TC-COND-14 — checkConditionRestrictions: unconscious returns cannot-act restriction
- **AC:** `[EXTEND]` `RulesEngine::checkConditionRestrictions()` returns correct restriction for unconscious
- **Suite:** `module-test-suite`
- **Test class/method:** `RulesEngineTest::testUnconsciousRestrictionCannotAct()`
- **Setup:** Participant with `unconscious` condition; call `checkConditionRestrictions()`
- **Expected:** Result contains `cannot_act = true`
- **Roles:** n/a (service layer)

### TC-COND-15 — checkConditionRestrictions: grabbed returns cannot-move restriction
- **AC:** `[EXTEND]` `RulesEngine::checkConditionRestrictions()` returns correct restriction for grabbed
- **Suite:** `module-test-suite`
- **Test class/method:** `RulesEngineTest::testGrabbedRestrictionCannotMove()`
- **Setup:** Participant with `grabbed` condition; call `checkConditionRestrictions()`
- **Expected:** Result contains `cannot_move = true`; stride action blocked
- **Roles:** n/a (service layer)

### TC-COND-16 — Valued condition stacking increases value, capped at max_value
- **AC:** `[EXTEND]` Applying a valued condition already present increases value; capped at max_value
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testValuedConditionStackingCapAtMax()`
- **Setup:** Apply `frightened 3` (max_value=4); apply `frightened 2` again
- **Expected:** `frightened` value increases (e.g., to 5 capped at 4 = 4); no duplicate row
- **Roles:** n/a (service layer)

### TC-COND-17 — Non-valued condition apply is idempotent
- **AC:** `[NEW]` Applying a non-valued condition already present is a no-op
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testNonValuedConditionApplyIsIdempotent()`
- **Setup:** Apply `blinded`; apply `blinded` again
- **Expected:** Only one active `blinded` condition row; no error; second apply is a no-op
- **Roles:** n/a (service layer)

### TC-COND-18 — removeCondition on non-existent condition is a no-op
- **AC:** `[EXTEND]` Removing a condition that does not exist on the participant returns no-op (not an error)
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testRemoveNonExistentConditionIsNoOp()`
- **Setup:** Participant with no active conditions; call `removeCondition($participant_id, 'frightened', $encounter_id)`
- **Expected:** No exception; no DB error; zero rows affected is acceptable
- **Roles:** n/a (service layer)

### TC-COND-19 — Clumsy imposes stat penalty to Dex-based checks
- **AC:** `[TEST-ONLY]` Conditions that impose a penalty to a stat are reflected in effective stat calculation
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testClumsyImposesStatPenaltyToDex()`
- **Setup:** Character with Dex 16 (+3); apply `clumsy 2` (−2 to Dex-based checks); calculate effective Dex modifier
- **Expected:** Effective Dex modifier = +1 (3 − 2); used in AC and Reflex save calculations
- **Roles:** n/a (stat calculation check)

### TC-COND-20 — Condition state is readable by anonymous users (display only)
- **AC:** Anonymous user behavior: condition state is readable (displayed to all encounter participants)
- **Suite:** `role-url-audit`
- **Entry:** `GET /api/encounter/{id}/conditions` — HTTP 200 for anonymous (or encounter-context: all participants)
- **Expected:** 200 for anonymous (read-only condition display); no mutation allowed
- **Roles:** anonymous (200)

### TC-COND-21 — Only GM or character controller may apply conditions
- **AC:** Authenticated user behavior: only GM or character controller may apply conditions
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testOnlyGmOrControllerCanApplyCondition()`
- **Setup:** Player A tries to apply condition to player B's character; call `applyCondition()` with player A session
- **Expected:** 403 or authorization exception; condition not created
- **Roles:** authenticated player (other's character)

### TC-COND-22 — Admin can remove any condition
- **AC:** Admin behavior: admin can remove any condition for moderation
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testAdminCanRemoveAnyCondition()`
- **Setup:** Apply condition to any participant; remove as admin
- **Expected:** Condition soft-deleted; no authorization error
- **Roles:** admin

### TC-COND-23 — combat_conditions uses insert-only (apply) and soft-delete (remove)
- **AC:** No hard deletes during active encounter
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testInsertOnlyAndSoftDelete()`
- **Setup:** Apply condition; remove condition; inspect DB rows
- **Expected:** Apply creates new row; remove sets `removed_at_round` (not DELETE); row remains in table
- **Roles:** n/a (data integrity check)

### TC-COND-24 — Clearing an encounter clears its conditions
- **AC:** Rollback: conditions are encounter-scoped; clearing an encounter clears its conditions
- **Suite:** `module-test-suite`
- **Test class/method:** `ConditionManagerTest::testEncounterClearClearsConditions()`
- **Setup:** Active encounter with conditions on participants; call encounter-clear/teardown
- **Expected:** All condition rows for that encounter have `removed_at_round` set or are deleted (per rollback strategy); no conditions remain active for cleared encounter
- **Roles:** n/a (rollback check)

### TC-COND-25 — QA audit still passes after module deployment
- **AC:** QA automated audit must still pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler` (local dev)
- **Expected:** 0 violations, 0 failures
- **Roles:** all 6 roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| Full PF2E condition catalog completeness (~40 conditions) | Verifying that every one of the ~40 PF2E conditions is accurately represented in the catalog with correct effects, max_values, and end_triggers requires manual review against PF2E Core Rulebook Conditions Appendix. TC-COND-02 verifies the presence of 26 core conditions but cannot assert full accuracy of every effect definition. |
| Error messages are clear and actionable (AC: `[TEST-ONLY]`) | "Clear and actionable" is a UX quality criterion; TC-COND-04 verifies an error is returned and contains a specific string, but the broader clarity requirement needs human review. |

---

## Regression risk areas

1. `dc-cr-action-economy` coupling: `checkConditionRestrictions()` (TC-COND-13 to TC-COND-15) gates action dispatch in `ActionProcessor`; if conditions system changes restriction keys, action economy tests will fail silently.
2. `dc-cr-dice-system` coupling: `processDying()` calls `NumberGenerationService::rollFlat(10)` for the DC 10 check; if the dice system changes its interface, dying tests will break.
3. `dc-cr-difficulty-class` coupling: `processDying()` uses flat DC 10 — confirmed against `getSimpleDC(level=1)=15` (different table) — verify `processDying` uses the *flat check* table (DC 10), not `getSimpleDC`.
4. Combat_conditions table insert-only contract (TC-COND-23): any code path that uses `DELETE` instead of soft-delete will corrupt historical encounter data and break audit trails.
5. QA audit regression: if `GET /api/encounter/{id}/conditions` is added as a new public route, it must be registered in `qa-permissions.json` during the Stage 0 preflight for this feature.
