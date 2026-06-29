# Implementation Notes: dc-cr-skills-medicine-actions

## Status
Done — committed `8083dcf8a`

## Files changed
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/EncounterPhaseHandler.php`
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/ExplorationPhaseHandler.php`

## What was built

### EncounterPhaseHandler.php
**Administer First Aid [2 actions, Trained Medicine, healer's tools]** (REQ 1688–1693):
- Added to `getLegalIntents()` and `getActionCost()` (cost = 2)
- Two modes: `stabilize` and `stop_bleeding`
- Once-per-round-per-condition-per-target enforced via `$game_state['first_aid_used'][$target.':'.$mode]`
- Improvised tools: -2 penalty when `is_improvised_tools=TRUE`
- `processAdministerFirstAid()` helper:
  - **Stabilize mode**: DC = 15 + dying_value. Crit success / success = `stabilizeCharacter()` (dying→0, wounded+1). Failure = `decrementCondition('dying', 1)`. Crit failure = `applyCondition('dying', value+1)`.
  - **Stop bleeding mode**: DC from `params['bleeding_dc']` (default 15). Success/crit = removes `persistent_bleed`/`bleeding` conditions. Crit fail = 1d4 immediate bleed damage via `applyDamage()`.

**Treat Poison [1 action, Trained Medicine, healer's tools]** (REQ 1695–1698):
- Added to `getLegalIntents()` and `getActionCost()` (cost = 1)
- One-per-save tracking via `$game_state['poison_treated'][$target.':poison']`
- `calculateDegreeOfSuccess()` used on Medicine vs poison_dc (default 15)
- On success/crit: sets `poison_treated` flag (poison save handler must apply +1 degree on next save)

### ExplorationPhaseHandler.php
**Treat Wounds [10 min exploration, Trained Medicine, healer's tools]** (REQ 1553–1562):
- Added to `getLegalIntents()` and handled in `processIntent()` switch
- DC/HP table: Trained DC 15/2d8+0, Expert DC 20/2d8+10, Master DC 30/2d8+30, Legendary DC 40/2d8+50
- Crit success = double healing; crit failure = 1d8 damage (no healing)
- 1-hour immunity per target: `$entity['state']['last_treated_wounds_at']` timestamp (in exploration minutes)
- Blocked if `now_minutes - last_treated_wounds_at < 60`
- Advances time by 10 min via `advanceExplorationTime()`
- Degree of success computed inline (ExplorationPhaseHandler has no injected `combatCalculator`)

**Treat Disease [downtime, Trained Medicine, healer's tools]** (REQ 1563–1568):
- Added to `getLegalIntents()` and handled in `processIntent()` switch
- Once per disease per rest period per target: `$game_state['disease_treated'][$target.':'.$disease_id]`
- On success/crit: sets `disease_treated` flag (disease save handler must apply +1 degree)
- DC from `params['disease_dc']` (default 15)

## New routes introduced
None.

## Pre-QA permission audit
No new routes — permission audit not applicable.

## Impact analysis
No new routes. No schema changes. New game_state keys (`first_aid_used`, `poison_treated`, `disease_treated`) are additive — no existing callers affected. Entity state `last_treated_wounds_at` is a new key on the entity state sub-array — additive.

## Notes / deviations
- **Treat Wounds degree-of-success is inline** (not via `combatCalculator`) because `ExplorationPhaseHandler` does not inject `CombatCalculator`. The formula matches PF2e standard: nat 20 / total ≥ DC+10 = crit success; nat 1 / total ≤ DC-10 = crit fail.
- **Poison treated flag**: downstream poison save handler must check `$game_state['poison_treated'][$target.':poison']` and upgrade degree by one step. This is a future implementation dependency (not in scope for this feature).
- **Disease treated flag**: same pattern as poison — disease save handler dependency.

## KB references
- Prior session: CharacterCalculator is NOT a Drupal service (seat instructions updated).

## Rollback
`git revert 8083dcf8a`
