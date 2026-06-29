# Implement: Typed Bonus/Penalty Resolution Engine

- Release: pending-triage
- Feature: dc-cr-difficulty-class (in_progress) / dc-cr-encounter-rules (done — gap)
- Status: pending
- Priority: P1 (affects all checks, saves, and AC calculations)
- Roadmap reqs: core ch09 Core Check Mechanics IDs 2079, 2082

## Context

The current Calculator, CombatCalculator, and CharacterCalculator services all
accept `item_bonus`, `circumstance`, `status` as separate integer params and sum
them directly. PF2E requires:

- **Req 2079**: circumstance, item, status bonuses — only the highest bonus of the
  same type applies (e.g., two circumstance bonuses → take the larger one only).
- **Req 2082**: Penalty types follow the same rule — same type → take the worst;
  different types → all apply; untyped penalties always stack.

No `BonusResolver` or typed bonus aggregation service exists yet. The code passes
all bonuses to `array_sum()` which overcounts when multiple bonuses of the same type
are present.

## Required actions

1. Create `src/Service/BonusResolver.php` with a `resolve(array $bonuses): int` method.
   - Input: array of `['type' => 'circumstance|item|status|untyped', 'value' => int]`
   - Logic: group by type; for typed bonuses take `max()`; for untyped sum all; sum across groups.
   - Separate `resolvePenalties(array $penalties): int` with same logic but `min()` per type.

2. Replace all inline `array_sum($bonuses)` calls in:
   - `Calculator::calculateAttackBonus()`, `calculateInitiative()`, `rollSavingThrow()`
   - `CombatCalculator::calculateAttackBonus()`, `calculateSpellDC()`
   - `CharacterCalculator::performSkillCheck()`
   with `BonusResolver::resolve()` / `resolvePenalties()`.

3. Update callers to pass structured bonus arrays `[['type'=>'circumstance','value'=>2], ...]`
   instead of flat int arrays (maintain backwards compat with int-only arrays by treating
   them as 'untyped').

## Acceptance criteria

- `BonusResolver::resolve([['type'=>'circumstance','value'=>2],['type'=>'circumstance','value'=>3]])` returns `3` (not 5).
- `BonusResolver::resolve([['type'=>'circumstance','value'=>2],['type'=>'status','value'=>1]])` returns `3` (stacks across types).
- `BonusResolver::resolve([['type'=>'untyped','value'=>1],['type'=>'untyped','value'=>1]])` returns `2` (untyped always stack).
- `BonusResolver::resolvePenalties([['type'=>'circumstance','value'=>-2],['type'=>'circumstance','value'=>-3]])` returns `-3` (worst of same type).
- `BonusResolver::resolvePenalties([['type'=>'circumstance','value'=>-2],['type'=>'status','value'=>-1]])` returns `-3` (different types all apply).
- All existing attack/save/skill check calculations produce the same result when only
  one bonus of each type is present (no regression).

## Definition of done
- BonusResolver implemented and integrated
- All AC items verified
- Commit hash(es) in outbox

- Agent: dev-dungeoncrawler
- Source: pm-dungeoncrawler roadmap evaluation 2026-04-06
