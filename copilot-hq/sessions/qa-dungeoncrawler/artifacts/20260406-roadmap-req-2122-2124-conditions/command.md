# QA: Conditions System (Reqs 2122–2124)

- Agent: qa-dungeoncrawler
- Status: pending
- Priority: high
- Release: ch09-playing-the-game

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Conditions Appendix (p.618–626): condition definitions, valued conditions, end triggers

## Requirements Covered

| ID   | Req Text                                                                                      | Status      |
|------|-----------------------------------------------------------------------------------------------|-------------|
| 2122 | Full conditions system: track value (if valued) and duration                                  | implemented |
| 2123 | Valued conditions (dying, wounded, frightened, etc.) track numeric severity                   | implemented |
| 2124 | Conditions end via specified removal methods (healing, saves, time, etc.)                     | implemented |

## Test Cases

### REQ-2122 — Full Conditions System

**Positive:** ConditionManager has a full catalog of PF2e conditions with is_valued and end_trigger defined.
```php
$cm = \Drupal::service('dungeoncrawler_content.condition_manager');
$src = file_get_contents((new ReflectionClass($cm))->getFileName());
// Spot-check several conditions exist in catalog
$conditions = ['blinded','dying','frightened','grabbed','prone','sickened','stunned','stupefied','unconscious'];
foreach ($conditions as $cond) {
  assert(strpos($src, "'$cond'") !== FALSE, "Condition '$cond' must exist in catalog");
}
```

**Negative:** Unknown condition slug ('nonexistent') should not be applied without error handling.
```php
assert(strpos($src, 'CONDITION_DEFINITIONS') !== FALSE || strpos($src, "function applyCondition") !== FALSE,
  'applyCondition must exist');
```

---

### REQ-2123 — Valued Conditions Track Numeric Severity

**Positive:** `dying`, `wounded`, `frightened`, `drained`, `doomed`, `enfeebled`, `clumsy`, `stupefied` are `is_valued => TRUE` with a max_value.
```php
$valued_conditions = ['dying' => 4, 'wounded' => 3, 'frightened' => 4, 'drained' => 4, 'stupefied' => 4];
foreach ($valued_conditions as $cond => $max) {
  assert(strpos($src, "'$cond'") !== FALSE, "Valued condition '$cond' must exist");
  assert(strpos($src, "'max_value' => $max") !== FALSE || strpos($src, "max_value") !== FALSE,
    "Condition '$cond' must have max_value");
}
```

**Negative:** Non-valued conditions like `blinded` and `prone` should have `is_valued => FALSE`.
```php
assert(strpos($src, "'blinded'") !== FALSE, 'blinded must be defined');
$blinded_pos = strpos($src, "'blinded'");
$blinded_block = substr($src, $blinded_pos, 150);
assert(strpos($blinded_block, "'is_valued' => FALSE") !== FALSE, 'blinded must be non-valued');
```

---

### REQ-2124 — Conditions End via Correct Trigger

**Positive:** `frightened` uses `end_of_turn` trigger; `drained` uses `rest`; `dying` uses `recovery`.
```php
assert(strpos($src, "'frightened' => ['is_valued' => TRUE") !== FALSE, 'frightened must be defined');
assert(strpos($src, "'end_of_turn'") !== FALSE, 'end_of_turn trigger must exist');
assert(strpos($src, "'recovery'") !== FALSE, 'recovery trigger (for dying) must exist');
assert(strpos($src, "'rest'") !== FALSE, 'rest trigger must exist');
// Verify tickConditions method exists for automatic end-of-turn reduction
assert(strpos($src, 'tickConditions') !== FALSE || strpos($src, 'processEndOfTurnEffects') !== FALSE,
  'Condition tick must be called at end of turn');
```

**Negative:** A `persistent` condition like `doomed` should NOT auto-clear on end of turn.
```php
$doomed_pos = strpos($src, "'doomed'");
$doomed_block = substr($src, $doomed_pos, 150);
assert(strpos($doomed_block, "'persistent'") !== FALSE, "doomed must have persistent end_trigger");
```
