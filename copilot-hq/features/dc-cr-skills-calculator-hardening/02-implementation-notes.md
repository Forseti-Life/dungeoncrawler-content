# Implementation Notes: dc-cr-skills-calculator-hardening

## Status
Done — committed `8083dcf8a`

## Files changed
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterCalculator.php`
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterLevelingService.php`

## What was built

### CharacterCalculator.php
- Added `ARMOR_CHECK_PENALTY_SKILLS` constant: `['acrobatics', 'athletics', 'stealth', 'thievery']`
- Extended `calculateSkillCheck()` with optional 6th param `array $options = []`:
  - `trained_only` (bool): returns `['blocked' => TRUE, 'error' => ...]` when character is untrained
  - `is_attack_trait` (bool): when TRUE, bypasses armor check penalty (e.g. grapple, shove)
- Armor check penalty applied from `$characterData['armor_check_penalty']` (negative int)
- Return array now includes `armor_check_penalty` and `blocked` keys (both backward-compatible)

### CharacterLevelingService.php
- `submitSkillIncrease()` enforces PF2e rank ceilings:
  - Expert→Master blocked at level < 7 (throws `\InvalidArgumentException`, code 400)
  - Master→Legendary blocked at level < 15 (throws `\InvalidArgumentException`, code 400)
- Level read from `$char_data['basicInfo']['level'] ?? $char_data['level'] ?? 1`

## Impact analysis
No new routes. No schema changes. Fully backward-compatible (new optional param, new optional return keys). Existing callers of `calculateSkillCheck()` without options param are unaffected.

## New routes introduced
None.

## Pre-QA permission audit
No new routes — permission audit not applicable.

## Verification
```bash
php -r "
require 'sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterCalculator.php';
\$calc = new \Drupal\dungeoncrawler_content\Service\CharacterCalculator();
\$r = \$calc->calculateSkillCheck(['level'=>5,'abilities'=>['strength'=>10],'skills'=>['athletics'=>'untrained']],'athletics',15,0,NULL,['trained_only'=>TRUE]);
echo 'blocked: '.(!empty(\$r['blocked'])?'YES':'NO').PHP_EOL;
"
# Expected: blocked: YES
```

PHP syntax check passed. `drush cr` succeeded (no fatal errors).

## KB references
- KB lesson 2026-04: CharacterCalculator is standalone (not a Drupal service) — do not use `\Drupal::service()`.
- Seat instructions updated with disambiguation note.

## Rollback
`git revert 8083dcf8a`
