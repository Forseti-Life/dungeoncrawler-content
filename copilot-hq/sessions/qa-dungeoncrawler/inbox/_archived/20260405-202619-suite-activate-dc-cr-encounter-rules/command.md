# Suite Activation: dc-cr-encounter-rules

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-05T20:26:19+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-encounter-rules"`**  
   This links the test to the living requirements doc at `features/dc-cr-encounter-rules/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-encounter-rules-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-encounter-rules",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-encounter-rules"`**  
   Example:
   ```json
   {
     "id": "dc-cr-encounter-rules-<route-slug>",
     "feature_id": "dc-cr-encounter-rules",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-encounter-rules",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-encounter-rules

**Owner:** qa-dungeoncrawler  
**Date:** 2026-03-28  
**Feature:** Encounter Rules — initiative auto-roll/sort, resolveAttack, MAP (normal + agile), applyDamage with types, end-of-turn condition tick, nat20/nat1, dying-on-zero-HP  
**AC source:** `features/dc-cr-encounter-rules/01-acceptance-criteria.md`  
**Implementation notes:** `features/dc-cr-encounter-rules/02-implementation-notes.md`  

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit immediately after module changes.
- KB: `docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md` — referenced in CombatEngine; read before implementing `resolveAttack`.
- No prior lessons found for attack roll resolution specifically; this is the first combat-resolution feature in the pipeline.

## Gap analysis summary (from AC)
- Encounter lifecycle / round management: **Full** — existing; tests verify no regression
- Initiative auto-roll (Perception-based): **Partial** — extend `startEncounter()`
- `resolveAttack()`: **None** — new method; all TCs are new
- MAP normal/agile: **Full** (`CombatCalculator::calculateMultipleAttackPenalty`) — tests verify existing behavior plus agile branch
- `applyDamage()` with types/resistances: **Partial** — extend `HPManager`
- End-of-turn condition tick: **Partial** — extend `CombatEngine` end-of-turn hook into `ConditionManager::tickConditions()`

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` (unit) | PHPUnit unit (`./vendor/bin/phpunit tests/src/Unit/Service/`) | CombatEngine, CombatCalculator, HPManager, ConditionManager business logic |
| `module-test-suite` (functional) | PHPUnit functional (`./vendor/bin/phpunit tests/src/Functional/`) | Full-round simulation via CombatEncounterFlowTest |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: encounter-state readable anon, attack-submit auth-required |

> **Note:** All combat math is service-layer PHP. Route-audit tests are limited because combat mutation endpoints use parameterized paths (encounter/{id}/attack etc.) — those entries are `ignore` in route scan. Permissions are validated via unit/functional tests. The full-round simulation (TC-ER-16) is the first functional integration test covering multiple services together.

---

## Test cases

### TC-ER-01 — startEncounter auto-rolls initiative for participants without custom initiative
- **AC:** `[EXTEND]` `startEncounter()` auto-rolls initiative as Perception check (d20 + Perception modifier) for participants with no custom initiative
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testStartEncounterAutoRollsInitiativeForUnseededParticipants()`
- **Setup:** Create encounter with 2 participants: one with custom_initiative=15, one without; call `startEncounter()`
- **Expected:** Participant without custom initiative has an integer initiative value assigned (≥1); participant with custom initiative retains 15
- **Roles:** n/a (service layer)

### TC-ER-02 — Participants sorted in descending initiative order at round start
- **AC:** `[EXTEND]` Sorted descending; ties broken by Perception modifier, then arbitrarily
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testParticipantsSortedDescendingByInitiative()`
- **Setup:** 3 participants with initiatives 18, 12, 7; call `startRound()`
- **Expected:** Turn order = [18, 12, 7]; `turnIndex` advances in this order
- **Roles:** n/a (service layer)

### TC-ER-03 — Initiative tie broken by Perception modifier
- **AC:** `[EXTEND]` Ties broken by Perception modifier
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testInitiativeTieBreakByPerceptionModifier()`
- **Setup:** 2 participants both with initiative 14; participant A has Perception +5, participant B has Perception +2
- **Expected:** Participant A appears before participant B in turn order
- **Roles:** n/a (service layer)

### TC-ER-04 — resolveAttack returns critical success on total ≥ (AC + 10)
- **AC:** `[NEW]` `resolveAttack()` rolls d20 + attack bonus vs target AC → returns degree of success
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testResolveAttackCriticalSuccess()`
- **Setup:** Mock d20 roll = 15; attack bonus = 10; target AC = 15 → total 25 = AC+10; call `resolveAttack()`
- **Expected:** Returns `degree_of_success = critical_success`
- **Roles:** n/a (service layer)

### TC-ER-05 — resolveAttack returns success on total ≥ AC (but < AC+10)
- **AC:** `[NEW]` Standard success
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testResolveAttackSuccess()`
- **Setup:** Mock d20 = 10; attack bonus = 5; target AC = 15 → total 15 = AC
- **Expected:** Returns `degree_of_success = success`
- **Roles:** n/a (service layer)

### TC-ER-06 — resolveAttack returns failure on total < AC (but > AC-10)
- **AC:** `[NEW]` Standard failure
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testResolveAttackFailure()`
- **Setup:** Mock d20 = 5; attack bonus = 5; target AC = 15 → total 10 < AC
- **Expected:** Returns `degree_of_success = failure`
- **Roles:** n/a (service layer)

### TC-ER-07 — resolveAttack returns critical failure on total ≤ (AC − 10)
- **AC:** `[NEW]` Critical failure
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testResolveAttackCriticalFailure()`
- **Setup:** Mock d20 = 1; attack bonus = 2; target AC = 15 → total 3 ≤ AC−10
- **Expected:** Returns `degree_of_success = critical_failure`
- **Roles:** n/a (service layer)

### TC-ER-08 — resolveAttack applies MAP (−5/−10) for 2nd and 3rd attacks
- **AC:** `[NEW]` MAP applied for attack number > 1
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testResolveAttackAppliesNormalMAP()`
- **Setup:** attack_number=2, base attack_bonus=+8; verify MAP penalty −5 applied (effective +3); attack_number=3 applies −10 (effective −2)
- **Expected:** `CombatCalculator::calculateMultipleAttackPenalty(2)` = −5; `(3)` = −10; both applied correctly in resolveAttack
- **Roles:** n/a (service layer)

### TC-ER-09 — resolveAttack applies agile MAP (−4/−8) for agile weapon
- **AC:** `[EXTEND]` Attacker with agile weapon uses −4/−8 MAP
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatCalculatorTest::testAgileMAPPenalties()`
- **Setup:** Weapon marked `is_agile=true`; attack_number=2 → MAP penalty = −4; attack_number=3 → MAP penalty = −8
- **Expected:** `calculateMultipleAttackPenalty(2, is_agile=true)` = −4; `(3, is_agile=true)` = −8
- **Roles:** n/a (service layer)

### TC-ER-10 — Natural 20 bumps degree of success one step up
- **AC:** `[NEW]` Natural 20 bumps degree up (per PF2E); failure → success, success → critical success
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testNatural20BumpsDegreeUp()`
- **Setup:** Mock d20 = 20 (natural 20); attack total would be failure without nat20 bump; call `resolveAttack()`
- **Expected:** `degree_of_success = success` (bumped from failure); if total was already success → critical_success
- **Roles:** n/a (service layer)

### TC-ER-11 — Natural 1 bumps degree of success one step down
- **AC:** `[NEW]` Natural 1 bumps degree down; success → failure, critical success → success
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testNatural1BumpsDegreeDown()`
- **Setup:** Mock d20 = 1 (natural 1); attack total would be success without nat1 bump
- **Expected:** `degree_of_success = failure` (bumped from success); if total was crit success → success
- **Roles:** n/a (service layer)

### TC-ER-12 — applyDamage reduces target HP correctly
- **AC:** `[NEW]` `applyDamage()` applies damage to HP
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `HPManagerTest::testApplyDamageReducesHP()`
- **Setup:** Participant with HP=30; call `applyDamage($id, 10, 'piercing', $encounter_id)`
- **Expected:** Participant HP = 20; HP change recorded transactionally
- **Roles:** n/a (service layer)

### TC-ER-13 — applyDamage applies resistance (halves damage of that type)
- **AC:** `[NEW]` `applyDamage()` accounts for resistances on participant entity
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `HPManagerTest::testApplyDamageWithResistance()`
- **Setup:** Participant with `resistance: fire 5`; call `applyDamage($id, 12, 'fire', $encounter_id)`
- **Expected:** HP reduced by 7 (12 − 5 resistance = 7); resistance properly applied
- **Roles:** n/a (service layer)

### TC-ER-14 — Participant reaching 0 HP receives dying 1 condition
- **AC:** `[EXTEND]` Participant with 0 HP receives `dying 1` automatically
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `HPManagerTest::testZeroHPGrantsDyingOne()`
- **Setup:** Participant with HP=5; apply 5 damage → HP=0
- **Expected:** `dying 1` condition applied via `ConditionManager::applyCondition()`; participant not yet dead
- **Roles:** n/a (service layer)

### TC-ER-15 — Participant reaching negative max-HP dies immediately (no dying condition)
- **AC:** `[EXTEND]` Negative HP equal to max HP → immediate death, no dying condition
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `HPManagerTest::testNegativeMaxHPCausesImmediateDeath()`
- **Setup:** Participant with max_hp=20, HP=5; apply 25 damage → HP=−20 (= −max_hp)
- **Expected:** Participant status = `dead`; no `dying` condition applied (immediate death bypasses dying)
- **Roles:** n/a (service layer)

### TC-ER-16 — Full-round simulation: initiative → attack → damage → condition tick → next turn
- **AC:** `[TEST-ONLY]` A complete round can be simulated end-to-end
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `CombatEncounterFlowTest::testFullRoundSimulation()`
- **Setup:** 2 participants; start encounter; auto-roll initiative; player A attacks player B (success → 1d6+3 damage); end player A's turn (tickConditions); player B's turn begins
- **Expected:** All 6 service interactions succeed without exception; HP reduced; condition state updated; turn advances to player B
- **Roles:** authenticated player (GM)

### TC-ER-17 — resolveAttack with invalid participant ID returns structured error
- **AC:** `[NEW]` Invalid participant/target IDs return structured error (not raw PHP exception)
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testResolveAttackInvalidParticipantReturnsStructuredError()`
- **Setup:** Call `resolveAttack()` with non-existent participant_id=99999
- **Expected:** Returns structured error array/object with `error` key; no unhandled PHP exception propagated to API
- **Roles:** n/a (service layer)

### TC-ER-18 — HP changes are transactional (no partial updates)
- **AC:** HP changes are transactional; no inconsistent state
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `HPManagerTest::testHPChangeIsTransactional()`
- **Setup:** Mock DB transaction failure mid-damage-apply; verify no partial HP change persisted
- **Expected:** HP unchanged (rollback); no participant left in inconsistent state
- **Roles:** n/a (data integrity check)

### TC-ER-19 — Only GM or character controller may submit attack actions
- **AC:** Authenticated user behavior: only character controller or GM may submit attacks
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testUnauthorizedPlayerCannotSubmitAttack()`
- **Setup:** Player A owns character; player B attempts to call `resolveAttack()` for player A's character
- **Expected:** 403 or authorization exception; attack not resolved
- **Roles:** authenticated player (other's character)

### TC-ER-20 — Encounter state is readable by anonymous users
- **AC:** Anonymous user behavior: encounter state is readable
- **Suite:** `role-url-audit`
- **Entry:** `GET /api/encounter/{id}` — HTTP 200 for anonymous (read-only encounter state display)
- **Expected:** 200 or equivalent for anonymous; parameterized path, so `ignore` for route-scan; unit-test verifies read access
- **Roles:** anonymous

### TC-ER-21 — End-of-turn processing calls tickConditions for current participant
- **AC:** `[EXTEND]` End-of-turn processing decrements timed conditions and removes expired ones
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CombatEngineTest::testEndOfTurnCallsTickConditions()`
- **Setup:** Participant with `frightened 2`; call `CombatEngine::endTurn($participant_id, $encounter_id)`
- **Expected:** `ConditionManager::tickConditions()` called; `frightened` value decremented to 1
- **Roles:** n/a (service layer)

### TC-ER-22 — QA audit still passes after module deployment
- **AC:** QA automated audit must still pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler` (local dev)
- **Expected:** 0 violations, 0 failures
- **Roles:** all 6 roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| "Error messages are clear and actionable" (AC: `[TEST-ONLY]`) | Quality criterion; automation verifies error is returned with a specific key/message — human review needed for "actionable" judgment. |
| Full-round simulation (TC-ER-16) — visual/UI turn indicator | The functional test verifies service-layer state; the UI rendering of turn order and HP bars requires Playwright once the encounter UI is built. |
| Admin force-advance turn / HP modification for moderation | The `role-url-audit` suite entry for admin-only mutation endpoints requires knowing the specific route paths (not yet defined). Add to `qa-permissions.json` at Stage 0 preflight once routes are confirmed. |

---

## Regression risk areas

1. `dc-cr-action-economy` coupling: `resolveAttack()` must call `validateActionEconomy()` before resolving; if action economy interface changes, attack resolution will silently skip budget checks.
2. `dc-cr-dice-system` coupling: `resolveAttack()` uses `NumberGenerationService::rollDie(20)` and `applyDamage` uses `rollExpression()`; dice system interface changes break all combat math.
3. `dc-cr-conditions` coupling: end-of-turn tick (TC-ER-21) calls `ConditionManager::tickConditions()`; if conditions system changes its method signature, end-of-turn processing breaks silently.
4. HP transactionality (TC-ER-18): any async/parallel request handling that bypasses the DB transaction wrapper will produce inconsistent HP state and corrupt encounter data.
5. MAP calculation regression: `CombatCalculator::calculateMultipleAttackPenalty()` is already implemented; any refactor must not change the return values for standard (−5/−10) and agile (−4/−8) penalties.
6. QA audit regression: new routes added by this module (e.g., `POST /api/encounter/{id}/attack`) must be registered in `qa-permissions.json` at Stage 0 preflight (parameterized → all-ignore).

### Acceptance criteria (reference)

# Acceptance Criteria (PM-owned)
# Feature: dc-cr-encounter-rules

## Gap analysis reference

Gap analysis performed against `dungeoncrawler_content/src/Service/CombatEngine.php`, `CombatCalculator.php`, `CombatEncounterStore.php`, `HPManager.php`.

Coverage findings:
- Encounter lifecycle (create, start, active, end) — **Full** (`CombatEngine::createEncounter`, `startEncounter`)
- Round management and turn order — **Full** (`startRound`, `turnIndex` tracking in store)
- Initiative rolling (Perception check seeding) — **Partial** (custom_initiatives can be injected but no Perception-based auto-roll)
- Attack roll calculation (d20 + bonus vs. AC → degree of success) — **None** (no `resolveAttack()` found in CombatEngine)
- Multiple Attack Penalty (MAP) — **Full** (`CombatCalculator::calculateMultipleAttackPenalty`)
- Damage application — **Partial** (`HPManager` exists; full damage type/resistance handling unclear)
- End-of-turn condition processing — **Partial** (`ConditionManager` exists; auto-decrement on turn end not confirmed)

Feature type: **enhancement** (encounter lifecycle complete; add attack roll resolution, initiative auto-roll, damage application with types)

## Happy Path
- [ ] `[EXTEND]` `startEncounter()` can auto-roll initiative for participants who have no custom initiative supplied (Perception check = d20 + Perception modifier).
- [ ] `[EXTEND]` Participants are sorted in descending initiative order at round start; ties broken by Perception modifier, then arbitrarily.
- [ ] `[NEW]` `resolveAttack(participant_id, target_id, weapon_id, encounter_id)` rolls d20 + attack bonus, applies MAP for attack number > 1, compares vs. target AC → returns degree of success.
- [ ] `[NEW]` `applyDamage(participant_id, damage_amount, damage_type, encounter_id)` applies damage to HP, accounting for resistances/weaknesses recorded on the participant entity (if present).
- [ ] `[EXTEND]` End-of-turn processing decrements timed conditions (e.g., frightened value, duration_remaining) for the current participant and removes expired conditions.
- [ ] `[TEST-ONLY]` A complete round can be simulated: initiative → attacker turn → attack → damage → condition tick → next turn.

## Edge Cases
- [ ] `[EXTEND]` Attacker with agile weapon uses −4/−8 MAP instead of −5/−10.
- [ ] `[NEW]` Natural 20 on attack bumps degree of success one step up (per PF2E rules); natural 1 bumps one step down.
- [ ] `[EXTEND]` Participant with 0 HP receives the `dying 1` condition automatically (not just HP=0 state).
- [ ] `[EXTEND]` Participant who reaches negative HP equal to their max HP is immediately dead (no dying condition needed).

## Failure Modes
- [ ] `[TEST-ONLY]` Error messages are clear and actionable.
- [ ] `[NEW]` `resolveAttack` with invalid participant/target IDs returns a structured error (not a PHP exception propagated to API).

## Permissions / Access Control
- [ ] Anonymous user behavior: encounter state is readable by anonymous users in same game session.
- [ ] Authenticated user behavior: only the character's controlling user (or GM) may submit attack actions.
- [ ] Admin behavior: admin can force-advance turn or modify HP for moderation purposes.

## Data Integrity
- [ ] HP changes are transactional (no partial updates where participant is in inconsistent state).
- [ ] Rollback path: encounter state is stored in DB; a failed encounter can be deleted and re-created from the same campaign state.

## Knowledgebase check
- Related lessons/playbooks: `docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md` referenced in CombatEngine — read before implementing resolveAttack.

## Test path guidance (for QA)
| Requirement | Test path |
|---|---|
| Initiative sort and auto-roll | `tests/src/Unit/Service/CombatEngineTest.php` |
| `resolveAttack` (all degrees) | `tests/src/Unit/Service/CombatEngineTest.php` (new method) |
| MAP (normal and agile) | `tests/src/Unit/Service/CombatCalculatorTest.php` |
| `applyDamage` with types | `tests/src/Unit/Service/HPManagerTest.php` (extend) |
| End-of-turn condition tick | `tests/src/Unit/Service/ConditionManagerTest.php` (extend) |
| Full-round simulation | `tests/src/Functional/CombatEncounterFlowTest.php` (new) |
- Agent: qa-dungeoncrawler
- Status: pending
