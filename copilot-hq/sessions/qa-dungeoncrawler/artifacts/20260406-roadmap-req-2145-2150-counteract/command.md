# QA: Counteract Rules (Reqs 2145–2150)

- Agent: qa-dungeoncrawler
- Status: pending
- Priority: high
- Release: ch09-playing-the-game

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Counteract Rules (p.458–459)

## Requirements Covered

| ID   | Req Text                                                                              | Status      |
|------|---------------------------------------------------------------------------------------|-------------|
| 2145 | Counteract check = appropriate modifier + proficiency vs. target DC                  | **pending** |
| 2146 | Counteract level: spells=spell level; other effects=half level (round up)             | **pending** |
| 2147 | Crit success: counteract target level ≤ caster level +3                               | **pending** |
| 2148 | Success: counteract target level ≤ caster level +1                                    | **pending** |
| 2149 | Failure: counteract only if target level < caster counteract level                    | **pending** |
| 2150 | Critical failure: counteract fails entirely                                           | **pending** |

## Test Cases (all pending until CounteractService implemented)

### REQ-2145 — Counteract Check

**Positive:** Counteract check uses spellcasting modifier + proficiency vs. target spell DC.
```php
// TODO: $cs = \Drupal::service('dungeoncrawler_content.counteract_service');
// $result = $cs->attemptCounteract($caster, $target_effect, $encounter_id);
// assert(isset($result['check_total'], $result['degree']));
```

**Negative:** Missing proficiency bonus reduces counteract check total correctly.

---

### REQ-2146 — Counteract Level Calculation

**Positive:** A level 3 spell has counteract level 3. A level 5 monster ability has counteract level 3 (ceil(5/2)).
```php
// TODO: assert($cs->getCounteractLevel('spell', 3) === 3);
// assert($cs->getCounteractLevel('ability', 5) === 3);
```

**Negative:** Counteract level 0 (cantrip) should still be valid (treated as level 1 or 0 per GM ruling).

---

### REQ-2147–2150 — Counteract Degree Outcomes

**Positive:** All four degree outcomes apply correct level comparison.
```php
// TODO: Crit success → can counteract effects up to caster_level + 3
// Success → up to caster_level + 1
// Failure → only if target_level < caster_counteract_level
// Crit failure → cannot counteract
```

**Negative:** Success cannot counteract a target of level caster_level + 2 (requires crit success).
