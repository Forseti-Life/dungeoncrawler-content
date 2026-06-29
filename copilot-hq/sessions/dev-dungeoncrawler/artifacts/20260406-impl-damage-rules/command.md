# Implement: Damage Rules — Thrown/Propulsive Str, Min-1, Crit Doubling Fix, Crit Specialization, Nonlethal

- Release: ch09-playing-the-game
- Feature: dc-cr-damage-rules
- Status: pending
- Priority: high
- Agent: dev-dungeoncrawler

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Damage (p.450–453): critical hits, thrown/propulsive, nonlethal, immunities

## Requirements

| ID   | Req                                                                                      |
|------|------------------------------------------------------------------------------------------|
| 2111 | Thrown weapons add full Str modifier to damage                                           |
| 2112 | Propulsive ranged: half Str (positive only)                                              |
| 2114 | Minimum 1 damage after all reductions (before immunities)                               |
| 2115 | Crit: double dice only; flat bonuses added once after                                    |
| 2116 | Apply weapon/spell crit specialization on critical hit                                   |
| 2118 | Crit immunity: normalize double-damage to normal; other crit effects remain              |
| 2119 | Precision immunity: ignore precision component only                                      |
| 2120 | Nonlethal with lethal weapon: −2 attack roll                                             |
| 2121 | Nonlethal 0 HP → unconscious (no dying condition)                                       |

## What Exists

- `Calculator.php`: `applyCriticalDamage(base_damage_rolls, static_modifiers)` exists but uses wrong formula `(dice + static) * 2` instead of `dice * 2 + static`.
- `CombatEngine.php`: `$base_damage * 2` on crit — same wrong formula (pre-calculated total doubled).
- `HPManager.php`: `max(0, ...)` on resistance reduction — allows 0 damage through (should be min 1 before immunity/resistance stage).
- `ItemCombatDataService.php`: detects 'thrown' trait via regex but does not return a Str modifier flag for damage.
- No nonlethal flag exists in any attack call path.
- No crit specialization trigger exists anywhere.

## Required Changes

### 1. Fix `applyCriticalDamage` in `Calculator.php` (req 2115)

Current (wrong):
```php
$doubled_total = max(0, ($dice_sum + $static) * 2);
```

Fix:
```php
$doubled_total = max(0, ($dice_sum * 2) + $static);
```

### 2. Fix crit damage in `CombatEngine.resolveAttack()` and `ActionProcessor` (req 2115)

Both currently use `$base_damage * 2` where `$base_damage` already includes ability_mod.

Fix: track dice total and static total separately before combining:
```php
// Before: $damage_result = $this->calculator->rollDamage($weapon['damage_dice'], $ability_mod);
//         $base_damage = $damage_result['total'];
//         $damage_dealt = ($degree === 'critical_success') ? $base_damage * 2 : $base_damage;
//
// After:
$damage_result = $this->calculator->rollDamage($weapon['damage_dice'], 0); // dice only
$dice_total = $damage_result['total'];
$static_mod = $ability_mod + ($item_bonus ?? 0); // all flat bonuses
if ($degree === 'critical_success') {
  $damage_dealt = $this->calculator->applyCriticalDamage($damage_result['rolls'], $static_mod)['doubled_total'];
} else {
  $damage_dealt = $dice_total + $static_mod;
}
```

### 3. Add Thrown/Propulsive Str handling to `ItemCombatDataService` (reqs 2111, 2112)

Return `'damage_str_mode'` in weapon combat data:
- `'full'` for thrown weapons
- `'half_positive'` for propulsive weapons  
- `'none'` for all other ranged weapons

Callers (ActionProcessor, CombatEngine) should apply Str mod based on this flag.

### 4. Minimum 1 Damage in `HPManager.applyDamage()` (req 2114)

After resistances are applied, before applying to HP:
```php
if ($damage > 0) {
  $damage = max(1, $damage); // PF2e: minimum 1 after reductions (before immunities)
}
```
Note: if the attack was blocked entirely (immunity = no damage), leave at 0.

### 5. Nonlethal Attack Support (reqs 2120, 2121)

In `CombatEngine.resolveAttack()` and `ActionProcessor`:
- Accept `$is_nonlethal` flag in weapon data
- Apply −2 to attack roll when `is_nonlethal === TRUE`

In `HPManager.applyDamage()`:
- Accept `$is_nonlethal` flag in `$source` metadata
- At 0 HP with nonlethal: apply `'unconscious'` condition instead of `'dying 1'`

### 6. Critical Specialization Trigger (req 2116)

Add a `CritSpecializationService` or method that maps weapon category to effect:
- Bludgeoning: target knocked prone (Reflex save to avoid)
- Slashing: persistent bleed damage (amount = die size)
- Piercing: target frightened 1
- etc.

Trigger on `$degree === 'critical_success'` after damage is applied.

### 7. Critical Hit Immunity and Precision Immunity (reqs 2118, 2119)

In `RulesEngine.checkImmunities()`:
- Add `'critical_hits'` as a recognized immunity type
- When target is immune to critical hits: downgrade crit success to normal success before damage calculation
- `'precision'` immunity: strip precision damage component from total (precision damage must be tracked separately in damage result)

## Acceptance Criteria

1. `applyCriticalDamage([4,3], 3)` returns `doubled_total=17` (not 20)
2. Weapon with 'thrown' trait adds full Str to damage
3. Weapon with 'propulsive' adds half Str (min 0) to damage
4. `applyDamage` with resistance=5 vs damage=4 → final_damage=1
5. Nonlethal attack: -2 to attack roll
6. Nonlethal 0 HP: unconscious, no dying condition
7. Critical hit triggers crit specialization effect
8. 'critical_hits' immunity normalizes double → normal damage
9. Precision immunity strips precision component

## Definition of Done
- [ ] `applyCriticalDamage` formula fixed
- [ ] CombatEngine and ActionProcessor use dice-separate crit path
- [ ] Thrown/propulsive Str modes returned by ItemCombatDataService
- [ ] HPManager enforces min-1 damage
- [ ] Nonlethal flag wired through attack path
- [ ] Crit specialization stub implemented
- [ ] Crit and precision immunity handled in RulesEngine
- [ ] QA evidence updated for reqs 2111–2121
- [ ] `dc_requirements` reqs updated to `implemented` via MySQL
