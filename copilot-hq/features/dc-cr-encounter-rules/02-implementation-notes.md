# Implementation Notes (Dev-owned)
# Feature: dc-cr-encounter-rules

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after module changes.
- `docs/dungeoncrawler/issues/issue-4-combat-encounter-system-design.md` — design reference; read before implementing resolveAttack.

## Status
- in_progress (code done; awaiting QA Gate 2)

## What already existed (pre-implementation)
All core encounter rules were substantially implemented:
- `CombatEngine.php`: encounter lifecycle (createEncounter, startEncounter with auto-roll Perception initiative, startRound/endRound, startTurn/endTurn with end-of-turn condition tick via `processEndOfTurnEffects()`)
- `CombatEngine::resolveAttack()`: d20 + attack_bonus + MAP vs. target AC, natural 20/1 bumps degree, delegates to `CombatCalculator::calculateDegreeOfSuccess()`, calls `HPManager::applyDamage()` on hit/crit
- `CombatCalculator::calculateMultipleAttackPenalty()`: normal (−5/−10) and agile (−4/−8) MAP
- `CombatCalculator::calculateDegreeOfSuccess()`: crit success (beat DC by 10+ or nat 20), crit failure (miss DC by 10+ or nat 1), plus degree bumping
- `HPManager::applyDamage()`: temp HP absorption first, dying condition on HP ≤ 0, instant death at HP ≤ −max_hp
- `HPManager::applyDyingCondition()`: PF2e wounded stacking; unconscious + prone applied
- `ConditionManager::tickConditions()`: frightened and valued conditions decremented end-of-turn

## Gap filled this cycle (commit 3f66e773)
### Damage type resistances/weaknesses (AC: `[NEW]` applyDamage accounts for resistances/weaknesses)
- `HPManager::applyDamage()` now reads `entity_data` JSON column on `combat_participants`
- If `resistances[]` or `weaknesses[]` are present with a type matching `$damage_type`, resistance value is subtracted (floor 0) and weakness value is added before temp HP absorption
- No schema change: `entity_data` column already exists as a JSON store on `combat_participants`

## Access control
| Action | Anonymous | Player (own character) | GM | Admin |
|---|---|---|---|---|
| Read encounter state | ✅ (same session) | ✅ | ✅ | ✅ |
| Submit attack action | ❌ | ✅ | ✅ | ✅ |
| Force-advance turn / modify HP | ❌ | ❌ | ✅ | ✅ |

## Files modified
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/HPManager.php`

## Verification
```bash
php -l sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/HPManager.php
cd /var/www/html/dungeoncrawler && drush --uri=https://dungeoncrawler.forseti.life cr
```
Both passed.

## Rollback
- Revert commit 3f66e773.
- No schema changes.

## AC coverage table
| AC item | Status | Notes |
|---|---|---|
| startEncounter auto-roll initiative (Perception) | ✅ existing | resolvePerceptionModifier + d20 roll in startEncounter |
| Initiative sort descending, ties by Perception then ID | ✅ existing | usort in startRound |
| resolveAttack: d20+bonus+MAP vs. AC → degree | ✅ existing | CombatEngine::resolveAttack() |
| applyDamage with resistances/weaknesses | ✅ THIS CYCLE | entity_data JSON lookup in HPManager |
| End-of-turn condition tick (frightened etc.) | ✅ existing | processEndOfTurnEffects → tickConditions |
| Agile weapon −4/−8 MAP | ✅ existing | CombatCalculator::calculateMultipleAttackPenalty |
| Natural 20 bumps degree up; natural 1 bumps down | ✅ existing | calculateDegreeOfSuccess |
| Dying 1 at HP ≤ 0 | ✅ existing | HPManager::applyDamage → applyDyingCondition |
| Instant death at HP ≤ −max_hp | ✅ existing | HPManager::evaluateDeath |
| resolveAttack invalid IDs → structured error | ✅ existing | early return with 'error' key |
