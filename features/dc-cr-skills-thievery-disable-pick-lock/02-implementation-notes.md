# Implementation Notes: dc-cr-skills-thievery-disable-pick-lock

**Dev:** dev-dungeoncrawler
**Date:** 2026-04-08

## KB reference
None found specific to Thievery. Lock complexity DC table sourced directly from PF2e CRB ch04 (simple 15/average 20/good 25/superior 30). Pattern follows existing trained-skill enforcement in `disarm` and `administer_first_aid`.

## What was built

### EncounterPhaseHandler.php

**`getLegalIntents()`** — added `palm_object`, `steal`, `disable_device`, `pick_lock`.

**`getActionCost()`** — `palm_object` and `steal` cost 1 action; `disable_device` and `pick_lock` cost 2 actions.

**`processIntent()` — new cases:**

#### `palm_object` [REQ 1747–1750]
- Rolls Thievery (`params['thievery_bonus']`) vs each observer's Perception DC (`params['perception_dcs'][$obs_id]`, default 15).
- On success vs all observers: sets `$game_state['palmed_objects'][$actor_id . ':' . $item_id] = TRUE`.
- Per-observer result with `secret: true` (d20 not exposed to player).

#### `steal` [REQ 1751–1752]
- Rolls Thievery vs target's Perception DC (`params['perception_dc']`, default 15).
- Success: sets `$game_state['stolen_items']` record with actor, target, item_id.
- Critical Failure: marks target + all `params['observer_ids']` aware in `$game_state['steal_awareness'][$aware_id][$actor_id] = TRUE`.
- No trained requirement in AC (untrained allowed per PF2e).

#### `disable_device` [REQ 1748–1750]
- Requires `params['thievery_proficiency_rank'] >= 1` (Trained); returns error if not.
- No thieves' tools (`params['has_thieves_tools']` absent): DC +5.
- Complex devices: tracks progress in `$game_state['device_states'][$device_id]['successes']`; requires `params['successes_needed']` (default 1) successes.
- Critical Success/Success: increments success count; disables when threshold met.
- Critical Failure: sets `$game_state['device_states'][$device_id]['triggered'] = TRUE`.

#### `pick_lock` [REQ 1753–1756]
- Requires `params['thievery_proficiency_rank'] >= 1` (Trained).
- DC table: `{'simple': 15, 'average': 20, 'good': 25, 'superior': 30}` (from `params['lock_quality']`).
- No thieves' tools: DC +5.
- Success: sets `$game_state['lock_states'][$lock_id]['locked'] = FALSE`.
- Critical Failure: sets `$game_state['lock_states'][$lock_id]['jammed'] = TRUE`; subsequent attempts to pick that lock are blocked (checked before roll).

## Files changed
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/EncounterPhaseHandler.php`

## Verification
- PHP lint: passes.
- `drush cr`: cache rebuild complete.
- QA suite: check TCs tagged `dc-cr-skills-thievery-disable-pick-lock` in `qa-suites/products/dungeoncrawler/suite.json`.

## Security checklist
- [x] No new routes — all operations go through existing encounter handler.
- [x] Trained check enforced server-side for disable_device and pick_lock before roll.
- [x] Lock/device DC values are server-authoritative (table in code, not client-supplied).
- [x] d20 results marked `secret: true`.
- [x] No PII logged; only character_id, action_type, target/device/lock IDs logged.
- [x] Lock jammed state checked before re-attempt to prevent silent bypass.

## Rollback
- Revert commit; run `drush cr`.
