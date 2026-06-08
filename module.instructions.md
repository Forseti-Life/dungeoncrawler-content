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
| Hexmap UI, action rail, panel behavior, V2 shell | Frontend gameplay clients | `js/hexmap-v2.js`, `js/v2/`, `js/game-coordinator/` |
| Character creation, leveling, feats, sheet state | Character lifecycle | `src/Controller/Character*`, `src/Service/Character*` |
| Campaign start/state, room transitions, dungeon state | Campaign and world state | `src/Service/Campaign*`, `src/Service/Room*`, `src/Service/Dungeon*` |
| Quest generation/progress/rewards/objectives | Generation pipeline | `src/Service/Quest*`, `src/Controller/QuestTrackerController.php` |
| NPC behavior, institution modeling, relationship state | NPC and institutions | `src/Service/Npc*`, `src/Service/Institution*`, `src/Service/RelationshipManagerService.php` |
| Encounter AI, narrator output, GM orchestration | AI and narration integration | `src/Service/EncounterAiIntegrationService.php`, `src/Service/NarrationEngine.php` |
| Portrait/image generation, terrain images, TTS | Media generation | `src/Service/*Image*`, `src/Service/TextToSpeechIntegrationService.php` |
| Room chat logs, session hierarchy, turn logs | Chat and turn logging | `src/Controller/RoomChatController.php`, `src/Service/RoomChatService.php`, `src/Service/GameEventLogger.php` |
| Route/endpoint/form permission behavior | HTTP/API surface | `dungeoncrawler_content.routing.yml`, `src/Controller/`, `src/Form/` |

## Subsystem map (quick routing)

| Subsystem | Responsibility | Authoritative contract | Primary entry points | Key paths | Verification hook |
|---|---|---|---|---|---|
| HTTP/API surface | Web routes, API controllers, admin pages, and forms | `dungeoncrawler_content.routing.yml` and matching controller/form methods define the API and page contract | `dungeoncrawler_content.routing.yml`, `src/Controller/*Controller.php`, `src/Form/*Form.php` | `src/Controller/`, `src/Form/`, `templates/` | `/var/www/html/dungeoncrawler/vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/src/Functional/Routes/CampaignRoutesTest.php` |
| Encounter and action engine | Encounter phase orchestration, action resolution, combat/exploration flow | `EncounterPhaseHandler` is the master runtime engine; chat/UI must follow its event contracts | `src/Service/EncounterPhaseHandler.php`, `src/Service/GameplayActionProcessor.php`, `src/Service/GameCoordinatorService.php`, `src/Controller/GameCoordinatorController.php` | `src/Service/`, `js/game-coordinator/`, `js/v2/` | `/var/www/html/dungeoncrawler/vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/EncounterPhaseHandlerTest.php` |
| Character lifecycle | Character creation, state, progression, and equipment/runtime sync | Server-side character sheet state is canonical for actor resources and progression | `src/Controller/Character*Controller.php`, `src/Form/Character*Form.php`, `src/Service/Character*Service.php` | `src/Controller/Character*`, `src/Form/Character*`, `src/Service/Character*` | `/var/www/html/dungeoncrawler/vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/CharacterStateServiceTest.php` |
| Campaign and world state | Campaign clocks/state, room state, dungeon state, and persistence boundaries | Room entry always transitions through unified encounter state (no combat/non-combat split) | `src/Controller/Campaign*Controller.php`, `src/Controller/Room*Controller.php`, `src/Service/Campaign*Service.php`, `src/Service/Room*Service.php`, `src/Service/Dungeon*Service.php` | `src/Controller/Campaign*`, `src/Controller/Room*`, `src/Service/Campaign*`, `src/Service/Room*`, `src/Service/Dungeon*` | `/var/www/html/dungeoncrawler/vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/src/Functional/CampaignStateValidationTest.php` |
| Generation pipeline | Dungeon/room/map/content/storyline/quest generation | Template-vs-campaign instantiation boundary must stay explicit and non-overloaded | `src/Service/DungeonGenerationEngine.php`, `src/Service/RoomGeneratorService.php`, `src/Service/MapGeneratorService.php`, `src/Service/QuestGeneratorService.php`, `src/Service/Storyline*Service.php` | `src/Service/*Generator*`, `src/Service/Storyline*`, `src/Service/Quest*` | `/var/www/html/dungeoncrawler/vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/RoomGeneratorServiceContractTest.php` |
| NPC and institutions | NPC behavior plus institution/relationship modeling and review flows | Institutions are modeled as NPC-like social subjects and retain relationship state contracts | `src/Controller/NpcController.php`, `src/Controller/InstitutionReviewBrowserController.php`, `src/Service/Npc*Service.php`, `src/Service/Institution*Service.php`, `src/Service/RelationshipManagerService.php` | `src/Controller/Npc*`, `src/Controller/Institution*`, `src/Service/Npc*`, `src/Service/Institution*`, `src/Service/Relationship*` | `/var/www/html/dungeoncrawler/vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/NpcServiceTest.php` |
| AI and narration integration | Encounter AI provider plumbing, narration, and GM orchestration | Narration/events must consume authoritative encounter state; no parallel engine contract | `src/Service/EncounterAiIntegrationService.php`, `src/Service/AiConversationEncounterAiProvider.php`, `src/Service/NarrationEngine.php`, `src/Service/GmOrchestrationBrokerService.php` | `src/Controller/EncounterAi*`, `src/Service/*Ai*`, `src/Service/Narration*`, `src/Service/Gm*` | `/var/www/html/dungeoncrawler/vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/EncounterAiIntegrationServiceTest.php` |
| Media generation | Portrait/terrain/image generation and TTS integrations | Generated media services are adapters; gameplay state remains source-of-truth elsewhere | `src/Service/GeminiImageGenerationService.php`, `src/Service/VertexImageGenerationService.php`, `src/Service/TextToSpeechIntegrationService.php`, `src/Service/GeneratedImageRepository.php` | `src/Service/*Image*`, `src/Service/TextToSpeech*`, `images/` | `/var/www/html/dungeoncrawler/vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/RoomViewImageServiceTest.php` |
| Chat and turn logging | Room/campaign chat sessions and turn/event logs | Round-start and actor-turn events must be logged; actors explicitly act or pass | `src/Controller/ChatSessionController.php`, `src/Controller/RoomChatController.php`, `src/Service/ChatSessionManager.php`, `src/Service/RoomChatService.php`, `src/Service/GameEventLogger.php` | `src/Controller/*Chat*`, `src/Service/*Chat*`, `src/Service/*Log*` | `/var/www/html/dungeoncrawler/vendor/bin/phpunit web/modules/custom/dungeoncrawler_content/tests/src/Unit/Controller/RoomChatControllerProgressTest.php` |
| Frontend gameplay clients | Browser-side shell/hexmap/coordinator clients | Frontend executes server-authoritative contracts; client does not invent engine state | `js/hexmap-v2.js`, `js/hexmap.js`, `js/game-coordinator/`, `js/v2/` | `js/`, `css/`, `templates/` | `cd /home/ubuntu/forseti.life/dungeoncrawler-content && node tests/action_rail_contract_routing_test.js` |

## Update rule
When subsystem boundaries change, update this file in the same change set so routing remains accurate.
