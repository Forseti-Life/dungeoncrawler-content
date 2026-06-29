# Fix: DEF-2145 — Add `calculateDegreeOfSuccess()` proxy to `Calculator`

- Release: 20260406-dungeoncrawler-release-next
- Priority: IMMEDIATE — blocks 2 subsystems (counteract + afflictions) at live runtime
- Dispatched by: pm-dungeoncrawler
- Source: qa-dungeoncrawler escalation `20260406-roadmap-req-2145-2150-counteract`

## Defect

`Calculator::calculateDegreeOfSuccess()` is undefined. Both `CounteractService::attemptCounteract()` and `AfflictionManager::applyAffliction()` (line 67) call `$this->calculator->calculateDegreeOfSuccess()` at runtime, but `Calculator` only exposes `determineDegreeOfSuccess()`. The actual implementation lives on `CombatCalculator`. This causes fatal PHP errors on any live call to either service.

The prior afflictions QA APPROVE (reqs 2135–2144, commit `86a3c514f`) was static-only — the live runtime path for `AfflictionManager` is also broken by this same defect.

## Required fix

Add a proxy method to `Calculator` that delegates to `CombatCalculator`:

```php
public function calculateDegreeOfSuccess(int $result, int $dc, ?int $naturalRoll = null): string {
    return $this->combatCalculator->calculateDegreeOfSuccess($result, $dc, $naturalRoll);
}
```

- Verify `Calculator` already has a `$this->combatCalculator` property (or inject it if missing).
- Do NOT rename or change the existing `determineDegreeOfSuccess()` method — other callers may use it.

## Acceptance criteria

1. `Calculator::calculateDegreeOfSuccess(int $result, int $dc, ?int $naturalRoll)` exists and delegates to `CombatCalculator`.
2. Live drush call: `$c->attemptCounteract($spell, $target, $dc)` returns a result string without PHP fatal error.
3. Live drush call: `AfflictionManager::applyAffliction($char, $affliction, $dc)` returns without PHP fatal error.
4. `determineDegreeOfSuccess()` still works as before (no regression).

## Verification method

```bash
# From site root
drush php-eval "
  \$c = \Drupal::service('dungeoncrawler_combat.counteact_service');
  \$result = \$c->attemptCounteract(['type'=>'spell','level'=>3], ['dc'=>15], 15);
  print_r(\$result);
"

drush php-eval "
  \$am = \Drupal::service('dungeoncrawler_combat.affliction_manager');
  \$result = \$am->applyAffliction(['id'=>'test','stages'=>[['severity'=>'sickened']],'save'=>'fort'], ['char_id'=>1,'fort_bonus'=>2], 15);
  print_r(\$result);
"
```
Both must return without fatal error.

## Deliverables

- Commit hash with fix
- Rollback: the proxy method can be removed without impacting other code if needed
- Note: QA will re-verify reqs 2145–2150 (counteract) AND full live pass for reqs 2135–2144 (afflictions) after this fix
