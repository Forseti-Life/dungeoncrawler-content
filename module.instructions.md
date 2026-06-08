# Module Instructions: dungeoncrawler_content

## Purpose
`dungeoncrawler_content` is the primary gameplay module for Dungeoncrawler. It owns game runtime behavior, encounter flow, world/content generation, campaign state, and player-facing gameplay APIs/UI.

## Source of truth
- Canonical repository: `/home/ubuntu/forseti.life/dungeoncrawler-content`
- Site context: `org-chart/sites/dungeoncrawler/site.instructions.md`
- Architecture reference: `ARCHITECTURE.md`

## Request routing (intent -> subsystem)

| If request mentions... | Route first to subsystem | Start here first |
|---|---|---|
| Encounter, turns, initiative, action resolution, combat flow | Encounter and action engine | `src/Service/EncounterPhaseHandler.php`, `src/Service/GameplayActionProcessor.php` |
| Hexmap rendering, canvas behavior, map view state | Hexmap shell and rendering | `js/hexmap-v2.js`, `js/v2/canvas/`, `js/v2/GameShell.js` |
| Action bar, panel interactions, button wiring | Action rail and panel interaction | `js/v2/panels/`, `js/v2/systems/EncounterSystem.js` |
| Client execution of server actions, coordinator flows | Coordinator client bridge | `js/game-coordinator/`, `js/v2/systems/` |
| Character creation, leveling, feats, sheet state | Character lifecycle | `src/Controller/Character*`, `src/Service/Character*` |
| Campaign start/state, room transitions, dungeon state | Campaign and world state | `src/Service/Campaign*`, `src/Service/Room*`, `src/Service/Dungeon*` |
| Quest generation/progress/rewards/objectives | Generation pipeline | `src/Service/Quest*`, `src/Controller/QuestTrackerController.php` |
| NPC behavior, institution modeling, relationship state | NPC and institutions | `src/Service/Npc*`, `src/Service/Institution*`, `src/Service/RelationshipManagerService.php` |
| Encounter AI, narrator output, GM orchestration | AI and narration integration | `src/Service/EncounterAiIntegrationService.php`, `src/Service/NarrationEngine.php` |
| Merchant inventory, buying/selling, prices, vendor behavior | Merchant and economy integration | `src/Service/MerchantBotService.php`, `src/Service/MerchantTransactionService.php`, `src/Controller/MerchantApiController.php` |
| Portrait/image generation, terrain images, TTS | Media generation | `src/Service/*Image*`, `src/Service/TextToSpeechIntegrationService.php` |
| Room chat logs, session hierarchy, turn logs | Chat and turn logging | `src/Controller/RoomChatController.php`, `src/Service/RoomChatService.php`, `src/Service/GameEventLogger.php` |
| Route/endpoint/form permission behavior | HTTP/API surface | `dungeoncrawler_content.routing.yml`, `src/Controller/`, `src/Form/` |

## Subsystems by runtime side

### Server-side subsystems

| Subsystem | Responsibility | Primary entry points |
|---|---|---|
| HTTP/API surface | Define route contracts, controllers, forms, and access boundaries for web/API/admin traffic | `dungeoncrawler_content.routing.yml`, `src/Controller/*Controller.php`, `src/Form/*Form.php` |
| Encounter and action engine | Run authoritative encounter phases and resolve player/NPC action outcomes | `src/Service/EncounterPhaseHandler.php`, `src/Service/GameplayActionProcessor.php`, `src/Service/GameCoordinatorService.php` |
| Character lifecycle | Own canonical character creation, progression, resources, and runtime state | `src/Controller/Character*Controller.php`, `src/Form/Character*Form.php`, `src/Service/Character*Service.php` |
| Campaign and world state | Maintain campaign clocks/state plus room/dungeon progression and transitions | `src/Service/Campaign*Service.php`, `src/Service/Room*Service.php`, `src/Service/Dungeon*Service.php` |
| Generation pipeline | Generate dungeons, rooms, maps, quests, storylines, and seeded content contracts | `src/Service/DungeonGenerationEngine.php`, `src/Service/RoomGeneratorService.php`, `src/Service/MapGeneratorService.php`, `src/Service/Quest*Service.php`, `src/Service/Storyline*Service.php` |
| NPC and institutions | Manage NPC behavior and institution/relationship social-model state | `src/Service/Npc*Service.php`, `src/Service/Institution*Service.php`, `src/Service/RelationshipManagerService.php` |
| AI and narration integration | Bridge encounter state into AI providers and narration/GM orchestration services | `src/Service/EncounterAiIntegrationService.php`, `src/Service/AiConversationEncounterAiProvider.php`, `src/Service/NarrationEngine.php`, `src/Service/GmOrchestrationBrokerService.php` |
| Merchant and economy integration | Manage merchant behavior, inventory offers, and transaction execution/contracts | `src/Service/MerchantBotService.php`, `src/Service/MerchantTransactionService.php`, `src/Controller/MerchantApiController.php` |
| Media generation | Handle portrait/terrain generation plus generated-image persistence and TTS integration | `src/Service/GeminiImageGenerationService.php`, `src/Service/VertexImageGenerationService.php`, `src/Service/GeneratedImageRepository.php`, `src/Service/TextToSpeechIntegrationService.php` |
| Chat and turn logging | Persist chat sessions, room chat events, and explicit round/turn logging contracts | `src/Controller/RoomChatController.php`, `src/Service/ChatSessionManager.php`, `src/Service/RoomChatService.php`, `src/Service/GameEventLogger.php` |

### Client-side subsystems

| Subsystem | Responsibility | Primary entry points |
|---|---|---|
| Hexmap shell and rendering | Render map/room/actor visual state and handle map-level interactions | `js/hexmap-v2.js`, `js/hexmap.js`, `js/v2/GameShell.js`, `js/v2/canvas/` |
| Action rail and panel interaction | Drive action bar tabs/buttons/panels and context-sensitive action UX | `js/v2/panels/`, `js/v2/systems/EncounterSystem.js`, `js/v2/contracts/` |
| Coordinator client bridge | Translate UI actions into server-authoritative coordinator/action API calls | `js/game-coordinator/GameCoordinator.js`, `js/game-coordinator/GameCoordinatorApi.js`, `js/v2/systems/` |
| Client state and event bus | Maintain client runtime state and propagate game UI events consistently | `js/v2/GameEventBus.js`, `js/StateManager.js`, `js/v2/services/`, `js/v2/utils/` |
| Chat/panel UX and sync | Render chat/combat panel state and keep UI synchronized with encounter updates | `js/v2/panels/ChatPanel.js`, `js/v2/panels/`, `js/game-coordinator/NarrationOverlay.js` |

## Update rule
When subsystem boundaries change, update this file in the same change set so routing remains accurate.
