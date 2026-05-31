# Implementation Notes (Dev-owned)
# Feature: dc-cr-conditions

## Summary
Assessment (2026-04-06): All AC items are fully implemented — no code changes required. Full `CONDITIONS` catalog constant exists (~40 PF2E conditions with is_valued, max_value, end_trigger, effects). `applyCondition()` validates catalog type before insert, handles idempotent non-valued and stacking valued conditions. `tickConditions()` and `processDying()` are complete. `RulesEngine::checkConditionRestrictions()` is fully implemented (not a stub) — handles paralyzed/unconscious/dying/petrified (cannot act) and grabbed/immobilized/restrained (cannot move). Feature is ready for QA Gate 2.

## Impact Analysis
- `ConditionManager.php` only — no schema changes required (DB table already has `type`, `value`, `duration`, `removed_at_round`).
- `RulesEngine::checkConditionRestrictions()` stub is in RulesEngine.php — will be addressed in slice 2.
- `processDyingCondition()` is a stub in ConditionManager — implementing it touches dying/recovery rules which tie into CombatEngine turn processing. Coordinate with dc-cr-encounter-rules to avoid conflicts.

## Files / Components Touched
- `dungeoncrawler_content/src/Service/ConditionManager.php`:
  - Add `CONDITION_CATALOG` constant (array keyed by condition name: `is_valued`, `max_value`, `effects`, `end_trigger`)
  - Implement `tickConditions(int $participant_id, int $encounter_id)`: query active conditions, decrement valued conditions by 1, remove those reaching 0
  - Implement `processDyingCondition(int $participant_id, $constitution_modifier, int $encounter_id)`: roll flat DC 10 check, adjust dying value per PF2E rules, transition to `dead` at dying 4
- `dungeoncrawler_content/src/Service/RulesEngine.php` — `checkConditionRestrictions()` (slice 2): return restrictions for paralyzed, unconscious, grabbed

## Data Model / Storage Changes
- Schema updates: none (existing `combat_conditions` table is sufficient)
- Config changes: none
- Migrations: none

## First code slice
1. Add `CONDITION_CATALOG` to `ConditionManager` covering at minimum: frightened (valued, max 4), clumsy (valued, max 4), enfeebled (valued, max 4), drained (valued, max 4), stunned (valued, max 4), dying (valued, max 4), unconscious, paralyzed, grabbed, blinded, deafened, flat-footed.
2. Implement `tickConditions()` — iterate active conditions for participant, decrement `value` for valued conditions with `end_trigger=end_of_turn`, remove when value reaches 0.
3. Verify: apply frightened 2, tick twice, confirm removed.

## Security Considerations
- Input validation: condition type must exist in CONDITION_CATALOG before inserting.
- Access checks: condition application is GM or character controller only (enforced in calling layer).
- Sensitive data handling: none.

## Testing Performed
- Commands run: `drush cr` — clean (no errors)
- Assessment confirmed by reading source:
  - `CONDITIONS` catalog: ~40 conditions verified in ConditionManager.php lines 20–68
  - `applyCondition()`: catalog validation at line 89, idempotent non-valued at line 108, stacking valued at lines 115–119
  - `tickConditions()`: implemented at line 192 — decrements end_of_turn valued conditions, removes at 0
  - `processDying()`: implemented at line 252 — flat DC10, all 4 outcomes, dying 4 = dead + participant status update
  - `checkConditionRestrictions()`: implemented in RulesEngine.php at line 248 — blocking_act list (paralyzed, unconscious, petrified, dying), blocking_move list (grabbed, immobilized, restrained)
  - `removeCondition()`: returns FALSE (no-op) when condition not found on participant

## Rollback / Recovery
- Revert commit. No schema changes. Conditions are encounter-scoped; clearing an encounter clears conditions.

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md`
- PF2E Core Rulebook: Conditions Appendix (reference catalog for condition definitions).

## What I learned (Dev)
- The implementation notes written at feature creation described planned work; actual code was ahead of the notes. Always re-read the source before planning implementation.
- `RulesEngine::checkConditionRestrictions()` was described as a stub in the AC gap analysis but was actually fully implemented when assessed this cycle.

## What I'd change next time (Dev)
- (pending)
