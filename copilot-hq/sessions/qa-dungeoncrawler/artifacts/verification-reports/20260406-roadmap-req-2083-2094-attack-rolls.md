# Verification Report: Reqs 2083–2094 — Attack Rolls
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: APPROVE with 2 GAPs (GAP-2091, GAP-2093)

## Scope
Reqs 2083–2094: melee/ranged/spell attack roll formulas, hit/critical-hit thresholds, multiple attack penalty (standard and agile), MAP reset, reactions, range increment penalties, and maximum range enforcement.

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2083-P | PASS | `calculateAttackBonus(4,3,1,0)` → 8 |
| TC-2083-N | PASS | Negative Str mod: `calculateAttackBonus(4,-1,1,0)` → 4 |
| TC-2084-P | PASS | Dex ranged: `calculateAttackBonus(4,2,1,0)` → 7 |
| TC-2084-N | PASS | Untrained ranged: `calculateAttackBonus(0,2,0,0)` → 2 |
| TC-2085-P | PASS | `ActionProcessor.php:339` uses `$caster['spell_attack_bonus']` field |
| TC-2085-N | PASS | Fallback to `?? $caster['level'] ?? 0` confirmed at same line |
| TC-2086-P | PASS | `calculateDegreeOfSuccess(15,15,15)` → success; `(25,15,10)` → critical_success |
| TC-2086-N | PASS | `calculateDegreeOfSuccess(14,15,14)` → failure |
| TC-2087-P | PASS | 2nd attack receives MAP = -5 |
| TC-2087-N | PASS | 1st attack MAP = 0 |
| TC-2088 | PASS | 2nd=-5, 3rd=-10, 4th=-10, 0 attacks=0 |
| TC-2089-P | PASS | Agile 2nd=-4, Agile 3rd=-8 |
| TC-2089-N | PASS | Non-agile 2nd=-5 (not -4) |
| TC-2090-P | PASS | `CombatEngine.php:142` resets `attacks_this_turn => 0` in `startRound` |
| TC-2090-N | PASS | `endTurn` does NOT reset `attacks_this_turn` (only `startRound` does) |
| TC-2091-P | PENDING/GAP | `is_reaction` flag does not exist in `CombatEngine` or any service — reaction attacks cannot be distinguished from normal attacks. MAP increment applies to all attacks regardless of type. |
| TC-2091-N | PASS (pending confirmed) | Confirmed `is_reaction` absent in all dungeoncrawler_content services |
| TC-2092-P | PASS | `RulesEngine.php:435` — `$range_penalty = $increments * -2` |
| TC-2092-N | PASS | Penalty only applied when `distance > $base_range` (line 433) |
| TC-2093 | PENDING/GAP | Maximum effective range = 6× increment NOT computed dynamically. The hard cap uses the weapon's static `range` field, not `6 * range_increment`. REQ-2093 not yet implemented. |
| TC-2094 | PARTIAL PASS | `RulesEngine.php:422` — `distance > $weapon_range` returns `is_valid FALSE`. Attacks beyond `weapon['range']` are blocked. However the cap is static (weapon field), not dynamic 6×increment per PF2e spec. Functional behavior correct, but spec gap exists until REQ-2093 is implemented. |

## GAPs

### GAP-2091 — Reactions do not exclude MAP increment
**File**: `CombatEngine.php` — `resolveAttack` path (line ~462)
**Issue**: All attacks increment `attacks_this_turn`. No `is_reaction` flag exists. Reaction attacks (e.g., Attack of Opportunity) should not increment `attacks_this_turn` or consume MAP, per PF2e rules.
**Severity**: Medium. Once reaction-type actions are introduced (e.g., AoO), players will be penalized incorrectly.
**Inbox status**: REQ-2091 correctly marked "pending" — verify passes only for confirming the absence.

### GAP-2093/2094 — Max effective range not computed as 6× increment
**File**: `RulesEngine.php:420` — `$weapon_range = (int) ($weapon['range'] ?? ...)` used as hard cap
**Issue**: PF2e specifies max effective range = 6 × range_increment. Current code uses a static `range` field from the weapon definition. If `range` ≠ 6 × `range_increment`, the cap is incorrect.
**Severity**: Low in current gameplay (data-driven: correct if weapon data has `range` = 6×increment). Architectural gap: the enforcement should derive from increment, not a separate field.
**REQ-2093** remains not implemented.

## Verification commands used
```bash
cd /var/www/html/dungeoncrawler

# TC-2083/2084: calculateAttackBonus
./vendor/bin/drush ev '$c=\Drupal::service("dungeoncrawler_content.calculator"); echo $c->calculateAttackBonus(4,3,1,0)." ".$c->calculateAttackBonus(4,-1,1,0)." ".$c->calculateAttackBonus(4,2,1,0)." ".$c->calculateAttackBonus(0,2,0,0);'
# → 8 4 7 2

# TC-2085: spell_attack_bonus
grep -n "spell_attack_bonus" src/Service/ActionProcessor.php
# → line 339: $spell_attack_mod = (int) ($caster['spell_attack_bonus'] ?? $caster['level'] ?? 0);

# TC-2086-2089: degree + MAP
./vendor/bin/drush ev '$cc=\Drupal::service("dungeoncrawler_content.combat_calculator"); echo $cc->calculateDegreeOfSuccess(15,15,15)." ".$cc->calculateDegreeOfSuccess(25,15,10)." ".$cc->calculateDegreeOfSuccess(14,15,14)." ".$cc->calculateMultipleAttackPenalty(2,false)." ".$cc->calculateMultipleAttackPenalty(3,false)." ".$cc->calculateMultipleAttackPenalty(2,true)." ".$cc->calculateMultipleAttackPenalty(3,true);'
# → success critical_success failure -5 -10 -4 -8

# TC-2090: startRound resets attacks_this_turn
grep -n "attacks_this_turn" web/modules/custom/dungeoncrawler_content/src/Service/CombatEngine.php
# lines 142, 216, 226: => 0 (in startRound); 462, 475: increment in resolveAttack

# TC-2091: is_reaction absent
grep -rn "is_reaction" web/modules/custom/dungeoncrawler_content/src/
# → no results

# TC-2092: range_penalty in RulesEngine
grep -n "range_penalty\|range_increment" web/modules/custom/dungeoncrawler_content/src/Service/RulesEngine.php
# → lines 432-436: $increments * -2, only when distance > base_range
```
