# Suite Activation: dc-cr-action-economy

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-05T20:25:30+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-action-economy"`**  
   This links the test to the living requirements doc at `features/dc-cr-action-economy/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-action-economy-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-action-economy",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-action-economy"`**  
   Example:
   ```json
   {
     "id": "dc-cr-action-economy-<route-slug>",
     "feature_id": "dc-cr-action-economy",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-action-economy",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-action-economy

**Owner:** qa-dungeoncrawler  
**Date:** 2026-03-27  
**Feature:** Action Economy — full 1/2/3-action, reaction, and free-action budget enforcement  
**AC source:** `features/dc-cr-action-economy/01-acceptance-criteria.md`  
**Implementation notes:** `features/dc-cr-action-economy/02-implementation-notes.md`  

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit after module changes; surface regressions immediately.
- KB: no prior lessons found for PF2E action economy logic; this is the first action-budget feature in the pipeline.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | Business logic: action budget math, reaction tracking, free-action pass-through, validation guards |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: auth-required mutation endpoints, anon-blocked routes |

> **Note:** Action economy logic is service-layer (PHP), not URL-accessible. All business logic tests map to `module-test-suite`. Access control tests map to `role-url-audit`. No Playwright suite needed at this scope; mutation flows require auth tokens not available in crawl context.

---

## Test cases

### TC-AE-01 — Turn start resets action budget
- **AC:** `actions_remaining` resets to 3 and `reaction_available` resets to `true` at turn start
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testTurnStartResetsActionBudget()`
- **Setup:** Create participant with `actions_remaining=0, reaction_available=false`; call `turn_start()`
- **Expected:** `actions_remaining === 3`, `reaction_available === true`
- **Tags:** `[EXTEND]`, happy path

### TC-AE-02 — 1-action cost decrements by 1
- **AC:** Spending an action decrements `actions_remaining` by the action's cost
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testOneActionCostDecrement()`
- **Setup:** `actions_remaining=3`; spend 1-action Strike
- **Expected:** `actions_remaining === 2`
- **Tags:** `[EXTEND]`, happy path

### TC-AE-03 — 2-action activity decrements by 2
- **AC:** Spending a 2-action activity decrements `actions_remaining` by 2
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testTwoActionActivityDecrement()`
- **Setup:** `actions_remaining=3`; spend 2-action activity
- **Expected:** `actions_remaining === 1`
- **Tags:** `[EXTEND]`, happy path

### TC-AE-04 — 3-action activity decrements by 3
- **AC:** 3-action activity cost
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testThreeActionActivityDecrement()`
- **Setup:** `actions_remaining=3`; spend 3-action activity
- **Expected:** `actions_remaining === 0`
- **Tags:** `[EXTEND]`, happy path

### TC-AE-05 — Free action does not decrement budget
- **AC:** Free actions are always available and do not consume `actions_remaining`
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testFreeActionNoCost()`
- **Setup:** `actions_remaining=0`; attempt free action
- **Expected:** action succeeds, `actions_remaining === 0` (unchanged)
- **Tags:** `[EXTEND]`, happy path + edge case

### TC-AE-06 — Reaction sets reaction_available to false
- **AC:** `[NEW]` Spending a reaction sets `reaction_available` to `false`
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testReactionConsumption()`
- **Setup:** `reaction_available=true`; spend reaction
- **Expected:** `reaction_available === false`
- **Tags:** `[NEW]`, happy path

### TC-AE-07 — Cannot act when actions_remaining insufficient
- **AC:** A character cannot take a paid action if `actions_remaining < action_cost`
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testInsufficientActionsRejected()`
- **Setup:** `actions_remaining=1`; attempt 2-action activity
- **Expected:** rejection with message containing "Not enough actions"
- **Tags:** `[EXTEND]`, happy path guard

### TC-AE-08 — Cannot use reaction if already spent
- **AC:** `[NEW]` A character cannot use a reaction if `reaction_available` is `false`
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testSpentReactionRejected()`
- **Setup:** `reaction_available=false`; attempt reaction action
- **Expected:** rejection with message containing "Reaction already used this turn"
- **Tags:** `[NEW]`, happy path guard

### TC-AE-09 — 2-action activity rejected with 1 action remaining
- **AC:** A 2-action activity when only 1 action remains is rejected with explicit message
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testTwoActionActivityRejectedAtOneRemaining()`
- **Setup:** `actions_remaining=1`; attempt 2-action activity
- **Expected:** error "Not enough actions"
- **Tags:** `[EXTEND]`, edge case

### TC-AE-10 — actions_remaining cannot go below 0
- **AC:** Guard against double-decrements; floor at 0
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testActionsRemainingFloorAtZero()`
- **Setup:** `actions_remaining=1`; spend 1-action (→0); attempt another 1-action
- **Expected:** second attempt rejected; `actions_remaining === 0` (not -1)
- **Tags:** `[EXTEND]`, edge case

### TC-AE-11 — Invalid action cost rejected
- **AC:** Action cost values of 0, negative, or >3 rejected at content-type validation
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testInvalidActionCostRejected()`
- **Inputs to test:** cost=-1, cost=0, cost=4, cost=`null`, cost=`"bogus"`
- **Expected:** validation error (specific message TBD by Dev); budget unchanged
- **Tags:** `[EXTEND]`, failure mode

### TC-AE-12 — Spending actions outside active turn rejected
- **AC:** Attempting to spend actions when no active turn state returns an appropriate error
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testSpendActionsOutsideActiveTurnRejected()`
- **Setup:** no active turn state (encounter not started or between turns)
- **Expected:** error response; no state mutation
- **Tags:** `[EXTEND]`, failure mode

### TC-AE-13 — Anon cannot access mutation endpoints
- **AC:** Turn/action state mutation endpoints require authentication; anon blocked
- **Suite:** `role-url-audit`
- **Rule type:** `qa-permissions.json` entry — anon → HTTP 403/401 for any `POST /api/character/{id}/action` or equivalent route
- **Roles covered:** anonymous
- **Expected:** HTTP 403 (or 401)
- **Tags:** permissions/access control
- **Note:** Exact route paths TBD by Dev in implementation notes; add `qa-permissions.json` rule at Stage 0 once route is confirmed.

### TC-AE-14 — Authenticated player can spend own character's actions
- **AC:** Players may spend their own character's actions
- **Suite:** `role-url-audit`
- **Rule type:** `qa-permissions.json` entry — authenticated (player role) → HTTP 200 for own character's action endpoint
- **Roles covered:** authenticated player
- **Expected:** HTTP 200
- **Tags:** permissions/access control
- **Note:** Cross-character enforcement (player cannot spend another's actions) tested in `module-test-suite` (TC-AE-15).

### TC-AE-15 — Player cannot spend another character's actions
- **AC:** Players cannot spend another character's actions
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testPlayerCannotSpendOtherCharacterActions()`
- **Setup:** authenticate as player A; attempt action on character owned by player B
- **Expected:** HTTP 403; no state change
- **Tags:** permissions/access control

### TC-AE-16 — Admin can reset/override turn state
- **AC:** Admins may reset or override turn state for GM tooling purposes
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testAdminCanOverrideTurnState()`
- **Setup:** authenticate as admin; call GM reset endpoint
- **Expected:** turn state reset to turn-start defaults; HTTP 200
- **Tags:** permissions/access control

### TC-AE-17 — Data integrity: existing participants receive default action state
- **AC:** On module install/update, existing character nodes receive valid default action state
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testMigrationDefaultActionState()`
- **Setup:** create participant without action economy fields; run module update hook
- **Expected:** `actions_remaining=3, reaction_available=true` (or turn-neutral defaults) on all pre-existing participants
- **Tags:** data integrity
- **Automation note:** Requires a test that runs the update hook and asserts field defaults. If update hook is not yet written, flag to Dev and test manually at Stage 0.

### TC-AE-18 — Rollback: disabling module does not corrupt character nodes
- **AC:** Disabling the action-economy sub-module must not corrupt existing character nodes
- **Suite:** `module-test-suite`
- **Test class/method:** `ActionEconomyTest::testModuleDisableDoesNotCorruptNodes()`
- **Setup:** create participant with action state; uninstall/disable module
- **Expected:** character node still loadable; action state fields null/empty (not missing or corrupt)
- **Tags:** data integrity, rollback
- **Automation note:** Module uninstall in a Drupal test environment is possible with `\Drupal::service('module_installer')->uninstall()`; confirm test coverage is achievable with Dev.

---

## AC items that cannot be fully expressed as automation

| AC item | Limitation | Note to PM |
|---|---|---|
| TC-AE-13/14 exact routes | Route paths unknown until Dev implements | Add `qa-permissions.json` rule at Stage 0 once routes confirmed |
| TC-AE-17 migration | Update hook not yet written | Manual verification acceptable at Stage 0 if PHPUnit env not available |
| TC-AE-18 rollback | Drupal module uninstall in test env may not be feasible | Acceptable as manual smoke test at Stage 0; flag if test env doesn't support it |

---

## Pre-activation checklist (Stage 0, do not activate now)

- [ ] Confirm route paths with Dev; add `qa-permissions.json` rules for TC-AE-13/14
- [ ] Add `module-test-suite` test class `ActionEconomyTest` covering TC-AE-01 through TC-AE-18
- [ ] Update `qa-suites/products/dungeoncrawler/suite.json` with any new suite entries
- [ ] Validate suite: `python3 scripts/qa-suite-validate.py`
- [ ] Confirm `module-test-suite` is promoted to `required_for_release: true` or document risk acceptance
- [ ] Run full `role-url-audit` (0 violations baseline) after Dev deploys to local

### Acceptance criteria (reference)

# Acceptance Criteria (PM-owned)

## Gap analysis reference

Gap analysis performed against `dungeoncrawler_content/src/Service/ActionProcessor.php`, `RulesEngine.php`, `CombatEncounterStore.php`.

Coverage findings:
- `ActionProcessor::executeAction()` — dispatches to stride/strike; checks `actions_remaining >= 1` before Strike — **Partial** (1-action enforcement exists; 2-action/3-action activity cost enforcement not found)
- `actions_remaining` field on participants (stored in DB via `CombatEncounterStore`) — **Partial** (field referenced; reset logic at turn start needs verification)
- `reaction_available` field — **None** (not found in ActionProcessor or CombatEncounterStore)
- `RulesEngine::validateActionEconomy()` — method exists but body is a TODO stub — **Partial** (stub only)
- Free action / reaction action types — **None** (only stride/strike dispatched in ActionProcessor)

Feature type: **enhancement** — action budget enforcement for 1-action Strike exists; complete 2/3-action activities, reaction tracking, and free actions.

All criteria below are tagged accordingly:

## Happy Path
- [ ] `[EXTEND]` A character participant has an action budget of 3 actions and 1 reaction at the start of their turn.
- [ ] `[EXTEND]` Each turn, `actions_remaining` resets to 3 and `reaction_available` resets to true.
- [ ] `[EXTEND]` Actions have a defined cost: 1-action, 2-action (activity), 3-action (activity), free action, or reaction.
- [ ] `[EXTEND]` Spending an action decrements `actions_remaining` by the action's cost (1, 2, or 3). Free actions do not decrement it.
- [ ] `[NEW]` Spending a reaction sets `reaction_available` to false.
- [ ] `[EXTEND]` A character cannot take a paid action if `actions_remaining` is less than the action's cost.
- [ ] `[NEW]` A character cannot use a reaction if `reaction_available` is false.

## Edge Cases
- [ ] `[EXTEND]` A 2-action activity attempted when only 1 action remains is rejected with an explicit message ("Not enough actions").
- [ ] `[NEW]` A reaction attempted when already spent is rejected ("Reaction already used this turn").
- [ ] `[EXTEND]` Free actions are always available (do not consume action budget) and can be taken even at 0 remaining actions.
- [ ] `[EXTEND]` `actions_remaining` cannot go below 0 (guard against double-decrements).

## Failure Modes
- [ ] `[EXTEND]` Invalid action cost values (e.g., 0, negative, or >3) are rejected at content-type validation with a clear error.
- [ ] `[EXTEND]` Attempting to spend actions out of turn (no active turn state) returns an appropriate error.

## Permissions / Access Control
- [ ] Anonymous user behavior: turn/action state is not directly exposed to anonymous users; read-only game state views (if any) may be public but mutation endpoints require authentication.
- [ ] Authenticated user behavior: players may spend their own character's actions; cannot spend another character's actions.
- [ ] Admin behavior: admins may reset or override turn state for GM tooling purposes.

## Data Integrity
- [ ] No data loss on update/migration: any existing character nodes must receive valid default action state on module install/update.
- [ ] Rollback path: disabling the action-economy sub-module must not corrupt existing character nodes; state fields may be left empty/null.

## Verification method
- `drush php-eval` or a custom test script to create a test character, simulate turn start, spend actions, verify state:
  ```
  drush php-eval "/* create character, call turn_start(), assert actions_remaining=3, spend 2 actions, assert actions_remaining=1 */"
  ```
- QA automated audit must still pass (0 violations, 0 failures) after module changes are deployed to local.

## Knowledgebase check
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — reminder to run `drush cr` + QA audit immediately after module changes to surface regressions quickly.
- KB: none found for PF2E action economy specifically; this is the first feature in the pipeline.
- Agent: qa-dungeoncrawler
- Status: pending
