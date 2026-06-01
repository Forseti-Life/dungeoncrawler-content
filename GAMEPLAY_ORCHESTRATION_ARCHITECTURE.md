# Gameplay Orchestration & Process Flow Architecture

**Module**: dungeoncrawler_content  
**Last updated**: 2026-06-01  
**Status**: Encounter-first runtime; exploration phase deprecated and disabled

---

## Current Runtime Rule

Room gameplay runs through the encounter framework. There is no active `exploration` phase in the runtime state machine.

`ExplorationPhaseHandler` remains in the repository because it contains useful room-action, search, hazard, and narrative code that may be reused later. It is not registered as an active phase handler by `GameCoordinatorService`, and `/api/game/{campaign_id}/transition` rejects `target_phase: "exploration"`.

## Top-Level Ownership Model

| Layer | Primary service | Responsibility |
|---|---|---|
| Global gameplay state | `GameCoordinatorService` | Main orchestrator for the active `encounter` runtime |
| Encounter lifecycle | `EncounterPhaseHandler` | Owns room entry encounter bootstrap, active turn loop, NPC auto-play, action economy, and encounter cleanup |
| Room dialogue | `RoomChatService` | Orchestrates room chat, GM response generation, and canonical action execution |
| Dialogue grounding | `GameplayActionProcessor` | Converts freeform dialogue into canonical authoritative actions |
| Combat mechanics | `CombatEngine` | Owns encounter creation/start/end and low-level attack resolution |

`ExplorationPhaseHandler` is deprecated for active runtime routing. Encounter actions may still reuse selected helper behavior until that logic is refactored into neutral services.

## Room Entry Flow

1. `GameCoordinatorService::getFullState()` loads `dungeon_data` and normalizes any persisted `game_state.phase: "exploration"` to `encounter`.
2. Startup room entry calls `EncounterPhaseHandler::enterRoomFramework()`, the same server-authoritative room-entry contract used by connected-room movement.
3. Connected-room movement uses the canonical encounter action `type: "transition"` with `params.target_room_id`; legacy `room_transition` is not a supported action name.
4. `EncounterPhaseHandler` validates the target through `NavigationService`, emits `room_entered`, and starts or reuses encounter-framework context.
5. Every room entry starts one encounter framework with round state, turn order, active turn, and explicit end-turn/no-action contract for every actor present in the room. Hostile pressure can add combat encounter persistence and hostile NPC action behavior, but it does not define a separate room mode.
6. The client receives `phase: "encounter"`, `encounter_context`, round/turn fields, `available_actions`, and the action contract.

Room entry immediately uses the encounter framework. Social, investigation, search, dialogue, and hostile actions are all encounter actions under one contract.

## Action Flow

```text
Client action
  -> GameCoordinatorService.processAction()
    -> normalize deprecated persisted exploration phase to encounter
    -> route through the active encounter handler
    -> EncounterPhaseHandler.validateIntent()
    -> EncounterPhaseHandler.processIntent()
      -> transition/strike/search/talk/interact/end_turn/choose_not_to_act/etc.
      -> action economy and turn ownership enforced
      -> events and narration queued
```

Round starts, actor turn starts, and explicit no-action/end-turn choices are queued through the narrator/chat pipeline so the room log shows every round, every actor turn, and every actor's explicit turn-ending decision. The chat transcript renders encounter lines with a visible `Round X: Actor Y:` prefix so action text and NPC/player responses carry the active round and actor context directly in the log.

Campaign time advances through the server time resolver. Each completed encounter round adds 6 seconds to the canonical `campaign_clock`; connected-room `transition` adds server-side travel time from connection metadata (`travel_time_seconds`, `duration_seconds`, `time_cost_seconds`, minute equivalents, or nested `travel_time`) and falls back to 60 seconds when no explicit duration is present. The client displays the returned clock and must not calculate or override room-travel duration.

## Search / Perception Contract

Search and passive room perception are secret server-side checks. Passive checks use no modifiers. The explicit Search action is requested with `params.search_mode: "explicit"`; the server applies the standardized `+2` bonus and does not accept character-supplied Perception modifiers or requested sense targets from the client.

Failed checks are silent in chat. Successful checks may narrate only the discovered room detail or item in plain language, such as `You notice a spellbook on the shelf.` Chat output must not include roll totals, DCs, success degree, or sensory labels such as `Smell:` or `Sound:`.

## Supported Transitions

The live runtime is encounter-only. `exploration` is intentionally absent, and `downtime` is no longer an active runtime phase. Rest now happens through encounter actions gated by `room.gameplay_state.safe_for_rest`.

## Source of Truth by Concern

| Concern | Authoritative owner |
|---|---|
| Current active phase | `GameCoordinatorService` + `game_state.phase` (`encounter`) |
| Room encounter id / round / turn | `EncounterPhaseHandler` updating `game_state` |
| Encounter/participant persistence | `CombatEncounterStore` |
| Initiative ordering | `CombatEngine::startEncounter()` / `startRound()` with persisted participants |
| Action economy | `EncounterPhaseHandler::validateIntent()` and per-action processors |
| Attack resolution | `CombatEngine::resolveAttack()` |
| HP loss / defeat | `HPManager` |
| Dialogue-to-action grounding | `GameplayActionProcessor` |
| Narrative room action execution | `RoomChatService` |
| Event timeline | `GameEventLogger` |

## Validation Coverage

The encounter-first runtime is guarded by:

| Test | Coverage |
|---|---|
| `tests/deprecated_exploration_phase_test.js` | Coordinator defaults to encounter, rejects exploration transitions, omits active exploration client payload, and documents the deprecation |
| `tests/action_rail_search_binding_test.js` | V2 Search uses the encounter action path and current character runtime context |
| `tests/action_rail_turn_sync_test.js` | V2 action rail respects encounter turn state |

## Related Documentation

- `README.md`
- `COMBAT_ENGINE_ARCHITECTURE.md`
- `CHAT_AND_NARRATION_ARCHITECTURE.md`
