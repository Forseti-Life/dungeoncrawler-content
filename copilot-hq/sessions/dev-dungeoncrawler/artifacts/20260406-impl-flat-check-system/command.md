# Implement: Flat Check System (DC Bounds, Hidden/Concealed DCs, Fortune/Misfortune, Secret Checks)

- Release: ch09-playing-the-game
- Feature: dc-cr-flat-checks
- Status: pending
- Priority: high
- Agent: dev-dungeoncrawler

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Flat Checks (p.450), Fortune/Misfortune (p.451), Secret Checks (p.450)

## Requirements

| ID   | Req                                                                                    |
|------|----------------------------------------------------------------------------------------|
| 2102 | DC ≤ 1 = auto-success; DC ≥ 21 = auto-failure                                         |
| 2103 | Hidden=DC11, Concealed=DC5, Persistent damage=DC15 (DC10 assisted)                    |
| 2104 | Secret checks: GM rolls, player sees only outcome                                      |
| 2105 | Fortune: roll d20 twice, use higher                                                    |
| 2106 | Misfortune: roll d20 twice, use lower                                                  |
| 2107 | Fortune + Misfortune cancel; single roll                                               |

## What Exists

- `CombatEngine.php`: Persistent damage flat check at DC 15 (hardcoded `>= 15`). This handles req 2103 for persistent but NOT hidden/concealed.
- `ConditionManager.php`: `'concealed'` and `'hidden'`-equivalent conditions defined but no flat check DCs wired.
- No `rollFlatCheck()` method exists anywhere; checks are inline d20 comparisons.
- No Fortune/Misfortune logic exists.
- No secret check flag exists in API responses.

## Required Changes

### 1. Add `rollFlatCheck($dc, $options = [])` to `Calculator.php`

```php
public function rollFlatCheck(int $dc, array $options = []): array {
  // DC bounds
  if ($dc <= 1) return ['auto' => TRUE, 'success' => TRUE, 'roll' => NULL, 'dc' => $dc];
  if ($dc >= 21) return ['auto' => TRUE, 'success' => FALSE, 'roll' => NULL, 'dc' => $dc];

  $fortune = $options['fortune'] ?? FALSE;
  $misfortune = $options['misfortune'] ?? FALSE;

  if ($fortune && $misfortune) {
    // Cancel: single roll
    $roll = $this->numberGeneration->rollPathfinderDie(20);
  } elseif ($fortune) {
    $r1 = $this->numberGeneration->rollPathfinderDie(20);
    $r2 = $this->numberGeneration->rollPathfinderDie(20);
    $roll = max($r1, $r2);
  } elseif ($misfortune) {
    $r1 = $this->numberGeneration->rollPathfinderDie(20);
    $r2 = $this->numberGeneration->rollPathfinderDie(20);
    $roll = min($r1, $r2);
  } else {
    $roll = $this->numberGeneration->rollPathfinderDie(20);
  }

  return ['auto' => FALSE, 'success' => $roll >= $dc, 'roll' => $roll, 'dc' => $dc];
}
```

### 2. Wire DC 11 (hidden) and DC 5 (concealed) in `RulesEngine::validateAttack()`

Before resolving an attack, check target conditions:
- Target is `hidden` → require flat check DC 11; if fails, attack targets a random square
- Target is `concealed` → require flat check DC 5; if fails, attack misses automatically

### 3. Refactor persistent damage flat check in `CombatEngine.php`

Replace inline `rollPathfinderDie(20) >= 15` with `$this->calculator->rollFlatCheck(15)`.
Also handle assisted DC 10: accept an `$assisted` flag that sets DC to 10.

### 4. Secret check flag in API responses

Add `'secret' => TRUE` flag to any check result where the check was designated secret. The API response should omit `'roll'` when `secret === TRUE`.

### 5. Fortune/Misfortune condition effects

In `ConditionManager`, ensure conditions that grant fortune/misfortune set participant flags `'has_fortune'` / `'has_misfortune'` in combat state. Pass these flags to `rollFlatCheck()` and `rollSkillCheck()` / `rollSavingThrow()`.

## Acceptance Criteria

1. `rollFlatCheck(1)` → auto-success, no roll
2. `rollFlatCheck(21)` → auto-failure, no roll
3. Attacking hidden target triggers DC 11 flat check; fail = miss
4. Attacking concealed target triggers DC 5 flat check; fail = miss
5. Persistent damage uses DC 15 via `rollFlatCheck(15)` (DC 10 when assisted)
6. Fortune: two rolls, higher taken
7. Misfortune: two rolls, lower taken
8. Fortune + Misfortune both active = single roll
9. Secret check responses omit roll value

## Definition of Done
- [ ] `rollFlatCheck()` added to `Calculator.php`
- [ ] `RulesEngine` enforces hidden/concealed flat checks
- [ ] `CombatEngine` persistent damage refactored to use `rollFlatCheck()`
- [ ] Fortune/Misfortune wired through condition system
- [ ] QA inbox evidence updated for reqs 2101–2107
- [ ] `dc_requirements` reqs 2102–2107 updated to `implemented` via MySQL
