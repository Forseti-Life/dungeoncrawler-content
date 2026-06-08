# Module Instructions: dungeoncrawler_content

## Purpose
`dungeoncrawler_content` is the primary gameplay module for Dungeoncrawler. It owns game runtime behavior, encounter flow, world/content generation, campaign state, and player-facing gameplay APIs/UI.

## Source of truth
- Canonical repository: `/home/ubuntu/forseti.life/dungeoncrawler-content`
- Site context: `org-chart/sites/dungeoncrawler/site.instructions.md`
- Architecture reference: `ARCHITECTURE.md`

## Subsystem map (quick routing)

| Subsystem | Responsibility | Primary entry points | Key paths |
|---|---|---|---|
| HTTP/API surface | Web routes, API controllers, admin pages, and forms | `dungeoncrawler_content.routing.yml`, `src/Controller/*Controller.php`, `src/Form/*Form.php` | `src/Controller/`, `src/Form/`, `templates/` |
| Encounter and action engine | Encounter phase orchestration, action resolution, combat/exploration flow | `src/Service/EncounterPhaseHandler.php`, `src/Service/GameplayActionProcessor.php`, `src/Service/GameCoordinatorService.php`, `src/Controller/GameCoordinatorController.php` | `src/Service/`, `js/game-coordinator/`, `js/v2/` |
| Character lifecycle | Character creation, state, progression, and equipment/runtime sync | `src/Controller/Character*Controller.php`, `src/Form/Character*Form.php`, `src/Service/Character*Service.php` | `src/Controller/Character*`, `src/Form/Character*`, `src/Service/Character*` |
| Campaign and world state | Campaign clocks/state, room state, dungeon state, and persistence boundaries | `src/Controller/Campaign*Controller.php`, `src/Controller/Room*Controller.php`, `src/Service/Campaign*Service.php`, `src/Service/Room*Service.php`, `src/Service/Dungeon*Service.php` | `src/Controller/Campaign*`, `src/Controller/Room*`, `src/Service/Campaign*`, `src/Service/Room*`, `src/Service/Dungeon*` |
| Generation pipeline | Dungeon/room/map/content/storyline/quest generation | `src/Service/DungeonGenerationEngine.php`, `src/Service/RoomGeneratorService.php`, `src/Service/MapGeneratorService.php`, `src/Service/QuestGeneratorService.php`, `src/Service/Storyline*Service.php` | `src/Service/*Generator*`, `src/Service/Storyline*`, `src/Service/Quest*` |
| NPC and institutions | NPC behavior plus institution/relationship modeling and review flows | `src/Controller/NpcController.php`, `src/Controller/InstitutionReviewBrowserController.php`, `src/Service/Npc*Service.php`, `src/Service/Institution*Service.php`, `src/Service/RelationshipManagerService.php` | `src/Controller/Npc*`, `src/Controller/Institution*`, `src/Service/Npc*`, `src/Service/Institution*`, `src/Service/Relationship*` |
| AI and narration integration | Encounter AI provider plumbing, narration, and GM orchestration | `src/Service/EncounterAiIntegrationService.php`, `src/Service/AiConversationEncounterAiProvider.php`, `src/Service/NarrationEngine.php`, `src/Service/GmOrchestrationBrokerService.php` | `src/Controller/EncounterAi*`, `src/Service/*Ai*`, `src/Service/Narration*`, `src/Service/Gm*` |
| Media generation | Portrait/terrain/image generation and TTS integrations | `src/Service/GeminiImageGenerationService.php`, `src/Service/VertexImageGenerationService.php`, `src/Service/TextToSpeechIntegrationService.php`, `src/Service/GeneratedImageRepository.php` | `src/Service/*Image*`, `src/Service/TextToSpeech*`, `images/` |
| Chat and turn logging | Room/campaign chat sessions and turn/event logs | `src/Controller/ChatSessionController.php`, `src/Controller/RoomChatController.php`, `src/Service/ChatSessionManager.php`, `src/Service/RoomChatService.php`, `src/Service/GameEventLogger.php` | `src/Controller/*Chat*`, `src/Service/*Chat*`, `src/Service/*Log*` |
| Frontend gameplay clients | Browser-side shell/hexmap/coordinator clients | `js/hexmap-v2.js`, `js/hexmap.js`, `js/game-coordinator/`, `js/v2/` | `js/`, `css/`, `templates/` |

## Update rule
When subsystem boundaries change, update this file in the same change set so routing remains accurate.
