# Combat & Encounter Engine Architecture

**Module**: dungeoncrawler_content  
**Last updated**: 2026-03-08  
**Overall completion**: ~75%

This document tracks the implementation status of the PF2e combat/encounter engine — every service, controller, API endpoint, database table, and known gap.

---

## Table of Contents

1. [Service Inventory](#service-inventory)
2. [Controller & API Endpoint Status](#controller--api-endpoint-status)
3. [Database Schema](#database-schema)
4. [Test Coverage](#test-coverage)
5. [Known Bugs](#known-bugs)
6. [Implementation Gaps — Prioritized](#implementation-gaps--prioritized)
7. [Completion Matrix](#completion-matrix)

---

## Service Inventory

### Combat Core Services

| Service ID | Class | Lines | Status | Notes |
|---|---|---:|---|---|
| `dungeoncrawler_content.combat_engine` | CombatEngine | 524 | **Substantial** | Full encounter lifecycle: `createEncounter`, `startEncounter`, `startRound`, `endRound`, `startTurn`, `endTurn`, `resolveAttack`. `delayTurn`/`resumeFromDelay` are stubs. XP award computation TODO. |
| `dungeoncrawler_content.combat_calculator` | CombatCalculator | 240 | **Complete** | `determineDegreOfSuccess`, `getSimpleDC`, `getTaskDC`, `calculateMultipleAttackPenalty` (PF2e -5/-10), `calculateAttackBonus` (ability+prof+level+item+other), `calculateSpellSaveDC` (10+ability+prof+level+item+other). All methods implemented — stale TODO comments removed. |
| `dungeoncrawler_content.combat_encounter_store` | CombatEncounterStore | 300 | **Complete** | Full CRUD: `createEncounter`, `loadEncounter`, `updateEncounter`, `saveParticipants`, `updateParticipant`, `addCondition`, `removeCondition`, `listActiveConditions`, `logAction`, `logDamage`. No TODOs. |
| `dungeoncrawler_content.action_processor` | ActionProcessor | 420 | **Complete** | `executeAction` (dispatcher: stride, strike, cast_spell), `executeStrike`, `executeStride`, `executeCastSpell` (attack/save/automatic delivery, damage, healing, condition application, multi-target). No TODOs. |
| `dungeoncrawler_content.hp_manager` | HPManager | 274 | **Complete** | `applyDamage` (with temp HP absorption), `applyHealing`, `applyTemporaryHP` (PF2e take-higher-value), `checkDeathCondition`, `applyDyingCondition`, `stabilizeCharacter`. No TODOs. |
| `dungeoncrawler_content.condition_manager` | ConditionManager | 570 | **Complete** | 32-condition PF2e catalog. `applyCondition`, `removeCondition`, `getActiveConditions`, `tickConditions`, `processDying`, `applyConditionEffects`, `getConditionModifiers`, `processPersistentDamage` (applies damage, rolls flat check DC 15 to end, supports dice expressions). No TODOs. |
| `dungeoncrawler_content.reaction_handler` | ReactionHandler | 673 | **Complete** | `checkForReactions`, `checkForShieldBlock`, `executeReaction`, `processAttackOfOpportunity`, `processShieldBlock`. No TODOs. |
| `dungeoncrawler_content.rules_engine` | RulesEngine | 550 | **Complete** | `validateAction` (6-layer pipeline: state→economy→conditions→prerequisites→type-specific→immunities), `validateActionEconomy`, `validateActionPrerequisites`, `checkConditionRestrictions`, `checkImmunities`, `validateAttack`, `validateSpellCast`. No TODOs. |
| `dungeoncrawler_content.state_manager` | StateManager | 453 | **Complete** | `transitionState`, `getCurrentState`, `saveStateSnapshot`, `restoreStateSnapshot`, `getInitiativeOrder`, `getCurrentTurnParticipant`, `getParticipantState`, `invalidateCache`. No stubs. |
| `dungeoncrawler_content.calculator` | Calculator | 464 | **Complete** | General PF2e stat calculations. No TODOs. |
| `dungeoncrawler_content.number_generation` | NumberGenerationService | 298 | **Complete** | Dice engine: d4–d20, d%, NdX notation, modifiers. No TODOs. |
| `dungeoncrawler_content.equipment_catalog` | ItemCombatDataService | 304 | **Complete** | `getWeaponCombatData`, `getWeaponsCombatData`, `getUnarmedStrikeData`. No TODOs. |

### Encounter Generation & Balancing

| Service ID | Class | Lines | Status | Notes |
|---|---|---:|---|---|
| `dungeoncrawler_content.encounter_balancer` | EncounterBalancer | 450+ | **Complete** | `createEncounter` — full PF2e XP budget encounter building; `selectCreaturesForBudget` — weighted knapsack creature selection with grouping; `getCreatureLevelRange` — difficulty-to-level mapping; `getCreatureXPCost` — level-differential XP lookup table; `getFallbackCreatures` — 8-theme creature catalog (dungeon, cave, crypt, ruins, underground, demonic, underdark, sewer); `adjustForPartySize` — scales budgets for non-standard party sizes. No TODOs. |
| `dungeoncrawler_content.encounter_generator` | EncounterGeneratorService | 422 | **Complete** | `generateEncounter` has real logic with EncounterBalancer now fully operational. |

### Encounter AI Integration

| Service ID | Class | Lines | Status | Notes |
|---|---|---:|---|---|
| `dungeoncrawler_content.encounter_ai_integration` | EncounterAiIntegrationService | 178 | **Complete** | Context building, recommendation request, validation. No TODOs. |
| `dungeoncrawler_content.encounter_ai_provider.ai_conversation` | AiConversationEncounterAiProvider | 566 | **Complete** | Routes through `ai_conversation.ai_api_service`. No TODOs. |
| `dungeoncrawler_content.encounter_ai_provider.stub` | StubEncounterAiProvider | 85 | **Complete** | Deterministic fallback provider. |

### Phase Handlers & Coordinator

| Service ID | Class | Lines | Status | Notes |
|---|---|---:|---|---|
| `dungeoncrawler_content.game_coordinator` | GameCoordinatorService | 626 | **Complete** | Central orchestrator: phase state machine, action routing, event logging. No TODOs. |
| `dungeoncrawler_content.encounter_phase_handler` | EncounterPhaseHandler | 1780 | **Complete** | `processIntent`, `onEnter`, `onExit`, `getAvailableActions`, `validateIntent`. Integrates CombatEngine, AI, NPC psychology. No TODOs. |
| `dungeoncrawler_content.exploration_phase_handler` | ExplorationPhaseHandler | 1092 | **Complete** | No TODOs. |
| `dungeoncrawler_content.downtime_phase_handler` | DowntimePhaseHandler | 283 | **Partial** | `long_rest` implemented. Other downtime activities stubbed (1 TODO). |

---

## Controller & API Endpoint Status

### CombatEncounterApiController — Primary Combat API (8/9 routed, working)

| Method | Route | HTTP | Status |
|---|---|---|---|
| `currentState()` | `/api/combat/state` | GET | **Implemented** |
| `start()` | `/api/combat/start` | POST | **Implemented** |
| `endTurn()` | `/api/combat/end-turn` | POST | **Implemented** |
| `end()` | `/api/combat/end` | POST | **Implemented** |
| `attack()` | `/api/combat/attack` | POST | **Implemented** |
| `action()` | `/api/combat/action` | POST | **Implemented** |
| `get()` | `/api/combat/get` | POST | **Implemented** |
| `set()` | `/api/combat/set` | POST | **Implemented** |

### CombatApiController — Encounter-Scoped CRUD (12/12 implemented)

| Method | Route Pattern | HTTP | Status |
|---|---|---|---|
| `updateHP()` | `.../participants/{id}/hp` | PATCH | **Implemented** — damage + healing via HPManager |
| `applyTempHP()` | `.../participants/{id}/temp-hp` | POST | **Implemented** — PF2e temp HP rules via HPManager |
| `applyCondition()` | `.../participants/{id}/conditions` | POST | **Implemented** — delegates to ConditionManager::applyCondition with duration |
| `removeCondition()` | `.../conditions/{id}` | DELETE | **Implemented** — delegates to ConditionManager::removeCondition |
| `listConditions()` | `.../participants/{id}/conditions` | GET | **Implemented** — ConditionManager::getActiveConditions, normalized output |
| `getInitiative()` | `.../initiative` | GET | **Implemented** — loads encounter participants sorted by initiative, includes conditions + turn marker |
| `rerollInitiative()` | `.../initiative/reroll` | POST | **Implemented** — re-rolls d20 via NumberGenerationService, re-sorts order |
| `addParticipant()` | `.../participants` | POST | **Implemented** — inserts participant, optional initiative roll, logs join action |
| `removeParticipant()` | `.../participants/{id}` | DELETE | **Implemented** — marks is_defeated=1, logs removal action |
| `updateParticipant()` | `.../participants/{id}` | PATCH | **Implemented** — whitelisted field update (ac, hp, position, etc.) |
| `getLog()` | `.../log` | GET | **Implemented** — paginated + filtered combat action log |
| `getStatistics()` | `.../statistics` | GET | **Implemented** — aggregated damage, action counts, per-participant stats |

All 12 routes registered in `dungeoncrawler_content.routing.yml` under `/api/combat/{encounter_id}/...`. CSRF-protected POST endpoints, `access dungeoncrawler characters` permission.

### CombatController — UI Controller (0/9 implemented, NOT ROUTED)

All 9 public methods are stubs returning placeholder responses. No routes registered in `dungeoncrawler_content.routing.yml`.

### CombatActionController — Turn-Level Actions (0/11 implemented, NOT ROUTED)

All 11 public methods (8 with TODO markers) are stubs. No routes registered.

### Other Combat-Related Routed Endpoints

| Route | Controller | Status |
|---|---|---|
| `POST /api/combat/recommendation-preview` | EncounterAiPreviewController | **Implemented** (admin-only) |
| `POST /api/character/{id}/hp` | CharacterStateController | **Implemented** |
| `POST /api/character/{id}/conditions` | CharacterStateController | **Implemented** |
| `DELETE /api/character/{id}/conditions/{cid}` | CharacterStateController | **Implemented** |
| `GET /architecture/encounter-ai-integration` | EncounterAiIntegrationController | **Implemented** |
| `GET /hexmap` | HexMapController | **Implemented** |

---

## Database Schema

### Combat Tables (all defined in `dungeoncrawler_content.install`)

| Table | Key Columns | Status |
|---|---|---|
| `combat_encounters` | id, campaign_id, room_id, map_id, status, current_round, turn_index | **Active — used by CombatEncounterStore** |
| `combat_participants` | id, encounter_id, entity_id, name, team, initiative, ac, hp, max_hp, temp_hp, actions_remaining, attacks_this_turn, position_q/r, reaction_available, is_defeated | **Active — temp_hp added via hook 10025** |
| `combat_conditions` | id, participant_id, encounter_id, condition_type, value, duration_type, duration_remaining, source | **Active — used by ConditionManager** |
| `combat_actions` | id, encounter_id, participant_id, action_type, target_id, payload, result | **Active — used for action + narration logging** |
| `combat_damage_log` | id, encounter_id, participant_id, amount, damage_type, source, hp_before, hp_after | **Active — used by HPManager** |

### Encounter Template Tables

| Table | Status |
|---|---|
| `dungeoncrawler_content_encounter_templates` | Schema defined |
| `dc_campaign_encounter_templates` | Schema defined |
| `dc_campaign_encounter_instances` | Schema defined |

---

## Test Coverage

### Drush Script Functional Tests (694 total, all passing)

| Suite | Tests | Covers |
|---|---:|---|
| `combat_engine_test.php` | 136 | CombatEngine lifecycle, ActionProcessor, ConditionManager, CombatCalculator, encounter start/round/turn/end |
| `hp_manager_test.php` | 86 | HPManager damage, healing, temp HP (grant/no-stack/upgrade/absorb), dying, massive death, stabilization |
| `combat_api_controller_test.php` | 112 | CombatApiController all 12 endpoints: HP CRUD, conditions, initiative, participants, combat log, statistics |
| `combat_services_wiring_test.php` | 99 | RulesEngine::validateAction (12 tests), ActionProcessor::executeCastSpell (13 tests), ConditionManager::processPersistentDamage (9 tests), executeAction dispatcher (4 tests), combat log verification (2 tests) |
| `multi_round_combat_cycle_test.php` | 28 | Dialogue combat start, player `end_turn`, NPC auto-play, round advancement, encounter end, return to exploration |
| `chat_session_test.php` | 54 | ChatSessionManager hierarchy, deterministic keys, feed-up rules |
| `chat_integration_test.php` | 46 | Chat REST endpoints, bridge methods, ChatChannelManager |
| `npc_psychology_test.php` | 67 | NpcPsychologyService personality, attitude, inner monologue |
| `narration_pipeline_test.php` | 66 | NarrationEngine → GameCoordinator pipeline, perception filtering |

### PHPUnit Tests (combat-related)

| Suite | Test Methods | Focus |
|---|---:|---|
| CombatCalculatorTest | 4 | Attack/damage calculations |
| StubEncounterAiProviderTest | 4 | Fallback AI provider |
| EncounterAiIntegrationServiceTest | 6 | AI context + recommendation |
| AiConversationEncounterAiProviderTest | 7 | Live AI provider |
| NumberGenerationServiceTest | 8 | Dice engine |
| CombatControllerTest | 2 | Controller route access |
| CombatApiControllerTest | 2 | API controller route access |
| CombatEncounterApiControllerTest | 8 | Primary combat API routes |
| CombatActionControllerTest | 2 | Action controller route access |

### Test Gaps

- ~ConditionManager~ — **RESOLVED**: `processPersistentDamage` tested in `combat_services_wiring_test.php` (9 tests)
- ~RulesEngine~ — **RESOLVED**: `validateAction` 6-layer pipeline tested in `combat_services_wiring_test.php` (12 tests)
- ~ActionProcessor~ — **RESOLVED**: `executeCastSpell` tested in `combat_services_wiring_test.php` (13 tests)
- **EncounterBalancer / EncounterGeneratorService** — no dedicated tests yet (implementations complete, needs test coverage)
- ~~Full-round simulation~~ — **RESOLVED**: `multi_round_combat_cycle_test.php` validates end-turn, NPC auto-play, round 2 advancement, and return to exploration
- **CombatApiController condition/initiative/participant endpoints** — fully tested (112 tests in `combat_api_controller_test.php`)

---

## Known Bugs

| Bug | Location | Impact | Severity |
|---|---|---|---|
| ~Table name mismatch~ | `CombatEncounterApiController::currentState()` | ~~queries `dc_combat_encounters`~~ **FIXED** — now uses `combat_encounters` | **Resolved** |

---

## Implementation Gaps — Prioritized

### High Priority (blocks core combat loop correctness)

| Gap | Service | Current State | Impact |
|---|---|---|---|
| ~MAP returns 0~ | CombatCalculator | **RESOLVED** — `calculateMultipleAttackPenalty` fully implemented (0/-5/-10, -4 if agile) | ~~Second/third attacks never penalized~~ |
| ~Attack bonus TODO~ | CombatCalculator | **RESOLVED** — `calculateAttackBonus` fully implemented | ~~Attack rolls may use incorrect modifiers~~ |
| ~Spell save DC TODO~ | CombatCalculator | **RESOLVED** — `calculateSpellSaveDC` fully implemented | ~~Spell checks don't resolve correctly~~ |
| ~`validateAction` stub~ | RulesEngine | **RESOLVED** — 6-layer pipeline: state→economy→conditions→prerequisites→type-specific→immunities | ~~Rules validation pipeline not enforced~~ |
| ~`executeCastSpell` stub~ | ActionProcessor | **RESOLVED** — Full spell casting: attack/save/automatic delivery, damage, healing, conditions, multi-target | ~~Spellcasting actions do nothing~~ |
| ~Persistent damage stub~ | ConditionManager | **RESOLVED** — `processPersistentDamage`: applies damage, flat check DC 15, dice expressions, condition removal | ~~On-fire/bleed/acid conditions don't deal damage~~ |

### Medium Priority (secondary API layer + encounter generation)

| Gap | Service | Current State | Impact |
|---|---|---|---|
| ~10 CombatApiController stubs~ | CombatApiController | **RESOLVED** — All 12/12 endpoints implemented + 112 tests | ~~Secondary combat API non-functional~~ |
| ~EncounterBalancer stubs~ | EncounterBalancer | **RESOLVED** — All 6 methods + 4 helpers implemented. Full PF2e XP budget encounter building. | ~~Cannot auto-generate balanced encounters~~ |
| `delayTurn`/`resumeFromDelay` stubs | CombatEngine | Return `[]` | Delay action doesn't work |
| 2/3-action activities | ActionProcessor | Only 1-action enforcement | Multi-action activities (Power Attack, combat maneuvers) not available |
| Reaction enforcement | ActionProcessor | `reaction_available` in DB but not consumed | Reactions can be used unlimited times |
| XP award computation | CombatEngine | Encounter end doesn't award XP | Players never gain XP from combat |
| Damage resistances/weaknesses | HPManager | Not implemented | Creatures ignore type-based DR |

### Low Priority (UI controllers, polish)

| Gap | Service | Current State | Impact |
|---|---|---|---|
| CombatController (9 methods) | CombatController | All stubs, no routes | UI-level combat management unavailable |
| CombatActionController (11 methods) | CombatActionController | All stubs, no routes | UI-level turn action management unavailable |
| ~Table name bug~ | CombatEncounterApiController | **FIXED** — `combat_encounters` | ~~Direct `/api/combat/state` call fails~~ |
| ~CombatApiController routing~ | CombatApiController | **RESOLVED** — 12 routes added to `routing.yml` at `/api/combat/{encounter_id}/...` | ~~Endpoints only callable programmatically, not via HTTP~~ |
| DowntimePhaseHandler activities | DowntimePhaseHandler | Only `long_rest` works | Crafting, Earn Income, Retrain unavailable |

---

## Completion Matrix

| Layer | % | Notes |
|---|---:|---|
| Database schema | 95% | All 8 tables defined. ~Table name bug fixed.~ |
| Service DI registration | 100% | All 21 combat/encounter services in YAML. |
| Core combat services | 98% | HPManager, ConditionManager, ReactionHandler, Store, StateManager, CombatCalculator, **RulesEngine**, **ActionProcessor** all complete. Only EncounterBalancer stubs remain. |
| Primary combat API | 80% | `CombatEncounterApiController` — 8/9 endpoints working. This is the real game API. |
| Secondary combat API | 100% | `CombatApiController` — **12/12 endpoints implemented, all routed** at `/api/combat/{encounter_id}/...`. |
| UI controllers | 0% | `CombatController` + `CombatActionController` — fully stubbed, not routed. |
| Encounter generation | 90% | EncounterBalancer fully implemented. EncounterGeneratorService operational. Multi-round encounter lifecycle test coverage now exists. |
| AI integration | 95% | Full provider chain with stub fallback. Guarded autoplay + narration. |
| Phase handlers | 90% | EncounterPhaseHandler (1780 lines) deeply integrated. |
| Test coverage | 84% | 136+86+112+**99**+28 combat tests + 54+46+67+66 other + 43 PHPUnit = **694+43 total**. All core combat services now tested. Missing: EncounterBalancer dedicated tests. |
| **Overall** | **~80%** | Core combat loop fully functional: strikes, spells, conditions, persistent damage, validation pipeline. Encounter generation now complete. Remaining gaps: UI controllers, delay/resume, XP awards, damage resistances. |

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────┐
│                    Frontend (hexmap)                     │
│   game-coordinator.js → action-panel.js → chat-panel.js │
└──────────────────────────┬──────────────────────────────┘
                           │ POST /api/game/{cid}/action
                           │ POST /api/combat/*
                           ▼
┌──────────────────────────────────────────────────────────┐
│            GameCoordinatorService (orchestrator)          │
│  Phase state machine: exploration → encounter → downtime │
└──────────┬─────────────────┬─────────────────┬──────────┘
           │                 │                 │
           ▼                 ▼                 ▼
   ┌──────────────┐ ┌────────────────┐ ┌──────────────┐
   │ Exploration   │ │ Encounter      │ │ Downtime     │
   │ PhaseHandler  │ │ PhaseHandler   │ │ PhaseHandler │
   │  (1092 lines) │ │  (1780 lines)  │ │  (283 lines) │
   └──────────────┘ └───────┬────────┘ └──────────────┘
                            │
              ┌─────────────┼─────────────┐
              ▼             ▼             ▼
   ┌──────────────┐ ┌────────────┐ ┌──────────────┐
   │ CombatEngine │ │ Action     │ │ Reaction     │
   │  (lifecycle) │ │ Processor  │ │ Handler      │
   │  524 lines   │ │ 263 lines  │ │ 673 lines    │
   └──────┬───────┘ └─────┬──────┘ └──────┬───────┘
          │               │               │
          ▼               ▼               ▼
   ┌────────────────────────────────────────────┐
   │              Shared Services               │
   │  HPManager | ConditionManager | Calculator │
   │  RulesEngine | StateManager | Store        │
   │  NumberGenerationService                   │
   └────────────────────────────────────────────┘
          │               │
          ▼               ▼
   ┌────────────┐  ┌──────────────────┐
   │ AI Layer   │  │ Database         │
   │ Encounter  │  │ combat_encounters│
   │ AI Integ.  │  │ combat_participants
   │ + Provider │  │ combat_conditions│
   │   chain    │  │ combat_actions   │
   └────────────┘  │ combat_damage_log│
                   └──────────────────┘
```

---

## Related Documentation

- **README.md** — Module overview, all routes, permissions, Future Enhancements checklist
- **GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md** — authoritative gameplay process flow, orchestrator ownership, dialogue→combat→exploration lifecycle
- **CHAT_AND_NARRATION_ARCHITECTURE.md** — Chat session hierarchy, NarrationEngine pipeline, channel manager
- **AI_ENCOUNTER_INTEGRATION.md** — Encounter AI provider chain, phased rollout, observability
- **HEXMAP_ARCHITECTURE.md** — Hexmap rendering, spatial model, fog-of-war
