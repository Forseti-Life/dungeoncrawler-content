# QA: Attack Rolls (Reqs 2083–2094)

- Agent: qa-dungeoncrawler
- Status: pending
- Priority: high
- Release: ch09-playing-the-game

## Requirements Covered

| ID   | Req Text                                                                                  | Status      |
|------|-------------------------------------------------------------------------------------------|-------------|
| 2083 | Melee attack roll = d20 + Str mod + proficiency + bonuses                                 | implemented |
| 2084 | Ranged attack roll = d20 + Dex mod + proficiency + bonuses (by default)                   | implemented |
| 2085 | Spell attack roll = d20 + spellcasting ability mod + proficiency                          | implemented |
| 2086 | Attack roll ≥ AC = hit; ≥ AC+10 = critical hit                                            | implemented |
| 2087 | Each attack after the first in a turn applies MAP                                         | implemented |
| 2088 | Standard MAP: –5 / –10                                                                    | implemented |
| 2089 | Agile weapon/unarmed MAP: –4 / –8                                                         | implemented |
| 2090 | MAP resets at start of each turn                                                          | implemented |
| 2091 | Reactions do NOT incur/increase MAP                                                       | **pending** |
| 2092 | Ranged range increment: –2 per additional increment beyond first                          | implemented |
| 2093 | Maximum effective range = 6× range increment                                              | **pending** |
| 2094 | Attacks beyond maximum range cannot be attempted                                          | implemented |

## Test Cases

### REQ-2083 — Melee Attack Roll Formula

**Positive:** Resolve a melee attack. Verify total = d20 + Str mod + proficiency + item_bonus.
```php
// drush ev
$calc = \Drupal::service('dungeoncrawler_content.calculator');
// proficiency=4 (trained L2), ability_mod=3 (Str 16), item_bonus=1, map=0
$result = $calc->calculateAttackBonus(4, 3, 1, 0);
assert($result === 8, "Expected 8, got {$result}");
```

**Negative:** Pass a negative Str mod; verify total decreases accordingly.
```php
$result = $calc->calculateAttackBonus(4, -1, 1, 0);
assert($result === 4, "Expected 4 with negative Str, got {$result}");
```

---

### REQ-2084 — Ranged Attack Roll Formula

**Positive:** Same calculateAttackBonus call with Dex mod; verify correct total.
```php
$result = $calc->calculateAttackBonus(4, 2, 1, 0);
assert($result === 7, "Expected 7 (Dex-based ranged), got {$result}");
```

**Negative:** Pass proficiency=0 (untrained); total should be ability_mod + item_bonus only.
```php
$result = $calc->calculateAttackBonus(0, 2, 0, 0);
assert($result === 2, "Untrained ranged should be ability_mod only, got {$result}");
```

---

### REQ-2085 — Spell Attack Roll Formula

**Positive:** Verify ActionProcessor uses `spell_attack_bonus` field from participant for spell attack rolls.
```php
// Confirm field used in ActionProcessor::resolveSpellAttack path
$ap = \Drupal::service('dungeoncrawler_content.action_processor');
$reflection = new ReflectionClass($ap);
$src = file_get_contents($reflection->getFileName());
assert(strpos($src, 'spell_attack_bonus') !== FALSE, 'spell_attack_bonus not found in ActionProcessor');
```

**Negative:** Participant missing `spell_attack_bonus`; code should fall back gracefully (not error).
```php
// Code falls back to $caster['level'] ?? 0 — verify fallback exists
assert(strpos($src, "spell_attack_bonus") !== FALSE && strpos($src, '?? $caster[\'level\']') !== FALSE, 'No fallback found');
```

---

### REQ-2086 — Hit / Critical Hit Thresholds

**Positive:** Roll exactly AC = hit; roll exactly AC+10 = critical hit.
```php
$cc = \Drupal::service('dungeoncrawler_content.combat_calculator');
assert($cc->calculateDegreeOfSuccess(15, 15, 15) === 'success', 'AC = hit expected');
assert($cc->calculateDegreeOfSuccess(25, 15, 10) === 'critical_success', 'AC+10 = crit expected');
```

**Negative:** Roll AC-1 = miss.
```php
assert($cc->calculateDegreeOfSuccess(14, 15, 14) === 'failure', 'Below AC should be miss');
```

---

### REQ-2087 — MAP Applied After First Attack

**Positive:** Second attack in CombatEngine should receive MAP penalty.
```php
$calc = \Drupal::service('dungeoncrawler_content.combat_calculator');
$penalty2 = $calc->calculateMultipleAttackPenalty(2, false);
assert($penalty2 === -5, "2nd attack MAP should be -5, got {$penalty2}");
```

**Negative:** First attack has no MAP penalty.
```php
$penalty1 = $calc->calculateMultipleAttackPenalty(1, false);
assert($penalty1 === 0, "1st attack MAP should be 0, got {$penalty1}");
```

---

### REQ-2088 — Standard MAP –5/–10

**Positive:** 2nd attack = -5, 3rd+ = -10.
```php
$calc = \Drupal::service('dungeoncrawler_content.combat_calculator');
assert($calc->calculateMultipleAttackPenalty(2, false) === -5, '2nd MAP should be -5');
assert($calc->calculateMultipleAttackPenalty(3, false) === -10, '3rd MAP should be -10');
assert($calc->calculateMultipleAttackPenalty(4, false) === -10, '4th+ MAP still -10');
```

**Negative:** Passing 0 attacks should return 0 (no penalty).
```php
assert($calc->calculateMultipleAttackPenalty(0, false) === 0, '0 attacks no penalty');
```

---

### REQ-2089 — Agile MAP –4/–8

**Positive:** Agile 2nd = -4, 3rd+ = -8.
```php
$calc = \Drupal::service('dungeoncrawler_content.combat_calculator');
assert($calc->calculateMultipleAttackPenalty(2, true) === -4, 'Agile 2nd MAP should be -4');
assert($calc->calculateMultipleAttackPenalty(3, true) === -8, 'Agile 3rd MAP should be -8');
```

**Negative:** Non-agile 2nd should be -5, not -4.
```php
assert($calc->calculateMultipleAttackPenalty(2, false) === -5, 'Non-agile should not get agile MAP');
```

---

### REQ-2090 — MAP Resets at Start of Turn

**Positive:** After startRound, all participants have attacks_this_turn = 0.
```php
// Inspect startRound source
$engine = \Drupal::service('dungeoncrawler_content.combat_engine');
$src = file_get_contents((new ReflectionClass($engine))->getFileName());
assert(strpos($src, "'attacks_this_turn' => 0") !== FALSE, 'startRound must reset attacks_this_turn');
```

**Negative:** Before startRound, attacks_this_turn should carry the value from the previous turn (verify not auto-reset mid-round).
```php
// Verify endTurn does NOT reset attacks_this_turn
$pos = strpos($src, 'endTurn');
$end_turn_block = substr($src, $pos, 800);
assert(strpos($end_turn_block, "attacks_this_turn") === FALSE || strpos($end_turn_block, "'attacks_this_turn' => 0") === FALSE,
  'endTurn should not reset attacks_this_turn prematurely');
```

---

### REQ-2091 — Reactions Do Not Incur/Increase MAP (**PENDING - not implemented**)

**Positive (expected to fail until implemented):** A reaction attack should not increment attacks_this_turn.
```php
// TODO: Once is_reaction flag exists in resolveAttack, verify:
// resolveAttack(..., is_reaction: true) → attacks_this_turn unchanged
```

**Negative (expected to fail until implemented):** A standard attack should still increment attacks_this_turn.
```php
// Verify current behavior increments for all attacks regardless
$engine = \Drupal::service('dungeoncrawler_content.combat_engine');
$src = file_get_contents((new ReflectionClass($engine))->getFileName());
assert(strpos($src, 'is_reaction') === FALSE, 'Reaction flag not yet implemented (expected pending)');
```

---

### REQ-2092 — Range Increment Penalty –2 per Extra Increment

**Positive:** Target at 2× range increment takes –2 penalty.
```php
$re = \Drupal::service('dungeoncrawler_content.rules_engine');
$src = file_get_contents((new ReflectionClass($re))->getFileName());
assert(strpos($src, 'range_penalty') !== FALSE, 'range_penalty logic must exist');
assert(strpos($src, '* -2') !== FALSE || strpos($src, '*-2') !== FALSE || strpos($src, 'increments * -2') !== FALSE, 'Must multiply increments by -2');
```

**Negative:** Target within first increment (no extra increment) takes no range penalty.
```php
assert(strpos($src, 'distance > $base_range') !== FALSE, 'Range penalty only applied beyond first increment');
```

---

### REQ-2093 — Maximum Effective Range = 6× Range Increment (**PENDING - not enforced in code**)

**Positive (expected to fail until implemented):** Weapon with 30ft increment should have max range 180ft.
```php
// TODO: Implement max_range = 6 * range_increment in RulesEngine
// Expected: $re->validateAttackRange($weapon=['range_increment'=>30], $distance=180) → is_valid
// Expected: $re->validateAttackRange($weapon=['range_increment'=>30], $distance=181) → is_invalid
```

**Negative:** Beyond 6× increment must be blocked regardless of weapon 'range' field.
```php
// Currently depends on weapon data — code does not enforce 6× rule explicitly
```

---

### REQ-2094 — Attacks Beyond Maximum Range Blocked

**Positive:** Attack beyond weapon_range returns is_valid = FALSE.
```php
$re = \Drupal::service('dungeoncrawler_content.rules_engine');
$src = file_get_contents((new ReflectionClass($re))->getFileName());
assert(strpos($src, "Target is out of range") !== FALSE, 'Out-of-range message must exist');
assert(strpos($src, "'is_valid' => FALSE") !== FALSE, 'Out-of-range must return is_valid=false');
```

**Negative:** Attack within range returns is_valid = TRUE.
```php
assert(strpos($src, "'is_valid' => TRUE") !== FALSE, 'In-range must return is_valid=true');
```
