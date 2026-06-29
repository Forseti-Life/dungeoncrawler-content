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
