# Status

- status: done
- created_at: 2026-06-12T15:17:00+00:00
- current_phase: completed

## Notes

### 2026-06-12 — Kickoff
- Created from direct user request for authoritative subsystem verification/refactor.

### 2026-06-12 — Initial inventory + boundary hardening
- Confirmed `GameCoordinatorService` remains single entrypoint for authoritative action processing and phase-handler routing.
- Confirmed `EncounterPhaseHandler` owns canonical encounter turn/round progression and per-actor legality handling.
- Applied explicit top-level authority comments in:
  - `src/Service/GameCoordinatorService.php`
  - `src/Service/EncounterPhaseHandler.php`

### 2026-06-12 — Refactor pass 1 (canonical Talk validation de-duplication)
- Implemented a targeted simplification to remove duplicate turn validation when `EncounterPhaseHandler` dispatches canonical Talk intents into `RoomChatService`.
- `EncounterPhaseHandler::processTalk()` now tags internal metadata with `_validated_encounter_talk => TRUE`.
- `RoomChatService::postMessage()` now skips `validateEncounterPlayerTurnForChat()` only for the internal canonical room player Talk path (`type=player`, `channel=room`, validated metadata present); all external/direct paths remain gated.
- Extended `EncounterPhaseHandlerTest::testProcessIntentTalkDelegatesToRoomChatServiceAndBuildsContract` to assert `_validated_encounter_talk` is present and true.
- Commit: `489bcec` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority and storage review
- Canonical action authority path remains `GameCoordinatorService::processAction()` -> `EncounterPhaseHandler::validateIntent()/processIntent()`.
- Canonical durable turn state is persisted in combat tables:
  - `combat_encounters.current_round`, `combat_encounters.turn_index`
  - `combat_participants.actions_remaining`, `attacks_this_turn`, `reaction_available`
- `EncounterPhaseHandler` projects canonical encounter state into `dungeon_data.game_state` via `syncGameStateWithCanonicalTurn()`; `GameCoordinatorService` persists that projection in `dc_campaign_dungeons.dungeon_data`.
- Server chat (`RoomChatController`) reads round/turn snapshots from coordinator state and routes player room chat into canonical Talk intents; it does not own turn advancement.
- Drift surface identified: `CombatEncounterApiController` read endpoints (`currentState/get`) still call `normalizeEncounterForResponse()` which can auto-play NPC turns and write `turn_index/current_round` through `updateEncounter()` outside the coordinator+encounter-handler chain.
- Drift surface identified: admin combat participant mutation endpoints allow direct writes to initiative/action-economy fields (`initiative`, `actions_remaining`, `attacks_this_turn`, `reaction_available`) outside canonical intent processing.

### 2026-06-12 — Round/turn authority hardening pass
- Eliminated read-path turn mutation in `CombatEncounterApiController`:
  - `normalizeEncounterForResponse()` is now side-effect free and no longer auto-plays NPC turns or advances `turn_index/current_round`.
- Hardened legacy admin mutation endpoints in `CombatApiController`:
  - `rerollInitiative()` now returns `409 round_turn_authority_disabled` with canonical redirect to `/api/game/{campaign_id}/action`.
  - `updateParticipant()` now rejects canonical turn fields (`initiative`, `actions_remaining`, `attacks_this_turn`, `reaction_available`) with the same 409 error contract.
  - Non-turn participant updates (`name`, `team`, `ac`, `hp`, `max_hp`, `position_q`, `position_r`) remain supported.
- Added regression coverage:
  - `tests/src/Unit/Controller/CombatApiControllerAuthorityTest.php` (reroll disabled, blocked field rejection, allowed non-turn update path).
- Commit: `8ace3b3` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 2 (roster mutation lock)
- Disabled remaining legacy roster mutation endpoints that can change turn order outside canonical authority:
  - `CombatApiController::addParticipant()` now returns `409 round_turn_authority_disabled`.
  - `CombatApiController::removeParticipant()` now returns `409 round_turn_authority_disabled`.
- Extended `CombatApiControllerAuthorityTest` with explicit coverage for disabled add/remove roster mutation paths.
- Commit: `3bc0449` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 3 (read-path purity + simplification)
- Removed remaining read-side mutation behavior in legacy encounter state endpoint flow:
  - `CombatEncounterApiController::currentState()` no longer retires/updates encounters on read.
  - Removed `retireActiveEncounters()` read-time mutation helper.
  - `autoPlayNonPlayerTurns()` is now explicitly side-effect free.
- Simplified legacy combat admin controller wiring:
  - Removed unused number-generation dependency from `CombatApiController` constructor/create path after disabling initiative/roster mutation endpoints.
- Updated authority unit tests to match constructor simplification.
- Commit: `2c572eb` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 4 (team mutation lock)
- Tightened legacy participant update surface:
  - `CombatApiController::updateParticipant()` now also blocks direct `team` changes behind canonical encounter authority (`409 round_turn_authority_disabled`).
  - Allowed update set remains non-authoritative fields only (`name`, `ac`, `hp`, `max_hp`, `position_q`, `position_r`).
- Expanded `CombatApiControllerAuthorityTest` with explicit team-mutation rejection coverage.
- Commit: `002b94c` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 5 (legacy encounter controller simplification)
- Removed stale non-canonical scaffolding from `CombatEncounterApiController`:
  - deleted unused AI/config/character-state/dispatcher/number-generation dependencies from constructor/create wiring;
  - removed dead legacy helper methods that no longer participate in canonical read behavior.
- Updated `CombatEncounterApiControllerTeamRulesTest` to assert only active read-path contract (`normalizeEncounterForResponse` remains side-effect free and preserves participant teams).
- Commit: `caa33ae` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 6 (stride participant sync canonicalization)
- Found a canonical authority bug in `EncounterPhaseHandler::processStride()`:
  - participant position sync called `CombatEncounterStore::updateParticipant()` with invalid argument shape (`encounter_id, actor_id, fields`) instead of canonical participant-row update.
- Refactored stride sync to resolve the actor's canonical participant row first, then persist position via:
  - `updateParticipant(participant_id, ['position_q' => ..., 'position_r' => ...])`.
- Tightened error handling around sync to catch `\Throwable` for runtime signature/type failures and keep behavior explicit.
- Added regression coverage:
  - `EncounterPhaseHandlerTest::testProcessStrideSyncsParticipantPositionByParticipantId`.
- Commit: `f658e6a` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 7 (HP mutation channel canonicalization)
- Tightened legacy participant admin mutation surface in `CombatApiController::updateParticipant()`:
  - blocked direct `hp`/`max_hp` writes from generic participant patch payloads;
  - retained metadata-only updates (`name`, `ac`, `position_q`, `position_r`) on this endpoint.
- This prevents bypassing dedicated HP mutation channels and keeps health-state transitions on their canonical path.
- Expanded authority tests:
  - updated allowed-field test to non-canonical metadata fields only;
  - added explicit `hp`/`max_hp` block coverage.
- Commit: `02b5c5c` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 8 (legacy action controller wiring simplification)
- Reviewed legacy `CombatActionController` architecture after mutation endpoints were already hard-disabled.
- Removed unused constructor dependencies (`action_processor`, `combat_engine`) so controller wiring now reflects actual runtime behavior:
  - only `CombatEncounterStore` remains required for read-only `getCurrentTurn`.
- Added targeted authority regression coverage:
  - `CombatActionControllerAuthorityTest` for store-backed `getCurrentTurn` and disabled mutation response contract.
- Commit: `e1065b4` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 9 (room chat controller throwable safety)
- Reviewed server-chat controller error boundaries under canonical encounter authority flow.
- Hardened `RoomChatController` non-stream request handlers to catch `\Throwable` (not only `\Exception`) so runtime type/argument failures are converted to stable JSON error contracts instead of escaping framework-level.
- This keeps client-side chat behavior deterministic while preserving server-side canonical authority ownership.
- Commit: `c7e793d` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 10 (unused deps + canonical error catch hardening)
- Full architecture review pass identified remaining drift across service wiring and error catch surfaces.
- Removed unused constructor dependencies:
  - `CombatEngine`: `StateManager` + `ActionProcessor` were injected but never called; removed from class, constructor, and `services.yml`.
  - `CombatController`: `CombatEngine` was injected but never called (no routes, all stubs); removed entirely.
- Hardened `\Exception` → `\Throwable` in all canonical service error boundaries:
  - `GameCoordinatorService` (4 sites): dungeon load, dungeon persist, NarrationEngine flush, NarrationEngine phase queue.
  - `EncounterPhaseHandler` (6 sites): encounter create, encounter end, strike resolution, encounter store update (canonical turn-state write), NPC AI fallback, NarrationEngine queue.
- Commit: `96b4043` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 11 (orphaned service removal + phase handler catch hardening)
- Removed orphaned `state_manager` service registration from `services.yml` (no consumer after pass 10 CombatEngine cleanup).
- Hardened `ExplorationPhaseHandler` \Exception -> \Throwable (5 sites): talk action, NPC profile creation, daily-prepare persist, dungeon-data persist, NarrationEngine queue.
- Hardened `DowntimePhaseHandler` \Exception -> \Throwable (3 sites): starvation persist, long-rest persist, downtime-rest persist — all wrapping DB writes.
- Commit: `2d98a64` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 12 (remove unreachable phase handlers from coordinator)
- Removed `ExplorationPhaseHandler` and `DowntimePhaseHandler` from `GameCoordinatorService` constructor and `services.yml` game_coordinator args.
- Both were injected but silently discarded — never stored in `phaseHandlers`, never called; comment falsely claimed "code reuse".
- `ExplorationPhaseHandler` remains legitimately registered as a service and injected into `EncounterPhaseHandler` for `processSearch()`. `DowntimePhaseHandler` has no consumers after removal.
- The live runtime is encounter-only; `getPhaseHandler('exploration')` returns NULL (deprecated) and `getPhaseHandler('downtime')` was already unreachable (not registered).
- Commit: `be82e00` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 13 (EncounterPhaseHandler dep cleanup + narration/AI catch hardening)
- Removed `ActionProcessor`, `RulesEngine`, `EventDispatcherInterface` from `EncounterPhaseHandler` (zero calls to any of them in the class body); removed properties, use statements, and services.yml args.
- Removed `DowntimePhaseHandler` service registration from `services.yml` (no consumers remaining).
- Hardened `NarrationEngine` (2 catches) and `AiGmService` (4 catches) from `\Exception` to `\Throwable`.
- Updated all 3 EncounterPhaseHandler unit test fixtures to match new constructor signature.
- Commit: `d01554b` (`dungeoncrawler-content` main).

### 2026-06-12 — Round/turn authority hardening pass 14 (orphaned service + full pipeline catch hardening)
- Verification confirmed: legacy `ActionProcessor` has zero consumers after passes 10 + 13; `NarrationEngine` and `RoomChatService` both use `GameplayActionProcessor` (different class). Removed `dungeoncrawler_content.action_processor` service registration.
- Hardened `RoomChatService` Exception->Throwable (16 catches — active user-facing chat/action pipeline).
- Hardened `GameplayActionProcessor` Exception->Throwable (6 catches — action bar validation/execution pipeline).
- Result: zero `\Exception` catches remain across the entire canonical active pipeline (8 services checked).
- Commit: `e33e21e` (`dungeoncrawler-content` main).

### 2026-06-15 — Closeout
- Work item objectives are complete: authority inventory, bypass elimination, canonical turn/round ownership enforcement, and contract hardening all shipped to `dungeoncrawler-content` main.
- Final pass checkpoint confirms no additional non-canonical turn mutation paths remain in reviewed combat admin/controller surfaces for this scope.

## Next Action
1. Closed.
