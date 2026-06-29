# Implement: Counteract Rules

- Release: ch09-playing-the-game
- Feature: dc-cr-counteract-rules
- Status: pending
- Priority: high
- Agent: dev-dungeoncrawler

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Counteract Rules (p.458–459)

## Requirements: 2145–2150 (all pending)

## What Exists
- `FeatEffectManager.php`: `'counterspell'` case returns an action stub but performs no counteract check
- No `CounteractService` exists; no counteract check logic anywhere

## Required Implementation

### Create `CounteractService.php`

```php
/**
 * Attempt to counteract an ongoing effect or spell.
 *
 * @param array $caster    Participant with spell_attack_bonus, level
 * @param array $target_effect  ['level'=>int, 'type'=>'spell'|'ability'|'creature', 'effect_id'=>...]
 * @param int $encounter_id
 * @return array ['check_total', 'degree', 'counteract_level', 'target_level', 'success'=>bool]
 */
public function attemptCounteract(array $caster, array $target_effect, int $encounter_id): array;

/**
 * Compute counteract level for an effect.
 * Spells: use spell level directly.
 * Other effects/creatures: ceil(level / 2).
 */
public function getCounteractLevel(string $type, int $level): int;
```

### Degree Logic

```php
$caster_counteract_level = $this->getCounteractLevel('spell', $caster['spell_level']);
$target_level = $this->getCounteractLevel($target_effect['type'], $target_effect['level']);
$check = d20 + spellcasting_mod + proficiency;
$degree = $this->calculator->calculateDegreeOfSuccess($check, $target_dc);

$can_counteract = match($degree) {
  'critical_success' => $target_level <= $caster_counteract_level + 3,
  'success'          => $target_level <= $caster_counteract_level + 1,
  'failure'          => $target_level < $caster_counteract_level,
  'critical_failure' => FALSE,
};
```

### Wire into `ActionProcessor`

When action type is `'counteract'` or `'dispel'`, route through `CounteractService`.

## Acceptance Criteria
1. `getCounteractLevel('spell', 4)` → 4
2. `getCounteractLevel('ability', 7)` → 4 (ceil(7/2))
3. Crit success: can counteract target_level ≤ caster_level + 3
4. Success: target_level ≤ caster_level + 1
5. Failure: target_level < caster_level only
6. Crit failure: always fails

## Definition of Done
- [ ] `CounteractService.php` created and registered
- [ ] `ActionProcessor` routes counteract actions through service
- [ ] QA evidence updated for reqs 2145–2150
- [ ] `dc_requirements` 2145–2150 updated to `implemented` via MySQL
