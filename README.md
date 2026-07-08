# Dungeon Crawler Content Module

**Module**: `dungeoncrawler_content`  
**Type**: Drupal custom module  
**Drupal**: 10.3+ / 11.x  
**PHP**: 8.1+

This module is the game runtime for DungeonCrawler: campaign orchestration, room/campaign state, encounter/combat flow, chat/narration, procedural generation, quest/storyline systems, and PF2e character/inventory services.

## 1. Current Architecture Snapshot

The current implementation is an **encounter-first runtime** with a unified coordinator entry point:

- Gameplay actions are orchestrated through `GameCoordinatorService`.
- Active runtime phase is `encounter`; `exploration` is not an active runtime phase.
- Room chat + canonical action execution are server-authoritative for state mutation.
- Campaign/runtime records are persisted to campaign tables (`dc_campaign_*`) and canonical content tables (`dungeoncrawler_content_*`).

Primary action entry point:

- `POST /api/game/{campaign_id}/action`

Legacy combat mutation endpoints under `/api/combat/*` are non-canonical support surfaces; player action flow is coordinator-driven.

## 2. Subsystem Map (Current)

| Subsystem | Core services/controllers | Responsibility |
|---|---|---|
| Gameplay Orchestration | `GameCoordinatorService`, `EncounterPhaseHandler`, `GameCoordinatorController` | Canonical action routing, turn/phase flow, room transition handling |
| Combat & Encounter Engine | `CombatEngine`, `ActionProcessor`, `RulesEngine`, `HPManager`, `ConditionManager`, `ReactionHandler`, `CombatApiController` | PF2e encounter lifecycle, action resolution, conditions, initiative, combat state |
| Chat, Session & Narration | `RoomChatService`, `ChatSessionManager`, `ChatChannelManager`, `NarrationEngine`, `AiGmService`, `RoomChatController`, `ChatSessionController` | Room dialogue, session hierarchy, narration/event feed |
| Content Generation | `CampaignInitializationService`, `DungeonGeneratorService`, `RoomGeneratorService`, `MapGeneratorService`, `EntityPlacerService`, `EncounterGeneratorService` | Dungeon/room/entity generation and campaign materialization |
| Quest & Storyline | `QuestGeneratorService`, `QuestTrackerService`, `QuestValidatorService`, `QuestTouchpointService`, `StorylineGenerationService`, `StorylineRealizationService`, `StorylineManagerService` | Quest lifecycle, storyline creation/activation/journals, contract validation |
| Character & Progression | `CharacterManager`, `CharacterStateService`, `CharacterLevelingService`, `CharacterCreationGmService`, character controllers/forms | Character creation, progression, runtime character state |
| Inventory & Equipment | `InventoryManagementService`, `ContainerManagementService`, `GameObjectInventoryService`, `EquipmentCatalogService`, inventory APIs | Item/container management and equipment interactions |
| NPC / Institutions / Psychology | `NpcService`, `NpcSheetGenerationService`, `NpcPsychologyService`, institution services | NPC generation/runtime data, psychology, campaign institutions/factions |
| Validation & Contract Enforcement | `StateValidationService`, `ImpactContractService`, validator/report controllers | Hard validation of canonical content contracts and runtime consistency |
| Generated Assets & Media | image generation services + generated image controllers | Portraits, room/terrain/sprite image generation and retrieval |

### Actor Psychology Invocation Summary

The actor psychology subsystem is invoked in two canonical gameplay lanes:

- **Dialogue lane** (room chat + NPC replies): `HexMapController` and `MapGeneratorService` bootstrap room NPC profiles, while `RoomChatService` builds NPC prompt context via `NpcPsychologyService::buildNpcContextForPrompt()` and records post-dialogue state shifts via `recordInnerMonologue()`.
- **Next-action lane** (encounter AI decisions): `EncounterPhaseHandler` injects `current_actor_profile` + `npc_psychology` into NPC action context through `buildNpcDecisionProfile()` and `buildNpcPsychologyContext()`.
- **Conformance refresh (2026-07-08):** recent RoomChatController facade decomposition and stream/result boundary extraction did not change actor-psychology invocation authority; invocation remains service-owned (`RoomChatService`, `EncounterPhaseHandler`) and server-authoritative.

See subsystem docs for full invocation points and context payload details:

- `CHAT_AND_NARRATION_ARCHITECTURE.md`
- `COMBAT_ENGINE_ARCHITECTURE.md`

## 3. Runtime Data Authority

| Concern | Authoritative surface |
|---|---|
| Campaign runtime state | `dc_campaigns`, `dc_campaign_dungeons`, `dc_campaign_rooms`, `dc_campaign_characters`, `dc_campaign_quests` |
| Canonical content library | `dungeoncrawler_content_registry`, `dungeoncrawler_content_rooms`, `dungeoncrawler_content_dungeons`, quest template/content tables |
| Storyline templates/contracts | `dungeoncrawler_content_quest_templates` and related storyline tables |
| Encounter persistence | combat encounter tables used by `CombatEncounterStore` |

Reference JSON/template files are input/reconciliation artifacts. Runtime contracts are enforced from database-backed canonical surfaces.

## 4. API Surface (Grouped from Routing)

The routing surface is organized around these domains:

- Admin + architecture/analysis pages
- Public pages and game UI routes
- Character management and character APIs
- NPC APIs
- Campaign/dungeon/room state APIs
- Room chat, chat channels, and chat session hierarchy APIs
- Inventory management APIs
- Game coordinator APIs (canonical gameplay loop)
- Combat support APIs (encounter-scoped support + legacy endpoints)
- Dungeon/room generation APIs
- Quest/storyline/reward/touchpoint APIs
- Spell/feat/content catalog APIs
- Play session and subsystem management APIs

See `dungeoncrawler_content.routing.yml` for route-level details.

## 5. Codebase Structure (Module Core)

Top-level module code lives in:

- `src/Controller/` — route handlers and API/page controllers
- `src/Service/` — core game/runtime services
- `src/Form/` — Drupal forms and admin workflows
- `src/Access/` — access checkers
- `src/Commands/` — Drush command surfaces
- `src/EventSubscriber/` — event-driven quest/runtime hooks

### RoomChat source-of-truth policy

- **Canonical authoring path:** `src/` at the module root.
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/` is a runtime mirror copy and must stay byte-identical for RoomChat files.
- Use `scripts/check-roomchat-tree-drift.sh` to verify parity before merge when RoomChat files are changed.

Wiring and route registration:

- `dungeoncrawler_content.services.yml`
- `dungeoncrawler_content.routing.yml`

## 6. Architecture Docs Index

Canonical architecture references in this repository:

- `ARCHITECTURE.md` — module architecture baseline
- `GAMEPLAY_ORCHESTRATION_ARCHITECTURE.md` — gameplay/runtime orchestration
- `CHAT_AND_NARRATION_ARCHITECTURE.md` — chat/session/narration model
- `COMBAT_ENGINE_ARCHITECTURE.md` — encounter/combat subsystem coverage
- `HEXMAP_ARCHITECTURE.md` — map/runtime model
- `DETERMINISTIC_GM_ORCHESTRATION_ARCHITECTURE.md` — deterministic GM orchestration direction
- `docs/README.md` — docs index for implementation/contract documents

## 7. Development Notes

- Module dependency: `drupal/ai_conversation` (see `composer.json` and `dungeoncrawler_content.info.yml`).
- Use targeted PHPUnit/Node contract tests for changed subsystem surfaces.
- Prefer updating subsystem-specific architecture docs together with behavior changes.

---

This README is now architecture-first and intended as the top-level entry point. Deep implementation details should live in subsystem docs, not in this file.
