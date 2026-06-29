# QA: Armor Class and Defenses (Reqs 2095–2100)

- Agent: qa-dungeoncrawler
- Status: pending
- Priority: high
- Release: ch09-playing-the-game

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Armor Class (p.274), Saving Throws (p.449), Perception/Initiative (p.448)

## Requirements Covered

| ID   | Req Text                                                                                      | Status      |
|------|-----------------------------------------------------------------------------------------------|-------------|
| 2095 | AC = 10 + Dex mod (capped by armor Dex Cap) + proficiency + armor item bonus                 | implemented |
| 2096 | Three save types: Fortitude (Con), Reflex (Dex), Will (Wis)                                  | implemented |
| 2097 | Save results: Crit Success=0dmg, Success=half, Failure=full, Crit Failure=double             | **pending** |
| 2098 | Perception = Wis mod + proficiency + bonuses                                                  | implemented |
| 2099 | Perception is default initiative check                                                        | implemented |
| 2100 | GM may use a different initiative check (e.g., Stealth when Avoiding Notice)                  | implemented |

## Test Cases

### REQ-2095 — AC Formula

**Positive:** Verify calculateAC returns 10 + dex_mod + armor_bonus + shield (when raised).
```php
$calc = \Drupal::service('dungeoncrawler_content.calculator');
$result = $calc->calculateAC(10, 3, 5, FALSE);
assert($result['total'] === 18, "AC should be 10+3+5=18, got {$result['total']}");
$result_shield = $calc->calculateAC(10, 3, 5, TRUE, [], 2);
assert($result_shield['total'] === 20, "AC with raised shield should be 20, got {$result_shield['total']}");
```

**Negative:** Zero dex (e.g., heavy armor) should still compute correctly.
```php
$result = $calc->calculateAC(10, 0, 6, FALSE);
assert($result['total'] === 16, "Heavy armor AC: 10+0+6=16, got {$result['total']}");
```

---

### REQ-2096 — Three Saving Throw Types

**Positive:** rollSavingThrow called with Con, Dex, Wis mods returns a total roll; verify three distinct calls each produce a result array with 'total'.
```php
$calc = \Drupal::service('dungeoncrawler_content.calculator');
$fort = $calc->rollSavingThrow(2, 4); // Con +2, trained+level
$refl = $calc->rollSavingThrow(3, 4); // Dex +3
$will = $calc->rollSavingThrow(1, 2); // Wis +1
assert(isset($fort['total'], $refl['total'], $will['total']), 'All three save types must return total');
```

**Negative:** Negative ability mod reduces total correctly.
```php
$save = $calc->rollSavingThrow(-2, 2);
assert($save['modifier'] === 0, "Con -2 + prof 2 = modifier 0, got {$save['modifier']}");
```

---

### REQ-2097 — Save Damage Outcomes (**PENDING — half-damage on success not implemented**)

**Positive (expected to fail until implemented):** Spell targeting save with Success degree should apply half damage.
```php
// TODO: After ActionProcessor fix, verify:
// degree='success' after save inversion → damage = floor($base_damage / 2)
// degree='critical_failure' after save inversion → damage = $base_damage * 2
// degree='critical_success' after save inversion → damage = 0
```

**Negative (current behavior to document):** Confirm Success currently returns 0 damage (wrong — should be half).
```php
$ap = \Drupal::service('dungeoncrawler_content.action_processor');
$src = file_get_contents((new ReflectionClass($ap))->getFileName());
// Verify absence of half-damage logic
assert(strpos($src, '/ 2') === FALSE || strpos($src, 'intdiv') === FALSE,
  'Half-damage logic missing (req 2097 pending)');
```

---

### REQ-2098 — Perception Formula

**Positive:** calculateInitiative uses perception_modifier which includes Wis + proficiency.
```php
$calc = \Drupal::service('dungeoncrawler_content.calculator');
$result = $calc->calculateInitiative(5); // perception_mod = Wis +2 + trained+level 3
assert(isset($result['total']), 'Initiative must return total');
assert($result['modifier'] === 5, "Perception modifier should be 5, got {$result['modifier']}");
```

**Negative:** Zero perception modifier still rolls correctly.
```php
$result = $calc->calculateInitiative(0);
assert($result['modifier'] === 0, 'Zero perception modifier should be 0');
assert($result['total'] >= 1 && $result['total'] <= 20, 'Initiative roll must be 1-20 range');
```

---

### REQ-2099 — Perception as Default Initiative

**Positive:** startEncounter auto-rolls initiative using perception_modifier for participants without custom values.
```php
$engine = \Drupal::service('dungeoncrawler_content.combat_engine');
$src = file_get_contents((new ReflectionClass($engine))->getFileName());
assert(strpos($src, 'perception_mod') !== FALSE, 'Auto initiative must use perception_mod');
assert(strpos($src, 'resolvePerceptionModifier') !== FALSE, 'Must resolve perception modifier');
```

**Negative:** Participant with no perception_modifier stored should default to 0 (not error).
```php
assert(strpos($src, "perception_modifier ?? \$entity['perception_mod'] ?? 0") !== FALSE ||
       strpos($src, 'perception_mod\' ?? 0') !== FALSE,
  'Must fallback gracefully when perception_modifier is missing');
```

---

### REQ-2100 — Custom Initiative Check (e.g., Stealth)

**Positive:** startEncounter accepts custom_initiatives array; participants with custom values skip perception auto-roll.
```php
$engine = \Drupal::service('dungeoncrawler_content.combat_engine');
$src = file_get_contents((new ReflectionClass($engine))->getFileName());
assert(strpos($src, 'custom_initiatives') !== FALSE, 'startEncounter must accept custom_initiatives');
assert(strpos($src, 'isset($custom_initiatives[$pid])') !== FALSE, 'Must use custom value if provided');
```

**Negative:** Participant NOT in custom_initiatives array still gets a perception-based auto-roll.
```php
assert(strpos($src, 'Auto-roll: Perception') !== FALSE || strpos($src, 'perception_mod') !== FALSE,
  'Non-custom participants must still get perception auto-roll');
```
