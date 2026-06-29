# Implementation Notes (Dev-owned)
# Feature: dc-cr-action-economy

## Summary
EXTEND: `RulesEngine::validateActionEconomy()` is a TODO stub; `ActionProcessor` only decrements by 1 for any action cost; `reaction_available` field is not tracked. This feature adds: (1) implement `validateActionEconomy()` to enforce multi-action budgets and reaction consumption, (2) add `reaction_available` tracking to `ActionProcessor` and `CombatEncounterStore`.

## Impact Analysis
- Touches `RulesEngine.php`, `ActionProcessor.php` — both in `dungeoncrawler_content/src/Service/`.
- `CombatEncounterStore` participant schema needs `reaction_available` bool column or JSON field.
- Existing 1-action strike/stride will continue to work; 2/3-action enforcement is additive.

## Files / Components Touched
- `dungeoncrawler_content/src/Service/RulesEngine.php` — implement `validateActionEconomy($participant, $action_cost)`
- `dungeoncrawler_content/src/Service/ActionProcessor.php` — consume action cost dynamically (not hardcoded -1); add reaction guard
- `dungeoncrawler_content/src/Service/CombatEncounterStore.php` — confirm `reaction_available` column or add to participant JSON

## Data Model / Storage Changes
- Schema updates: `reaction_available` bool on participant state (DB column or JSON field — check `CombatEncounterStore` storage pattern first)
- Config changes: none
- Migrations: any existing participants must receive `reaction_available = true` default

## First code slice
1. Implement `RulesEngine::validateActionEconomy()`:
   - Read `participant['actions_remaining']` and `participant['reaction_available']`
   - If `action_cost` is `'reaction'`: check `reaction_available === true`, return error if false
   - If `action_cost` is int: check `actions_remaining >= action_cost`, return error if not
   - If `action_cost` is `'free'`: always pass
2. Update `ActionProcessor` to call `validateActionEconomy()` before executing, and decrement by the action's actual cost (not hardcoded 1).

## Security Considerations
- Input validation: action_cost values must be validated to known values (1, 2, 3, 'free', 'reaction'); reject unknown costs.
- Access checks: participant must be the active turn participant; enforced by existing CombatEngine turn order.
- Sensitive data handling: none (no PII).

## Testing Performed
- Commands run: `drush php:script combat_engine_test.php` — 136/136 PASS (no regressions)
- `php -l ActionProcessor.php` — no syntax errors
- `php -l ActionEconomyTest.php` — no syntax errors
- Targeted scenarios:
  - `turn_start` → `actions_remaining=3, reaction_available=true` ✓ (CombatEngine.startTurn already correct)
  - spend 2-action activity → `actions_remaining=1` ✓ (validateActionEconomy already correct)
  - attempt another 2-action → should reject ✓
  - spend reaction → `reaction_available=false` ✓ (executeReactionAction new)
  - attempt second reaction → should reject ✓
  - spend free action → no decrement ✓ (executeFreeAction new)
  - invalid cost → reject ✓

## Rollback / Recovery
- Revert commit. No schema migration needed if `reaction_available` is stored in participant JSON (not a new DB column). DB-column path: add nullable with default true.

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit after module changes.

## What I learned (Dev)
- `validateActionEconomy` and `startTurn` reset were already fully implemented; the gap was the dispatch layer (`executeAction` switch) and the test class.
- `executeStrike/executeStride` hardcoded `-1` rather than using `economy['actions_after']`; fixed to use the validated value for architectural consistency.
- `executeReactionAction` must accept `allow_out_of_turn` flag because reactions can legally fire outside the participant's own turn (e.g., AoO, Shield Block).

## 2026-04-06 addendum — activity dispatch gap closed

Gap from original AC analysis: `executeAction` switch had no `activity` case, so generic 2/3-action activities (non-spell) could not be dispatched through the service layer. Fixed by adding `case 'activity':` → `executeActivity()` in ActionProcessor.php. Commit: `27a42744`.

- `executeActivity($participant_id, $action_data, $encounter_id)`: reads `action_data['action_cost']` (int 1/2/3, default 1), calls `validateActionEconomy`, decrements `actions_remaining`, logs the action.
- All AC criteria now covered: turn reset ✓, decrement by cost ✓, floor-at-zero ✓, reaction guard ✓, free-action no-decrement ✓, out-of-turn rejection ✓, invalid cost rejection ✓.
- Drush `cr` clean after commit.

## What I'd change next time (Dev)
- Include a Stage-0 check in the acceptance criteria validation pass to confirm `reaction_available` handling exists in `ReactionHandler` before starting implementation — it was already there, which saved time once discovered.
