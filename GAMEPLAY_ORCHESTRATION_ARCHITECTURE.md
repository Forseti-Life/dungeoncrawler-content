# Gameplay Orchestration & Process Flow Architecture

**Module**: dungeoncrawler_content  
**Last updated**: 2026-03-08  
**Status**: Implemented and validated through narrative, single-round, and multi-round combat lifecycle tests

---

## Overview

This document describes the authoritative runtime flow for gameplay orchestration in the dungeon crawler.

It answers two questions:

1. **What is the process flow?**
2. **Which services are the orchestrators vs. supporting mechanics/persistence services?**

The key design rule is:

> **The server owns phase, encounter lifecycle, turn order, damage resolution, and the return to narrative exploration.**

Client code can request actions, but it does not authoritatively decide combat state.

---

## Top-Level Ownership Model

### Primary orchestrators

| Layer | Primary service | Responsibility |
|---|---|---|
| Global gameplay state | `GameCoordinatorService` | Main orchestrator for `exploration`, `encounter`, and `downtime` |
| Exploration actions | `ExplorationPhaseHandler` | Validates and executes exploration intents, including dialogue bridge |
| Room dialogue | `RoomChatService` | Orchestrates room chat, GM response generation, canonical action execution |
| Dialogue grounding | `GameplayActionProcessor` | Converts freeform dialogue into canonical authoritative actions |
| Encounter lifecycle | `EncounterPhaseHandler` | Owns encounter entry, active turn loop, NPC auto-play, and encounter exit |
| Combat mechanics | `CombatEngine` | Owns encounter creation/start/end and low-level attack resolution |

### Supporting services

| Concern | Supporting service | Responsibility |
|---|---|---|
| Encounter persistence | `CombatEncounterStore` | Stores encounters, participants, damage log, conditions, action rows |
| Damage and defeat | `HPManager` | Applies damage/healing/temp HP and defeat/death state |
| Conditions | `ConditionManager` | Applies and ticks conditions |
| Event stream | `GameEventLogger` | Appends action and phase events to `dungeon_data.event_log` |
| NPC turn automation | `EncounterAiIntegrationService` | Produces AI recommendations for NPC turns |
| Narrative output | `AiGmService` / `NarrationEngine` | Generates immediate narration and queued perception-filtered narration |

---

## Process Flow: Dialogue-Driven Combat Start

This is the authoritative path for a player saying something like `I attack Gribbles.`

### Sequence

1. **Client sends a talk/exploration action**
   - The user speaks in the room UI.
   - The request is routed into `GameCoordinatorService::processAction()`.

2. **Global phase router delegates to exploration**
   - `GameCoordinatorService` loads `dungeon_data` and `game_state`.
   - It validates the action against the active phase.
   - Because the current phase is `exploration`, it delegates to `ExplorationPhaseHandler`.

3. **Exploration delegates room dialogue**
   - `ExplorationPhaseHandler::processTalk()` forwards the utterance into `RoomChatService`.
   - This is the bridge from exploration actions into room/GM orchestration.

4. **Room chat produces narrative + canonical actions**
   - `RoomChatService` gathers room context and invokes `GameplayActionProcessor`.
   - `GameplayActionProcessor` interprets aggressive dialogue and emits `combat_initiation`.
   - Target grounding prefers exact `entity_instance_id`; exact name fallback is allowed.

5. **Room chat executes canonical authoritative actions**
   - `RoomChatService` executes `combat_initiation` server-side.
   - It resolves enemy entities in the active room.
   - It asks `GameCoordinatorService` to transition from `exploration` to `encounter`.

6. **Phase transition is run centrally**
   - `GameCoordinatorService::executePhaseTransition()` runs:
     - old phase `onExit()`
     - new phase `onEnter()`
     - event logging
     - narration queueing
     - persistence

7. **Encounter entry bootstraps combat state**
   - `EncounterPhaseHandler::onEnter()` builds encounter participants from room entities.
   - It calls `CombatEngine::createEncounter()`.
   - It calls `CombatEngine::startEncounter()`.
   - It stores round 1, initiative order, current turn, and encounter id into `game_state`.

8. **Response returns encounter transition payload**
   - `RoomChatService` returns `combat_transition`, `canonical_actions`, and refreshed `dungeon_data`.
   - `ExplorationPhaseHandler` preserves that authoritative result in its action response.

### Result

The room stays narrative-first at the input layer, but combat state is started only through the server-owned phase machine.

---

## Process Flow: Encounter Turn Loop

Once phase is `encounter`, `EncounterPhaseHandler` becomes the active phase owner.

### Sequence

1. **Client sends encounter action**
   - Example intents: `strike`, `stride`, `talk`, `interact`, `end_turn`.

2. **Global phase router delegates to encounter**
   - `GameCoordinatorService::processAction()` validates against the current encounter state.
   - Turn ownership and action economy checks are enforced in `EncounterPhaseHandler::validateIntent()`.

3. **Encounter handler executes intent**
   - `EncounterPhaseHandler::processIntent()` dispatches by action type.
   - For `strike`, it calls `processStrike()`.
   - For `end_turn`, it calls `processEndTurn()`.

4. **Combat mechanics resolve through participant rows**
   - `processStrike()` maps entity ids to encounter participant rows.
   - `CombatEngine::resolveAttack()` performs the roll and damage application.
   - `HPManager` updates HP and defeat state.
   - `CombatEncounterStore` persists encounter state and damage log rows.

5. **Events and narration are appended**
   - `GameEventLogger` records `strike`, `end_turn`, `npc_strike`, `round_start`, and related events.
   - `NarrationEngine` receives queued room events.

6. **End-turn advances initiative**
   - `EncounterPhaseHandler::processEndTurn()` advances turn index.
   - If the order wraps, round increments.
   - If the next actor is an NPC, NPC auto-play runs immediately.

7. **NPC auto-play may recurse**
   - `autoPlayNpcTurn()` chooses or requests the NPC action.
   - It can resolve `strike` through the same combat path.
   - After the NPC turn, `processEndTurn()` can recurse until a player turn is reached again.

8. **Encounter end is checked after actions**
   - `EncounterPhaseHandler::checkEncounterEnd()` determines whether only one team remains.
   - If yes, it returns a phase transition request back to `GameCoordinatorService`.

---

## Process Flow: Return to Exploration

When the encounter ends:

1. `EncounterPhaseHandler` requests transition from `encounter` to `exploration`.
2. `GameCoordinatorService::executePhaseTransition()` runs encounter `onExit()` and exploration `onEnter()`.
3. `CombatEngine::endEncounter()` finalizes the encounter summary.
4. `game_state.encounter_id` is cleared.
5. `game_state.last_encounter` retains the encounter summary.
6. Exploration actions such as `move` and `talk` become available again.

---

## Canonical Runtime Flow Diagram

```text
Player dialogue
  -> GameCoordinatorService.processAction()
    -> ExplorationPhaseHandler.processTalk()
      -> RoomChatService.postMessage()
        -> GameplayActionProcessor
          -> emits canonical action: combat_initiation
        -> RoomChatService executes combat_initiation
          -> GameCoordinatorService.transitionPhase(encounter)
            -> EncounterPhaseHandler.onEnter()
              -> CombatEngine.createEncounter()
              -> CombatEngine.startEncounter()
                -> encounter round 1 + turn order active

Encounter action
  -> GameCoordinatorService.processAction()
    -> EncounterPhaseHandler.processIntent()
      -> strike/end_turn/etc.
      -> CombatEngine.resolveAttack()
      -> HPManager.applyDamage()
      -> CombatEncounterStore persists state
      -> GameEventLogger logs events
      -> checkEncounterEnd()
        -> if over: GameCoordinatorService.transitionPhase(exploration)
```

---

## Source of Truth by Concern

| Concern | Authoritative owner |
|---|---|
| Current game phase | `GameCoordinatorService` + `game_state.phase` |
| Encounter id / round / turn | `EncounterPhaseHandler` updating `game_state` |
| Encounter/participant persistence | `CombatEncounterStore` |
| Initiative ordering | `CombatEngine::startEncounter()` / `startRound()` with persisted participants |
| Attack resolution | `CombatEngine::resolveAttack()` |
| HP loss / defeat | `HPManager` |
| Dialogue-to-action grounding | `GameplayActionProcessor` |
| Narrative room action execution | `RoomChatService` |
| Event timeline | `GameEventLogger` |

---

## Validation Coverage

The current orchestration path is validated by the following executable tests:

| Test | Coverage |
|---|---|
| `tests/narrative_combat_flow_test.php` | exploration -> dialogue combat start -> encounter -> return to exploration |
| `tests/full_combat_cycle_test.php` | dialogue combat start -> decisive strike -> encounter end -> exploration |
| `tests/multi_round_combat_cycle_test.php` | dialogue combat start -> player end turn -> NPC auto-play -> round 2 -> victory -> exploration |

---

## Related Documentation

- [COMBAT_ENGINE_ARCHITECTURE.md](COMBAT_ENGINE_ARCHITECTURE.md) — combat services, APIs, database tables, test matrix
- [CHAT_AND_NARRATION_ARCHITECTURE.md](CHAT_AND_NARRATION_ARCHITECTURE.md) — room chat, session hierarchy, narration pipeline
- [AI_ENCOUNTER_INTEGRATION.md](AI_ENCOUNTER_INTEGRATION.md) — NPC decision provider chain
- [GM_INSTRUCTIONS.md](GM_INSTRUCTIONS.md) — GM prompt rules, canonical action semantics, grounding rules