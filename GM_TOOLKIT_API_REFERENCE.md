# GM Toolkit API Reference

This document inventories the `dungeoncrawler_content` JSON API surface for GM-facing tooling and runtime integrations.

**Source of truth**: `dungeoncrawler_content.routing.yml`

## Why this exists

- `API_DOCUMENTATION.md` documents only part of the runtime API surface.
- The GM toolkit needs a fuller inventory so the AI GM and human GMs know which endpoints exist and what each cluster is for.
- This reference groups the full `/api` surface by GM-oriented domain instead of leaving it as a raw route dump.

## Inventory summary

- Total `/api` route entries: **228**
- Unique `/api` resource paths: **206**
- Access requirements vary by route; use the routing file as the authority for permissions, CSRF, and access checks.

| Category | Route entries | GM use |
|---|---:|---|
| GM orchestration | 34 | GM-only guidance, narrative, rulings, rarity/safety controls, dashboards, and AI-context endpoints. |
| Campaign runtime | 53 | Campaign-scoped state, sessions, quests, storylines, subsystem state, and generated content orchestration. |
| Dungeon & room state | 10 | Dungeon/room state reads and room-level environment surfaces. |
| Encounters & entities | 24 | Combat state, initiative, encounter actions, and runtime entity lifecycle control. |
| NPCs & social | 7 | NPC CRUD, social checks, and NPC history for campaign management. |
| Characters & progression | 39 | Character state, creation helpers, level-up, traits, actions, and class-specific runtime mechanics. |
| Rules reference | 12 | Reference endpoints for spells, focus spells, feats, and related rules lookups. |
| Inventory & economy | 8 | Inventory mutation, transfer, selling, capacity, and item-location tools. |
| Familiars & companions | 15 | Familiar, animal companion, construct companion, and eidolon helper endpoints. |
| Media & presentation | 4 | Generated images, sprites, and presentation/media retrieval helpers. |
| Other API | 22 | Supporting endpoints that do not fit the main GM toolkit buckets but are still part of the `/api` surface. |

## GM lookup map: when a player asks about X, use Y

Use the runtime APIs first. Drop to services/controllers only when you are working inside PHP code or extending the product.

| Player asks about... | Primary API/tool | Internal source/controller | Notes |
|---|---|---|---|
| A spell | `/api/spells` or `/api/spells/{spell_id}` | `SpellCatalogController`, `SpellCatalogService` | Includes AoN fallback for missing spells and rituals. |
| A focus spell | `/api/focus-spells` | `FocusSpellCatalogController`, `SpellCatalogService` | Same canonical spell registry, filtered to focus entries. |
| A feat | `/api/feats` or `/api/feats/{feat_id}` | `FeatCatalogController`, feat catalogs in `CharacterManager` | Includes AoN fallback for feats not in the local catalog. |
| Spell / level / simple DCs | `/api/dc/level`, `/api/dc/simple`, `/api/dc/spell-level`, `/api/dc/adjustment` | `DcApiController` | Use for quick rules math rather than hard-coding tables in prompts. |
| Character sheet summary | `/api/character/{character_id}/summary` | `CharacterStateController` | Best lightweight “what does this character currently look like?” read. |
| Full character runtime state | `/api/character/{character_id}/state` | `CharacterStateController`, `CharacterStateService` | Use when the GM needs the authoritative full sheet/runtime payload. |
| Character traits / checks | `/api/character/{character_id}/traits`, `/api/character/{character_id}/traits/check` | `CharacterTraitsController` | Good for rules checks tied to ancestry/class/trait tags. |
| Character feat effects | `/api/character/{character_id}/feat-effects` | `FeatEffectController` | Use when a question is really about what a feat is doing to the sheet. |
| Eligible level-up feats | `/api/character/{character_id}/level-up/feats` | `CharacterLevelingController` | Use for “what can I choose now?” questions. |
| Inventory / carried items | `/api/inventory/{owner_type}/{owner_id}` | `InventoryManagementController`, `InventoryManagementService` | Best read surface for bags, containers, room storage, and equipment locations. |
| Familiar / companion state | `/api/character/{character_id}/familiar`, `/api/character/{character_id}/animal-companion` | `FamiliarController`, `AnimalCompanionController` | Use these before inferring companion state from the sheet manually. |
| Current room state | `/api/dungeon/{dungeon_id}/room/{room_id}/state` plus `/api/campaign/{campaign_id}/room/{room_id}/chat` and `/view-image` | `RoomStateController`, `RoomChatController`, `RoomViewImageController` | Room truth is split across state, chat/channels, entities, and view image. |
| NPC details / history | `/api/campaign/{campaign_id}/npcs`, `/api/campaign/{campaign_id}/npcs/{npc_id}`, `/history` | `NpcController` | Use for campaign NPC truth, not free-text memory. |
| Combat / encounter state | `/api/combat/state`, `/api/combat/{encounter_id}/...` | `CombatEncounterApiController`, `CombatApiController` | Use for initiative, participants, conditions, HP, and encounter log. |
| Campaign quests / storyline / subsystem state | `/api/campaign/{campaign_id}/quests...`, `/storylines...`, `/subsystems...` | `Quest*Controller`, `StorylineController`, `SubsystemController` | These are the GM’s structured state surfaces for ongoing narrative systems. |
| GM reference / rarity / rulings / dashboard data | `/api/gm/reference...`, `/api/campaign/{campaign_id}/gm/...` | `GmRunningGuideController` | This is the GM’s explicit orchestration and policy layer. |

## GM mutation authority: what the GM is allowed to update

### Rule of thumb

- The GM is authorized to update **campaign-instance values only**: the live state of rooms, encounters, NPCs, quests, subsystems, and character-sheet values **inside their campaign instance**.
- The GM is **not** authorized to update the canonical content library, packaged reference catalogs, or application code.
- In other words: the GM can change **what is true in this campaign right now**, but not **the global definition of a spell, feat, item, creature, or rule**, and not the code that powers the game.
- If you need to change something, use the narrowest endpoint that matches the change.
- **There is no single blanket room update route** and **no single blanket character-sheet update route**; the state is intentionally decomposed by subsystem.

### Character sheet mutation surfaces

These are the main APIs the GM can use to update **character-sheet values in a campaign instance**:

| Sheet concern | Update APIs |
|---|---|
| Core state snapshot | `/api/character/{character_id}/update` |
| HP / damage | `/api/character/{character_id}/hp` |
| Conditions | `/api/character/{character_id}/conditions`, `/api/character/{character_id}/conditions/{condition_id}` |
| Action / turn tracking | `/api/character/{character_id}/actions`, `/api/character/{character_id}/start-turn` |
| Spellcasting | `/api/character/{character_id}/cast-spell`, `/api/character/{character_id}/spellstrike`, `/api/character/{character_id}/spellstrike/recharge` |
| Inventory on the sheet | `/api/character/{character_id}/inventory`, plus `/api/inventory/...` for item-level mutations |
| XP / milestones / level-up | `/api/character/{character_id}/experience`, `/api/character/{character_id}/milestone`, `/api/character/{character_id}/level-up...` |
| Familiar state | `/api/character/{character_id}/familiar...` |
| Animal / construct companions | `/api/character/{character_id}/animal-companion...`, `/api/character/{character_id}/construct-companion` |
| Eidolon state | `/api/character/{character_id}/eidolon`, `/api/character/{character_id}/eidolon/dismiss` |
| Class-specific runtime toggles | Arcane Cascade, Overdrive, firearm actions, unstable actions, etc. under `/api/character/{character_id}/...` |

### Room and scene mutation surfaces

These are the main APIs the GM can use to update **room/scene values in a campaign instance**:

| Room/scene concern | Update APIs |
|---|---|
| Entities placed in the room | `/api/campaign/{campaign_id}/entity/spawn`, `/move`, `DELETE /entity/{instance_id}` |
| Encounter/combat state in the room | `/api/combat/start`, `/api/combat/action`, `/api/combat/attack`, `/api/combat/end-turn`, `/api/combat/{encounter_id}/participants...` |
| NPC state in the room | `/api/campaign/{campaign_id}/npcs`, `/api/campaign/{campaign_id}/npcs/{npc_id}`, `/social-check` |
| Room conversation / player-visible chat | `/api/campaign/{campaign_id}/room/{room_id}/chat...` |
| Room channels / side conversations | `/api/campaign/{campaign_id}/room/{room_id}/channels...` |
| Room imagery / view refresh | `/api/campaign/{campaign_id}/room/{room_id}/view-image`, plus campaign image surfaces |
| Room generation / regeneration | `/api/campaign/{campaign_id}/dungeons/{dungeon_id}/rooms/{room_id}/regenerate` |
| Quests, storylines, subsystem state affecting the room | `/api/campaign/{campaign_id}/quests...`, `/storylines...`, `/subsystems...` |
| GM rulings / rarity / safety / scene metadata affecting play in the room | `/api/campaign/{campaign_id}/gm/...` |

### Important operational guidance

1. **Use reference APIs for questions; use mutation APIs for state changes.** Do not answer rules questions from mutable campaign state when a canonical reference endpoint exists.
2. **Use AoN fallback only when the local canonical catalog misses.** For spells and feats, the local library stays primary.
3. **Assume the room truth is composite.** To know “everything in a room,” combine room state, entities, combat state, NPC state, room chat, and view-image surfaces.
4. **Assume the character-sheet truth is composite.** To know “everything on a character sheet,” combine summary/state with traits, feat effects, inventory, spells, familiar/companion state, and active conditions.
5. **Never use GM mutation authority to edit library content or code.** Canonical library changes belong to content import/admin workflows; code changes belong to development workflows.

## GM toolkit categories

### GM orchestration

GM-only guidance, narrative, rulings, rarity/safety controls, dashboards, and AI-context endpoints.

| Methods | Path | Handler |
|---|---|---|
| `GET, POST` | `/api/campaign/{campaign_id}/gm-private/{character_id}` | `ChatSessionController::getGmPrivate | ChatSessionController::postGmPrivate` |
| `POST` | `/api/campaign/{campaign_id}/gm/adventure/scene` | `GmRunningGuideController::recordSceneDesign` |
| `GET` | `/api/campaign/{campaign_id}/gm/adventure/scene-summary` | `GmRunningGuideController::getSceneTypeSummary` |
| `GET, POST` | `/api/campaign/{campaign_id}/gm/campaign-design` | `GmRunningGuideController::getCampaignDesign | GmRunningGuideController::setCampaignDesign` |
| `GET` | `/api/campaign/{campaign_id}/gm/dashboard` | `GmRunningGuideController::getDashboard` |
| `POST` | `/api/campaign/{campaign_id}/gm/dashboard/refresh` | `GmRunningGuideController::refreshDashboard` |
| `GET, POST` | `/api/campaign/{campaign_id}/gm/encounter/{encounter_id}/metadata` | `GmRunningGuideController::getEncounterMetadata | GmRunningGuideController::setEncounterMetadata` |
| `POST` | `/api/campaign/{campaign_id}/gm/locations/request` | `LocationGenerationController::requestLocation` |
| `POST` | `/api/campaign/{campaign_id}/gm/quests/request` | `LocationGenerationController::requestLocationQuests` |
| `GET, POST` | `/api/campaign/{campaign_id}/gm/rarity` | `GmRunningGuideController::getRarityAllowlist | GmRunningGuideController::setRarityAllowlist` |
| `POST` | `/api/campaign/{campaign_id}/gm/rarity/evaluate` | `GmRunningGuideController::evaluateRarity` |
| `POST` | `/api/campaign/{campaign_id}/gm/rooms/request` | `LocationGenerationController::requestRoom` |
| `GET, POST` | `/api/campaign/{campaign_id}/gm/rulings` | `GmRunningGuideController::createRuling | GmRunningGuideController::listRulings` |
| `PATCH` | `/api/campaign/{campaign_id}/gm/rulings/{ruling_id}/review` | `GmRunningGuideController::reviewRuling` |
| `GET, POST` | `/api/campaign/{campaign_id}/gm/safety` | `GmRunningGuideController::getSafety | GmRunningGuideController::setSafety` |
| `POST` | `/api/campaign/{campaign_id}/gm/secret-check/reveal` | `GmRunningGuideController::revealSecretCheck` |
| `GET, POST` | `/api/campaign/{campaign_id}/gm/session-zero` | `GmRunningGuideController::getSessionZero | GmRunningGuideController::setSessionZero` |
| `POST` | `/api/campaign/{campaign_id}/gm/story-points/reset` | `GmRunningGuideController::resetStoryPoints` |
| `GET` | `/api/campaign/{campaign_id}/gm/story-points/{player_id}` | `GmRunningGuideController::getStoryPoints` |
| `POST` | `/api/campaign/{campaign_id}/gm/story-points/{player_id}/award` | `GmRunningGuideController::awardStoryPoints` |
| `POST` | `/api/campaign/{campaign_id}/gm/story-points/{player_id}/spend` | `GmRunningGuideController::spendStoryPoint` |
| `GET` | `/api/campaign/{campaign_id}/play-sessions/ai-context` | `PlaySessionController::getAiGmContext` |
| `POST` | `/api/character/gm-chat` | `CharacterApiController::gmChat` |
| `GET, POST` | `/api/gm/reference` | `GmRunningGuideController::searchReference | GmRunningGuideController::upsertReferenceEntry` |
| `GET` | `/api/gm/reference/section/{section}` | `GmRunningGuideController::getReferenceBySection` |
| `GET` | `/api/gm/reference/{id}` | `GmRunningGuideController::getReferenceEntry` |

### Campaign runtime

Campaign-scoped state, sessions, quests, storylines, subsystem state, and generated content orchestration.

| Methods | Path | Handler |
|---|---|---|
| `POST` | `/api/campaign/{campaign_id}/dungeons/generate` | `DungeonGeneratorController::generateDungeon` |
| `GET` | `/api/campaign/{campaign_id}/dungeons/{dungeon_id}` | `DungeonGeneratorController::getDungeon` |
| `POST` | `/api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels` | `DungeonGeneratorController::addDungeonLevel` |
| `GET` | `/api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels/{depth}` | `DungeonGeneratorController::getDungeonLevel` |
| `POST` | `/api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels/{depth}/rooms` | `RoomGeneratorController::createRoom` |
| `GET` | `/api/campaign/{campaign_id}/dungeons/{dungeon_id}/rooms/{room_id}` | `RoomGeneratorController::getRoom` |
| `POST` | `/api/campaign/{campaign_id}/dungeons/{dungeon_id}/rooms/{room_id}/regenerate` | `RoomGeneratorController::regenerateRoom` |
| `GET` | `/api/campaign/{campaign_id}/entities` | `CampaignEntityController::listEntities` |
| `GET` | `/api/campaign/{campaign_id}/images` | `GeneratedImageApiController::getCampaignImages` |
| `POST` | `/api/campaign/{campaign_id}/invite` | `PlaySessionController::invitePlayer` |
| `POST` | `/api/campaign/{campaign_id}/narration/flush` | `ChatSessionController::flushNarration` |
| `GET` | `/api/campaign/{campaign_id}/narrative/{character_id}` | `ChatSessionController::getCharacterNarrative` |
| `GET, POST` | `/api/campaign/{campaign_id}/party-chat` | `ChatSessionController::getPartyChat | ChatSessionController::postPartyChat` |
| `GET` | `/api/campaign/{campaign_id}/play-sessions` | `PlaySessionController::listCampaignSessions` |
| `GET` | `/api/campaign/{campaign_id}/play-sessions/latest-state` | `PlaySessionController::getLatestState` |
| `GET` | `/api/campaign/{campaign_id}/quest-confirmations` | `QuestTrackerController::listTouchpointConfirmations` |
| `POST` | `/api/campaign/{campaign_id}/quest-confirmations/{confirmation_id}/resolve` | `QuestTrackerController::resolveTouchpointConfirmation` |
| `GET` | `/api/campaign/{campaign_id}/quest-journal` | `QuestTrackerController::getCampaignQuestJournal` |
| `POST` | `/api/campaign/{campaign_id}/quest-touchpoints` | `QuestTrackerController::ingestTouchpoint` |
| `GET` | `/api/campaign/{campaign_id}/quests/available` | `QuestTrackerController::getAvailableQuests` |
| `POST` | `/api/campaign/{campaign_id}/quests/generate` | `QuestGeneratorController::generate` |
| `POST` | `/api/campaign/{campaign_id}/quests/generate-for-location` | `QuestGeneratorController::generateForLocation` |
| `POST` | `/api/campaign/{campaign_id}/quests/{quest_id}/complete` | `QuestTrackerController::completeQuest` |
| `PUT` | `/api/campaign/{campaign_id}/quests/{quest_id}/progress` | `QuestTrackerController::updateProgress` |
| `GET` | `/api/campaign/{campaign_id}/quests/{quest_id}/rewards` | `QuestRewardController::getRewardSummary` |
| `POST` | `/api/campaign/{campaign_id}/quests/{quest_id}/rewards/claim` | `QuestRewardController::claimRewards` |
| `POST` | `/api/campaign/{campaign_id}/quests/{quest_id}/start` | `QuestTrackerController::startQuest` |
| `GET` | `/api/campaign/{campaign_id}/sessions` | `ChatSessionController::listSessions` |
| `GET, POST` | `/api/campaign/{campaign_id}/sessions/{session_id}/messages` | `ChatSessionController::getSessionMessages | ChatSessionController::postSessionMessage` |
| `GET` | `/api/campaign/{campaign_id}/state` | `CampaignStateController::getState` |
| `GET, POST` | `/api/campaign/{campaign_id}/storylines` | `StorylineController::createCampaignStoryline | StorylineController::listCampaignStorylines` |
| `GET` | `/api/campaign/{campaign_id}/storylines/contacts` | `StorylineController::getCampaignStorylineContacts` |
| `GET` | `/api/campaign/{campaign_id}/storylines/{storyline_id}` | `StorylineController::getCampaignStoryline` |
| `POST` | `/api/campaign/{campaign_id}/storylines/{storyline_id}/activate` | `StorylineController::activateCampaignStoryline` |
| `POST` | `/api/campaign/{campaign_id}/storylines/{storyline_id}/advance` | `StorylineController::advanceCampaignStoryline` |
| `GET` | `/api/campaign/{campaign_id}/storylines/{storyline_id}/journal` | `StorylineController::getCampaignStorylineJournal` |
| `GET, POST` | `/api/campaign/{campaign_id}/subsystems` | `SubsystemController::initiateSubsystem | SubsystemController::listSubsystems` |
| `GET` | `/api/campaign/{campaign_id}/subsystems/active` | `SubsystemController::listActiveSubsystems` |
| `GET` | `/api/campaign/{campaign_id}/subsystems/{session_id}` | `SubsystemController::getSubsystem` |
| `GET` | `/api/campaign/{campaign_id}/subsystems/{session_id}/fail-condition` | `SubsystemController::checkFailCondition` |
| `POST` | `/api/campaign/{campaign_id}/subsystems/{session_id}/resolve` | `SubsystemController::resolveSubsystem` |
| `POST` | `/api/campaign/{campaign_id}/subsystems/{session_id}/turn` | `SubsystemController::takeTurn` |
| `GET` | `/api/campaign/{campaign_id}/subsystems/{session_id}/win-condition` | `SubsystemController::checkWinCondition` |
| `GET` | `/api/campaign/{campaign_id}/system-log` | `ChatSessionController::getSystemLog` |
| `GET` | `/api/campaign/{campaign_id}/variant-rules` | `SubsystemController::getVariantRules` |
| `POST` | `/api/campaign/{campaign_id}/variant-rules/{rule}` | `SubsystemController::setVariantRule` |
| `GET` | `/api/campaign/{campaign_id}/variant-rules/{rule}/compatibility` | `SubsystemController::checkRuleCompatibility` |
| `GET` | `/api/campaign/{campaign_id}/visited-locations` | `CampaignController::listVisitedLocations` |
| `GET` | `/api/campaign/{campaign_id}/xp-total/{character_id}` | `PlaySessionController::getCampaignCharacterXp` |

### Dungeon & room state

Dungeon/room state reads and room-level environment surfaces.

| Methods | Path | Handler |
|---|---|---|
| `GET, POST` | `/api/campaign/{campaign_id}/room/{room_id}/channels` | `RoomChatController::getChannels | RoomChatController::openChannel` |
| `DELETE` | `/api/campaign/{campaign_id}/room/{room_id}/channels/{channel_key}` | `RoomChatController::closeChannel` |
| `GET, POST` | `/api/campaign/{campaign_id}/room/{room_id}/chat` | `RoomChatController::getChatHistory | RoomChatController::postChatMessage` |
| `POST` | `/api/campaign/{campaign_id}/room/{room_id}/chat/player-suggestion` | `RoomChatController::suggestPlayerAutomationMessage` |
| `GET` | `/api/campaign/{campaign_id}/room/{room_id}/view-image` | `RoomViewImageController::getRoomViewImage` |
| `GET` | `/api/dungeon/{dungeon_id}/room/{room_id}/state` | `RoomStateController::getState` |
| `GET, POST` | `/api/dungeon/{dungeon_id}/state` | `DungeonStateController::getState | DungeonStateController::setState` |

### Encounters & entities

Combat state, initiative, encounter actions, and runtime entity lifecycle control.

| Methods | Path | Handler |
|---|---|---|
| `POST` | `/api/campaign/{campaign_id}/entity/spawn` | `CampaignEntityController::spawnEntity` |
| `DELETE` | `/api/campaign/{campaign_id}/entity/{instance_id}` | `CampaignEntityController::despawnEntity` |
| `POST` | `/api/campaign/{campaign_id}/entity/{instance_id}/move` | `CampaignEntityController::moveEntity` |
| `POST` | `/api/combat/action` | `CombatEncounterApiController::action` |
| `POST` | `/api/combat/attack` | `CombatEncounterApiController::attack` |
| `POST` | `/api/combat/end` | `CombatEncounterApiController::end` |
| `POST` | `/api/combat/end-turn` | `CombatEncounterApiController::endTurn` |
| `POST` | `/api/combat/get` | `CombatEncounterApiController::get` |
| `POST` | `/api/combat/recommendation-preview` | `EncounterAiPreviewController::preview` |
| `POST` | `/api/combat/set` | `CombatEncounterApiController::set` |
| `POST` | `/api/combat/start` | `CombatEncounterApiController::start` |
| `GET` | `/api/combat/state` | `CombatEncounterApiController::currentState` |
| `GET` | `/api/combat/{encounter_id}/initiative` | `CombatApiController::getInitiative` |
| `POST` | `/api/combat/{encounter_id}/initiative/reroll` | `CombatApiController::rerollInitiative` |
| `GET` | `/api/combat/{encounter_id}/log` | `CombatApiController::getLog` |
| `POST` | `/api/combat/{encounter_id}/participants` | `CombatApiController::addParticipant` |
| `POST` | `/api/combat/{encounter_id}/participants/{participant_id}` | `CombatApiController::updateParticipant` |
| `GET, POST` | `/api/combat/{encounter_id}/participants/{participant_id}/conditions` | `CombatApiController::applyCondition | CombatApiController::listConditions` |
| `POST` | `/api/combat/{encounter_id}/participants/{participant_id}/conditions/{condition_id}/remove` | `CombatApiController::removeCondition` |
| `POST` | `/api/combat/{encounter_id}/participants/{participant_id}/hp` | `CombatApiController::updateHP` |
| `POST` | `/api/combat/{encounter_id}/participants/{participant_id}/remove` | `CombatApiController::removeParticipant` |
| `POST` | `/api/combat/{encounter_id}/participants/{participant_id}/temp-hp` | `CombatApiController::applyTempHP` |
| `GET` | `/api/combat/{encounter_id}/statistics` | `CombatApiController::getStatistics` |

### NPCs & social

NPC CRUD, social checks, and NPC history for campaign management.

| Methods | Path | Handler |
|---|---|---|
| `GET, POST` | `/api/campaign/{campaign_id}/npcs` | `NpcController::createNpc | NpcController::listNpcs` |
| `DELETE, GET, PATCH` | `/api/campaign/{campaign_id}/npcs/{npc_id}` | `NpcController::deleteNpc | NpcController::getNpc | NpcController::updateNpc` |
| `GET` | `/api/campaign/{campaign_id}/npcs/{npc_id}/history` | `NpcController::getNpcHistory` |
| `POST` | `/api/campaign/{campaign_id}/npcs/{npc_id}/social-check` | `NpcController::socialCheck` |

### Characters & progression

Character state, creation helpers, level-up, traits, actions, and class-specific runtime mechanics.

| Methods | Path | Handler |
|---|---|---|
| `GET` | `/api/campaign/{campaign_id}/character/{character_id}/quest-journal` | `QuestTrackerController::getQuestJournal` |
| `GET` | `/api/character/generate-name` | `CharacterApiController::generateName` |
| `GET` | `/api/character/load/{character_id}` | `CharacterApiController::loadCharacter` |
| `POST` | `/api/character/save` | `CharacterApiController::saveCharacter` |
| `POST` | `/api/character/{character_id}/act-together` | `SomController::actTogether` |
| `POST` | `/api/character/{character_id}/actions` | `CharacterStateController::recordAction` |
| `POST` | `/api/character/{character_id}/arcane-cascade` | `SomController::arcaneCascade` |
| `POST` | `/api/character/{character_id}/cast-spell` | `CharacterStateController::castSpell` |
| `POST` | `/api/character/{character_id}/class-subtype` | `GunGearsController::selectClassSubtype` |
| `POST` | `/api/character/{character_id}/conditions` | `CharacterStateController::addCondition` |
| `DELETE` | `/api/character/{character_id}/conditions/{condition_id}` | `CharacterStateController::removeCondition` |
| `POST` | `/api/character/{character_id}/experience` | `CharacterStateController::gainExperience` |
| `GET` | `/api/character/{character_id}/feat-effects` | `FeatEffectController::getCharacterFeatEffects` |
| `GET` | `/api/character/{character_id}/feat-effects/todo-review` | `FeatEffectController::getCharacterFeatTodoReview` |
| `POST` | `/api/character/{character_id}/firearm/{weapon_id}/clear-jam` | `GunGearsController::firearmClearJam` |
| `POST` | `/api/character/{character_id}/firearm/{weapon_id}/fire` | `GunGearsController::firearmFire` |
| `PATCH` | `/api/character/{character_id}/firearm/{weapon_id}/mode` | `GunGearsController::firearmSwitchMode` |
| `POST` | `/api/character/{character_id}/firearm/{weapon_id}/reload` | `GunGearsController::firearmReload` |
| `POST` | `/api/character/{character_id}/hp` | `CharacterStateController::updateHitPoints` |
| `POST` | `/api/character/{character_id}/level-up` | `CharacterLevelingController::triggerLevelUp` |
| `POST` | `/api/character/{character_id}/level-up/ability-boosts` | `CharacterLevelingController::submitAbilityBoosts` |
| `POST` | `/api/character/{character_id}/level-up/admin-force` | `CharacterLevelingController::adminForce` |
| `POST` | `/api/character/{character_id}/level-up/admin-reset` | `CharacterLevelingController::adminReset` |
| `POST` | `/api/character/{character_id}/level-up/feat` | `CharacterLevelingController::submitFeat` |
| `POST` | `/api/character/{character_id}/level-up/skill-increase` | `CharacterLevelingController::submitSkillIncrease` |
| `GET` | `/api/character/{character_id}/level-up/status` | `CharacterLevelingController::getStatus` |
| `POST` | `/api/character/{character_id}/milestone` | `CharacterLevelingController::setMilestone` |
| `POST` | `/api/character/{character_id}/overdrive` | `GunGearsController::overdrive` |
| `POST` | `/api/character/{character_id}/som/class-subtype` | `SomController::selectSomSubtype` |
| `POST` | `/api/character/{character_id}/start-turn` | `CharacterStateController::startTurn` |
| `GET` | `/api/character/{character_id}/state` | `CharacterStateController::getState` |
| `GET` | `/api/character/{character_id}/summary` | `CharacterStateController::getSummary` |
| `GET` | `/api/character/{character_id}/traits` | `CharacterTraitsController::getTraits` |
| `GET` | `/api/character/{character_id}/traits/check` | `CharacterTraitsController::checkTraits` |
| `POST` | `/api/character/{character_id}/unstable-action` | `GunGearsController::unstableAction` |
| `POST` | `/api/character/{character_id}/update` | `CharacterStateController::updateState` |
| `GET, POST` | `/api/characters/ability-scores/available-boosts/{step}` | `AbilityScoreApiController::getAvailableBoosts` |
| `POST` | `/api/characters/ability-scores/calculate` | `AbilityScoreApiController::calculate` |
| `POST` | `/api/characters/ability-scores/validate-boost` | `AbilityScoreApiController::validateBoost` |

### Rules reference

Reference endpoints for spells, focus spells, feats, and related rules lookups.

| Methods | Path | Handler |
|---|---|---|
| `GET` | `/api/character/{character_id}/level-up/feats` | `CharacterLevelingController::getEligibleFeats` |
| `POST` | `/api/character/{character_id}/spellstrike` | `SomController::spellstrike` |
| `POST` | `/api/character/{character_id}/spellstrike/recharge` | `SomController::rechargeSpellstrike` |
| `GET` | `/api/dc/adjustment` | `DcApiController::dcAdjustment` |
| `GET` | `/api/dc/level` | `DcApiController::levelDc` |
| `GET` | `/api/dc/simple` | `DcApiController::simpleDc` |
| `GET` | `/api/dc/spell-level` | `DcApiController::spellLevelDc` |
| `GET` | `/api/feats` | `FeatCatalogController::catalog` |
| `GET` | `/api/feats/{feat_id}` | `FeatCatalogController::get` |
| `GET` | `/api/focus-spells` | `FocusSpellCatalogController::catalog` |
| `GET` | `/api/spells` | `SpellCatalogController::list` |
| `GET` | `/api/spells/{spell_id}` | `SpellCatalogController::get` |

### Inventory & economy

Inventory mutation, transfer, selling, capacity, and item-location tools.

| Methods | Path | Handler |
|---|---|---|
| `POST` | `/api/character/{character_id}/inventory` | `CharacterStateController::updateInventory` |
| `POST` | `/api/inventory/transfer` | `InventoryManagementController::transferItems` |
| `GET` | `/api/inventory/{owner_type}/{owner_id}` | `InventoryManagementController::getInventory` |
| `GET` | `/api/inventory/{owner_type}/{owner_id}/capacity` | `InventoryManagementController::getCapacity` |
| `POST` | `/api/inventory/{owner_type}/{owner_id}/item` | `InventoryManagementController::addItem` |
| `DELETE` | `/api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}` | `InventoryManagementController::removeItem` |
| `POST` | `/api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}/location` | `InventoryManagementController::changeItemLocation` |
| `POST` | `/api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}/sell` | `InventoryManagementController::sellItem` |

### Familiars & companions

Familiar, animal companion, construct companion, and eidolon helper endpoints.

| Methods | Path | Handler |
|---|---|---|
| `GET, POST` | `/api/character/{character_id}/animal-companion` | `AnimalCompanionController::createCompanion | AnimalCompanionController::getCompanion` |
| `GET` | `/api/character/{character_id}/animal-companion/catalog` | `AnimalCompanionController::getCatalog` |
| `POST` | `/api/character/{character_id}/animal-companion/specialization` | `AnimalCompanionController::selectSpecialization` |
| `POST` | `/api/character/{character_id}/construct-companion` | `GunGearsController::constructCompanion` |
| `POST` | `/api/character/{character_id}/eidolon` | `SomController::createOrUpdateEidolon` |
| `PATCH` | `/api/character/{character_id}/eidolon/dismiss` | `SomController::dismissEidolon` |
| `GET, POST` | `/api/character/{character_id}/familiar` | `FamiliarController::createFamiliar | FamiliarController::getFamiliar` |
| `GET` | `/api/character/{character_id}/familiar/abilities` | `FamiliarController::getAvailableAbilities` |
| `POST` | `/api/character/{character_id}/familiar/daily-abilities` | `FamiliarController::selectDailyAbilities` |
| `POST` | `/api/character/{character_id}/familiar/damage` | `FamiliarController::applyDamage` |
| `POST` | `/api/character/{character_id}/familiar/replace` | `FamiliarController::replaceFamiliar` |
| `POST` | `/api/character/{character_id}/familiar/touch-spell` | `FamiliarController::deliverTouchSpell` |
| `POST` | `/api/character/{character_id}/familiar/witch-spells` | `FamiliarController::storeWitchSpells` |

### Media & presentation

Generated images, sprites, and presentation/media retrieval helpers.

| Methods | Path | Handler |
|---|---|---|
| `GET` | `/api/image/{image_uuid}` | `GeneratedImageApiController::getImage` |
| `GET` | `/api/images/object/{table_name}/{object_id}` | `GeneratedImageApiController::getObjectImages` |
| `GET` | `/api/sprite/{sprite_id}` | `GeneratedImageApiController::getSprite` |
| `POST` | `/api/sprites/resolve` | `GeneratedImageApiController::resolveSprites` |

### Other API

Supporting endpoints that do not fit the main GM toolkit buckets but are still part of the `/api` surface.

| Methods | Path | Handler |
|---|---|---|
| `GET` | `/api/creatures` | `CreatureCatalogController::list` |
| `POST` | `/api/creatures/import` | `CreatureCatalogController::import` |
| `GET` | `/api/creatures/{creature_id}` | `CreatureCatalogController::get` |
| `POST` | `/api/creatures/{creature_id}/override` | `CreatureCatalogController::override` |
| `GET` | `/api/deities` | `DeityController::list` |
| `GET` | `/api/deities/{deity_id}` | `DeityController::get` |
| `POST` | `/api/game/{campaign_id}/action` | `GameCoordinatorController::action` |
| `GET` | `/api/game/{campaign_id}/events` | `GameCoordinatorController::events` |
| `POST` | `/api/game/{campaign_id}/player-agent/step` | `GameCoordinatorController::playerAgentStep` |
| `GET` | `/api/game/{campaign_id}/state` | `GameCoordinatorController::getState` |
| `POST` | `/api/game/{campaign_id}/transition` | `GameCoordinatorController::transition` |
| `POST` | `/api/sessions/start` | `PlaySessionController::startSession` |
| `GET` | `/api/sessions/{session_id}` | `PlaySessionController::getSession` |
| `POST` | `/api/sessions/{session_id}/end` | `PlaySessionController::endSession` |
| `GET, POST` | `/api/storylines/templates` | `StorylineController::importTemplate | StorylineController::listTemplates` |
| `GET` | `/api/variant-rules/abp-table` | `SubsystemController::getAbpTable` |
| `GET` | `/api/variant-rules/abp/{level}` | `SubsystemController::getAbpBonuses` |
| `GET` | `/api/variant-rules/ancestry-paragon/schedule` | `SubsystemController::getAncestryParagonSchedule` |
| `GET` | `/api/variant-rules/free-archetype/schedule` | `SubsystemController::getFreeArchetypeSchedule` |
| `GET` | `/api/variant-rules/pwl-table` | `SubsystemController::getPwlTable` |
| `GET` | `/api/variant-rules/pwl/{rank}` | `SubsystemController::getPwlBonus` |
