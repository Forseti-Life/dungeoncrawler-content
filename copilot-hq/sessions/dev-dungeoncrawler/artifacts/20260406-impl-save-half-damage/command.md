# Implement: Basic Saving Throw Damage Outcomes (half-damage on Success)

- Release: ch09-playing-the-game
- Feature: dc-cr-saving-throws
- Status: pending
- Priority: high
- Agent: dev-dungeoncrawler

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Saving Throws (Basic Saving Throws, p.449)

## Requirement (req 2097)

> Basic saving throw results: Critical Success = 0 damage; Success = half damage; Failure = full damage; Critical Failure = double damage.

## Context

File: `web/modules/custom/dungeoncrawler_content/src/Service/ActionProcessor.php`

The current save resolution in `ActionProcessor` inverts the save degree correctly, but then applies damage using only two tiers (full / double). The "success" outcome (target beats the DC but not by 10+) currently applies **zero** damage instead of **half** damage.

Degree inversion already in place:
```php
$degree_map = [
  'critical_success' => 'critical_failure',  // 0 damage ✅
  'success'          => 'failure',            // currently 0 damage ❌ should be half
  'failure'          => 'success',            // full damage ✅
  'critical_failure' => 'critical_success',   // double damage ✅
];
```

Then damage is applied only when `$degree` is in `['success', 'critical_success']`. After inversion, a save "success" maps to `'failure'` — which falls outside that array, giving 0 damage.

## Required Changes

In `ActionProcessor.php`, update the damage block that follows the degree inversion to handle the full four-tier system:

```php
// After inversion, $degree is from the spell's perspective:
//   critical_success = spell crits (save crit fail) → double damage
//   success          = spell hits (save fail)        → full damage
//   failure          = spell partially hits (save success) → half damage  ← NEW
//   critical_failure = spell misses (save crit success) → 0 damage

if ($damage_dice) {
  $roll = $this->numberGeneration->rollExpression($damage_dice);
  $base_damage = (int) ($roll['total'] ?? 0);

  if ($degree === 'critical_success') {
    $damage = $base_damage * 2;
  } elseif ($degree === 'success') {
    $damage = $base_damage;
  } elseif ($degree === 'failure') {
    $damage = (int) floor($base_damage / 2);
  } else {
    $damage = 0; // critical_failure = 0
  }

  if ($damage > 0) {
    $damage_result = $this->hpManager->applyDamage(...);
  }
}
```

Apply the same pattern to `$healing_dice` and `$condition_to_apply` blocks — conditions on "failure" (save success) should typically not apply (or apply at reduced severity per spell definition; default = no condition on save success).

## Acceptance Criteria

1. `degree === 'critical_success'` (spell perspective) → damage = base × 2
2. `degree === 'success'` → damage = base (full)
3. `degree === 'failure'` → damage = floor(base / 2)
4. `degree === 'critical_failure'` → damage = 0
5. Unit test: verify all four outcomes with a known $base_damage value

## Definition of Done
- [ ] ActionProcessor updated with four-tier save damage logic
- [ ] Existing call sites continue to work (regression test via `drush test` or manual scenario)
- [ ] QA inbox item `20260406-roadmap-req-2095-2100-ac-defenses` evidence updated with PASS for req 2097
- [ ] `dc_requirements` req 2097 status updated to `implemented` via MySQL
