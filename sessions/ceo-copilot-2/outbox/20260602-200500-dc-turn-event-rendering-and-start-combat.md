# Dungeoncrawler — Encounter framework master: combat start + NPC event rendering

## Why
We were still seeing turn-order/message ordering issues (e.g., Eldric appears silent on his turn, then outputs after the turn harness completes). Root cause was primarily **client-side event rendering gaps** (NPC turn events were logged by the encounter master but not rendered into the room chat transcript), plus lingering client polling surfaces that could still follow legacy `/api/combat/*` state.

## What changed
### 1) Added coordinator-only `start_combat` intent
* Backend: `GameCoordinatorService::processAction()` now special-cases `type = start_combat` when the active handler is `EncounterPhaseHandler`.
* It bootstraps room framework if needed, then calls `EncounterPhaseHandler::onEnter()` to escalate into combat while keeping the encounter framework as the master.
* This provides a **single canonical surface** for starting combat via `POST /api/game/{campaign}/action` instead of `POST /api/combat/start`.

### 2) HexmapStateSync now polls coordinator state first
* Frontend: `HexmapStateSync.sync()` now prefers `gameCoordinator.api.getState()` when coordinator is active.
* `HexmapStateSync.apply()` now understands the coordinator response shape (`{ game_state: ... }`) and projects it into the legacy `encounter_id/status/initiative_order` shape for turn hydration.

### 3) v2 room chat now renders NPC turn events immediately
* Frontend: `js/v2/panels/ChatPanel.js` now renders:
  * `npc_talk` as an `npc` chat line (speaker = actor name)
  * `npc_strike` as a `system` chat line summarizing strike outcome

This closes the "silent NPC turn" gap and makes per-actor output appear on the correct turn.

## Files
* `dungeoncrawler-content/src/Service/GameCoordinatorService.php`
* `dungeoncrawler-content/js/HexmapStateSync.js`
* `dungeoncrawler-content/js/v2/panels/ChatPanel.js`

## Validation
* PHP lint: OK
* JS syntax check: OK
* Drupal cache rebuild: OK
* Regression: `drush php:script tests/chat_session_test.php` → **59 passed / 0 failed**

## Remaining risk / follow-up
* Coordinator `strike` currently uses placeholder weapon stats when the client does not supply `params.weapon` (see `EncounterPhaseHandler::processStrike()` defaults). This is separate from the message-order fix but must be resolved before we fully remove any remaining legacy combat action surfaces.
