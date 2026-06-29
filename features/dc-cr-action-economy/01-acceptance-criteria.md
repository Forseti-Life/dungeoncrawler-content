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
