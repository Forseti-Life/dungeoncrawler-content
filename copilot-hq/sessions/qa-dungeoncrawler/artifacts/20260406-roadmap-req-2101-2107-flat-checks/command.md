# QA: Flat Checks (Reqs 2101–2107)

- Agent: qa-dungeoncrawler
- Status: pending
- Priority: high
- Release: ch09-playing-the-game

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Flat Checks (p.450), Fortune/Misfortune effects (p.451), Secret Checks (p.450)

## Requirements Covered

| ID   | Req Text                                                                                     | Status      |
|------|----------------------------------------------------------------------------------------------|-------------|
| 2101 | Flat checks use d20 with no added modifiers                                                  | implemented |
| 2102 | DC ≤ 1 = auto-success; DC ≥ 21 = auto-failure                                               | **pending** |
| 2103 | Common uses: hidden=DC11, concealed=DC5, persistent damage=DC15 (DC10 with help)            | **pending** |
| 2104 | Secret checks rolled by GM; player sees only what character perceives                        | **pending** |
| 2105 | Fortune: roll twice, use higher result                                                       | **pending** |
| 2106 | Misfortune: roll twice, use lower result                                                     | **pending** |
| 2107 | Fortune and misfortune cancel; only one of each applies                                      | **pending** |

## Test Cases

### REQ-2101 — Flat Check Mechanics (d20 only)

**Positive:** Persistent damage flat check uses raw d20 ≥ 15 (no modifiers added).
```php
$engine = \Drupal::service('dungeoncrawler_content.combat_engine');
$src = file_get_contents((new ReflectionClass($engine))->getFileName());
assert(strpos($src, 'rollPathfinderDie(20)') !== FALSE, 'Flat check must roll d20');
assert(strpos($src, '$flat_check >= 15') !== FALSE, 'Persistent damage must use DC 15 flat check');
// Verify no modifier is added to the flat_check roll
$pos = strpos($src, 'flat_check');
$block = substr($src, $pos - 50, 200);
assert(strpos($block, '+ $') === FALSE || strpos($block, 'modifier') === FALSE,
  'Flat check must not add modifiers to the roll');
```

**Negative:** Regular skill checks DO add modifiers — flat checks must be distinct.
```php
$calc = \Drupal::service('dungeoncrawler_content.calculator');
$skill = $calc->rollSkillCheck(3, 4); // has modifier
assert($skill['modifier'] === 7, 'Skill check uses modifier; flat check does not');
```

---

### REQ-2102 — DC Bounds (PENDING)

**Positive (expected to fail until implemented):** DC 0 or 1 should be auto-success without rolling.
```php
// TODO: After implementation, verify:
// rollFlatCheck(dc: 1) → result = 'success' without rolling
// rollFlatCheck(dc: 21) → result = 'failure' without rolling
```

**Negative:** DC 2 through 20 should require an actual roll.
```php
// TODO: DC 10 must roll d20; not auto-resolved
```

---

### REQ-2103 — Common Flat Check DCs (PENDING)

**Positive (expected to fail until implemented):**
- Targeting a hidden creature must trigger a DC 11 flat check before the attack resolves.
- Targeting a concealed creature must trigger a DC 5 flat check.
- Ending persistent damage must use DC 15 (or DC 10 if assisted).

```php
// Persistent damage DC 15 is currently implemented — verify:
$engine = \Drupal::service('dungeoncrawler_content.combat_engine');
$src = file_get_contents((new ReflectionClass($engine))->getFileName());
assert(strpos($src, '$flat_check >= 15') !== FALSE, 'Persistent damage DC 15 exists');
// Hidden (DC 11) and Concealed (DC 5) — not yet implemented; verify absence:
$re = \Drupal::service('dungeoncrawler_content.rules_engine');
$re_src = file_get_contents((new ReflectionClass($re))->getFileName());
assert(strpos($re_src, 'DC.*11') === FALSE || strpos($re_src, 'hidden') === FALSE,
  'Hidden DC 11 flat check not yet implemented (req 2103 pending)');
```

**Negative:** Attack against a visible, non-hidden creature should NOT trigger a flat check.

---

### REQ-2104 — Secret Checks (PENDING)

**Positive (expected to fail until implemented):** Checks flagged as secret should be rolled server-side with result hidden from the player's response.
```php
// TODO: Verify API response omits roll details when check is marked secret
// Expected: response includes only pass/fail outcome, not the roll value
```

**Negative:** Non-secret checks should include full roll details in response.

---

### REQ-2105 — Fortune: Roll Twice, Use Higher (PENDING)

**Positive (expected to fail until implemented):** When a Fortune effect is active, check rolls d20 twice and returns the higher.
```php
// TODO: After implementation:
// $calc->rollWithFortune('flat', $dc) → rolls 2d20, takes max
```

**Negative:** Without Fortune, only one d20 is rolled.

---

### REQ-2106 — Misfortune: Roll Twice, Use Lower (PENDING)

**Positive (expected to fail until implemented):** When Misfortune is active, roll twice and use the lower result.
```php
// TODO: $calc->rollWithMisfortune('flat', $dc) → rolls 2d20, takes min
```

**Negative:** Without Misfortune, only one d20 is rolled.

---

### REQ-2107 — Fortune/Misfortune Cancel Each Other (PENDING)

**Positive (expected to fail until implemented):** If both Fortune and Misfortune apply, treat as neither (single d20).
```php
// TODO: Verify resolver detects both flags and falls back to normal single roll
```

**Negative:** Two Fortune effects do NOT stack to give three dice — only one Fortune applies at a time.
