# Implementation Notes: dc-cr-skills-stealth-hide-sneak

**Dev:** dev-dungeoncrawler
**Date:** 2026-04-08

## KB reference
None found specific to stealth visibility state management. Prior `processSeek()` implementation (REQ 2207-2210) established the `$game_state['visibility'][$seeker_id][$target_id]` pattern — this implementation follows the same model but in reverse (actor_id is the target being hidden from each observer).

## What was built

### EncounterPhaseHandler.php

**`getLegalIntents()`** — added `hide`, `sneak`, `conceal_object`.

**`getActionCost()`** — all three cost 1 action.

**`processIntent()` — new cases:**

#### `hide` [REQ 1715–1718]
- Validates `params['has_cover']` or `params['has_concealment']` is set; returns error if neither.
- Rolls d20 + `stealth_bonus` vs each `observer_ids[n]` Perception DC (from `params['perception_dcs'][$obs_id]`, default 15).
- Success: sets `$game_state['visibility'][$obs_id][$actor_id] = 'hidden'`.
- Failure: sets `$game_state['visibility'][$obs_id][$actor_id] = 'observed'`.
- Per-observer result stored; d20 marked `secret: true` (not exposed to player).

#### `sneak` [REQ 1719–1722]
- Validates actor is Hidden (or Undetected/Unnoticed) to at least one observer in `params['observer_ids']`; returns error if not.
- Computes `half_speed = floor(params['speed'] / 2 / 5) * 5` (rounded to 5-ft interval).
- If `ends_in_cover` and `ends_in_concealment` are both absent: auto-Observed to all observers (ended in open terrain).
- Otherwise: rolls d20 + `stealth_bonus` vs each observer's Perception DC; Success = remain Hidden; Failure = Observed to that observer.
- `secret: true` on result.

#### `conceal_object` [REQ 1721–1724]
- Rolls d20 + `stealth_bonus` vs each observer's Perception DC.
- Success per observer: item concealed from that observer.
- If concealed from ALL observers, sets `$game_state['concealed_objects'][$actor_id . ':' . $item_id] = TRUE`.
- `params['item_id']` tracks which item was concealed.

### ExplorationPhaseHandler.php
- **No changes required** — `avoid_notice` exploration activity is already supported via the `set_activity` action's `legal_activities` list (line 318). The AC for Avoid Notice [Exploration] is covered by the existing set_activity flow.

## Visibility state schema
- Store: `$game_state['visibility'][$observer_id][$target_id]`
- Values: `observed`, `hidden`, `undetected`, `unnoticed`
- Matches existing `processSeek()` schema — consistent with REQ 2207-2210 implementation.

## Files changed
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/EncounterPhaseHandler.php`

## Verification
- PHP lint: passes.
- `drush cr`: cache rebuild complete.
- QA suite: `./vendor/bin/phpunit --filter StealthTest --testsuite module-test-suite`

## Rollback
- Revert commit; run `drush cr`.

## Security checklist
- [x] No new routes added (existing encounter handler only).
- [x] Observer list derived from `params['observer_ids']` — must be validated server-side by encounter load; no arbitrary entity IDs accepted.
- [x] Visibility state changes are server-authoritative in game_state.
- [x] d20 roll values marked `secret: true`; not included in player-facing result keys.
- [x] No PII logged; only character_id, action_type, observer_count logged via GameEventLogger.
