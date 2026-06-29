# QA: Damage Rules (Reqs 2108–2121)

- Agent: qa-dungeoncrawler
- Status: pending
- Priority: high
- Release: ch09-playing-the-game

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Damage (p.450–453): damage types, critical hits, resistances/immunities/weaknesses, nonlethal

## Requirements Covered

| ID   | Req Text                                                                                             | Status      |
|------|------------------------------------------------------------------------------------------------------|-------------|
| 2108 | All damage types: B/P/S, energy, positive, negative, force, alignment, mental, poison, bleed, precision | implemented |
| 2109 | Melee damage = weapon die + Str modifier                                                             | implemented |
| 2110 | Ranged damage = weapon die only (no Str by default)                                                  | implemented |
| 2111 | Thrown weapons add full Str modifier to damage                                                       | **pending** |
| 2112 | Propulsive ranged: add half Str modifier (positive only)                                             | **pending** |
| 2113 | Spell damage = dice only (no ability mod unless specified)                                           | implemented |
| 2114 | Minimum 1 damage after all reductions (before immunities/resistances)                               | **pending** |
| 2115 | Crit: double dice only; flat bonuses added once after                                                | **pending** |
| 2116 | Apply crit specialization effects on critical hit                                                    | **pending** |
| 2117 | Immunities (negate), weaknesses (+X), resistances (−X min 0)                                        | implemented |
| 2118 | Crit immunity: double-damage → normal damage (other crit effects still apply)                        | **pending** |
| 2119 | Precision immunity: ignores precision damage only                                                    | **pending** |
| 2120 | Nonlethal attacks with lethal weapons: −2 attack roll                                                | **pending** |
| 2121 | Nonlethal 0 HP → unconscious (no dying condition)                                                   | **pending** |

## Test Cases

### REQ-2108 — Damage Types Recognized

**Positive:** HPManager.applyDamage accepts fire, cold, acid, and bludgeoning types and applies resistances correctly.
```php
// Confirm resistance matching works for energy types
$src = file_get_contents('/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/HPManager.php');
assert(strpos($src, 'strtolower') !== FALSE, 'Damage type comparison must be case-insensitive');
assert(strpos($src, 'resistances') !== FALSE, 'Resistance application must exist');
assert(strpos($src, 'weaknesses') !== FALSE, 'Weakness application must exist');
```

**Negative:** Unknown damage type ('psychic') still applies damage without error (open-ended type system).
```php
assert(strpos($src, 'in_array($damage_type') === FALSE, 'Damage type must not be validated against a fixed list');
```

---

### REQ-2109 — Melee Damage Formula

**Positive:** rollDamage called with ability_modifier = Str mod; total includes it.
```php
$calc = \Drupal::service('dungeoncrawler_content.calculator');
// Mock: 1d6 roll returning 4 + Str +3
$result = $calc->rollDamage('1d6', 3);
assert($result['modifier'] === 3, "Str mod should be +3, got {$result['modifier']}");
assert($result['total'] >= 4, 'Total must be at least dice + modifier');
```

**Negative:** Passing 0 Str modifier still gives valid result.
```php
$result = $calc->rollDamage('1d6', 0);
assert($result['modifier'] === 0, 'Zero Str modifier should be 0');
```

---

### REQ-2110 — Ranged Damage (No Str by Default)

**Positive:** rollDamage called with ability_modifier = 0 for standard ranged weapons.
```php
$calc = \Drupal::service('dungeoncrawler_content.calculator');
$result = $calc->rollDamage('1d8', 0);
assert($result['modifier'] === 0, 'Ranged should have 0 ability modifier by default');
```

**Negative:** Passing a modifier to rollDamage still works (caller responsibility).
```php
$result = $calc->rollDamage('1d8', 2);
assert($result['modifier'] === 2, 'Caller can override with modifier (finesse/thrown)');
```

---

### REQ-2111 — Thrown Weapons: Full Str Modifier (PENDING)

**Positive (expected to fail until implemented):** When weapon has 'thrown' trait, calculateAttackBonus caller must add full Str mod to damage (not ranged 0).
```php
// TODO: Verify ItemCombatDataService returns str_mod for thrown weapons in damage context
// $icd = \Drupal::service('dungeoncrawler_content.item_combat_data');
// $weapon = $icd->getWeaponCombatData($item_id);
// assert($weapon['thrown_damage_str_full'] === TRUE, 'Thrown must use full Str');
```

**Negative:** Non-thrown ranged weapons should NOT add Str modifier to damage.

---

### REQ-2112 — Propulsive: Half Str (Positive Only) (PENDING)

**Positive (expected to fail until implemented):** Weapon with 'propulsive' trait adds floor(Str/2) to damage when Str > 0; subtracts nothing when Str < 0.
```php
// TODO: Verify propulsive str mod calculation
```

**Negative:** Negative Str mod does NOT reduce propulsive weapon damage.

---

### REQ-2113 — Spell Damage: Dice Only

**Positive:** Spell damage rolls are made without ability modifier (ActionProcessor passes 0 or no modifier for spell damage dice).
```php
$src = file_get_contents('/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/ActionProcessor.php');
assert(strpos($src, 'rollExpression($damage_dice)') !== FALSE, 'Spell damage uses rollExpression (no ability mod)');
```

**Negative:** Melee attack damage path uses rollDamage with ability modifier, distinguishing it from spell path.
```php
assert(strpos($src, 'rollDamage') !== FALSE || strpos($src, 'ability_modifier') !== FALSE,
  'Melee attack path should reference ability modifier separately');
```

---

### REQ-2114 — Minimum 1 Damage (PENDING)

**Positive (expected to fail until implemented):** After resistances reduce damage to 0, result should be 1 (not 0).
```php
// TODO: After fix, verify:
// applyDamage with resistance=5 vs damage=4 → final_damage=1 (not 0)
```

**Negative (current behavior):** Current code returns 0 when resistance exceeds damage.
```php
$src = file_get_contents('/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/HPManager.php');
assert(strpos($src, 'max(1,') === FALSE, 'Min-1 not yet enforced (req 2114 pending)');
```

---

### REQ-2115 — Critical Hit: Double Dice, Static Once (PENDING)

**Positive (expected to fail until implemented):** Crit damage = (dice_total × 2) + static_mod (not (dice + static) × 2).
```php
$calc = \Drupal::service('dungeoncrawler_content.calculator');
// dice=[4,3]=7, static=3 → correct crit = 7*2 + 3 = 17, current wrong = (7+3)*2 = 20
$result = $calc->applyCriticalDamage([4, 3], 3);
// Current implementation: (7+3)*2=20 — WRONG
assert($result['doubled_total'] === 17, "Crit should be dice×2+static=17, got {$result['doubled_total']}");
```

**Negative:** With static=0, result should be dice×2.
```php
$result = $calc->applyCriticalDamage([4, 3], 0);
assert($result['doubled_total'] === 14, "Crit with no static: 7×2=14");
```

---

### REQ-2116 — Critical Specialization Effects (PENDING)

**Positive (expected to fail until implemented):** On critical hit, weapon's crit specialization effect is triggered (e.g., slashing = bleed, bludgeoning = prone).
```php
// TODO: Verify crit specialization triggers after successful crit hit resolution
```

**Negative:** Non-critical hits should NOT trigger crit specialization.

---

### REQ-2117 — Immunities, Weaknesses, Resistances

**Positive:** Resistance reduces damage by the resistance value (min 0). Weakness adds the value.
```php
$src = file_get_contents('/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/HPManager.php');
assert(strpos($src, '$damage = max(0, $damage - (int) ($r[\'value\'] ?? 0))') !== FALSE, 'Resistance must reduce damage min 0');
assert(strpos($src, '$damage += (int) ($w[\'value\'] ?? 0)') !== FALSE, 'Weakness must add to damage');
```

**Negative (immunity via RulesEngine):** Target with immunity to 'fire' blocks the action entirely.
```php
$src = file_get_contents('/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/RulesEngine.php');
assert(strpos($src, 'checkImmunities') !== FALSE, 'Immunity check must exist in RulesEngine');
assert(strpos($src, "'is_immune' => TRUE") !== FALSE, 'Immune result must block action');
```

---

### REQ-2118 — Critical Hit Immunity (PENDING)

**Positive (expected to fail until implemented):** Creature with 'critical_hits' immunity takes normal (not doubled) damage on a critical hit; other crit effects still apply.
```php
// TODO: Verify crit immunity check in CombatEngine.resolveAttack path
```

**Negative:** Non-immune creature still takes doubled damage on crit.

---

### REQ-2119 — Precision Immunity (PENDING)

**Positive (expected to fail until implemented):** Precision immunity strips precision damage component from total but applies all other damage.
```php
// TODO: Verify precision damage is tracked separately and stripped on precision immunity
```

---

### REQ-2120 — Nonlethal Attack Penalty (PENDING)

**Positive (expected to fail until implemented):** Attack with nonlethal flag on a lethal weapon takes −2 to attack roll.
```php
// TODO: Verify calculateAttackBonus or resolveAttack accepts nonlethal flag and applies -2
```

**Negative:** Normal (lethal) attacks should have no penalty.

---

### REQ-2121 — Nonlethal 0 HP → Unconscious, Not Dying (PENDING)

**Positive (expected to fail until implemented):** HPManager.applyDamage with nonlethal flag should apply 'unconscious' condition at 0 HP instead of 'dying 1'.
```php
// TODO: After implementation:
// applyDamage(..., is_nonlethal: TRUE) → condition='unconscious', NOT 'dying'
```

**Negative:** Lethal damage still applies dying condition at 0 HP.
```php
$src = file_get_contents('/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/HPManager.php');
assert(strpos($src, "'dying', 1") !== FALSE, 'Lethal damage must apply dying 1 at 0 HP');
```
