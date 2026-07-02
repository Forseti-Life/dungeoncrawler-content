# Campaign Creation Process Flow (Create → Select Character → Chat Boot)

This document traces the **existing** campaign creation flow from the moment a player clicks **Create Campaign**, through **character selection**, and into the **hexmap v2 shell** where the chat panel hydrates.

## Step 1 — Render “Create Campaign” page (GET)

**Route**
- `dungeoncrawler_content.campaign_create` → `GET /campaigns/create`
  - File: `dungeoncrawler_content.routing.yml`

**Controller**
- File: `src/Controller/CampaignController.php`
- Function: `CampaignController::createCampaignPage()`
  - Builds render array for `#theme: management_form_page`
  - Supplies Form API form: `CampaignCreateForm`

## Step 2 — Build the Create Campaign form (Form API build)

**Form**
- File: `src/Form/CampaignCreateForm.php`
- Function: `CampaignCreateForm::buildForm()`
  - Defines fields: `name`, `theme`, `difficulty`
  - Attaches `dungeoncrawler_content/character-sheet` library

## Step 3 — Submit Create Campaign form (POST)

**Route**
- `dungeoncrawler_content.campaign_create.post` → `POST /campaigns/create`
  - File: `dungeoncrawler_content.routing.yml`
  - Same controller entry point; Drupal Form API handles submit pipeline.

**Validation**
- File: `src/Form/CampaignCreateForm.php`
- Function: `CampaignCreateForm::validateForm()`
  - Builds canonical payload via `CampaignCreateForm::buildCampaignPayload()`
  - Validates via `SchemaLoader::validateCampaignData()`

**Submit**
- File: `src/Form/CampaignCreateForm.php`
- Function: `CampaignCreateForm::submitForm()`
  - Calls `CampaignInitializationService::initializeCampaign(...)`
  - On success redirects to `dungeoncrawler_content.campaign_tavernentrance`

## Step 4 — Initialize campaign runtime data (server-side seed)

**Service**
- File: `src/Service/CampaignInitializationService.php`
- Function: `CampaignInitializationService::initializeCampaign()`

**Sub-operations (called in order):**
- `createCampaign(...)` → inserts `dc_campaigns` (campaign record + `campaign_data` JSON)
- `loadStarterRoomSeed()` → loads explicit starter tavern room seed
- `createStarterDungeon(...)` → creates starter dungeon runtime record(s)
- `loadTavernEntranceRoom(...)` → seeds tavern entrance room content
- `seedStarterQuests(...)` → initial quests
- `bootstrapChatSessions(...)` → creates hierarchical chat sessions (campaign/dungeon/room)
- `seedStarterRoomChatHistory(...)` → seeds initial room chat history for tavern

## Step 5 — Render Tavern Entrance character selection (GET)

**Route**
- `dungeoncrawler_content.campaign_tavernentrance` → `GET /campaigns/{campaign_id}/tavernentrance`
  - File: `dungeoncrawler_content.routing.yml`

**Controller**
- File: `src/Controller/CampaignController.php`
- Function: `CampaignController::tavernEntrance(int $campaign_id)`
  - Loads campaign row and enforces ownership
  - Loads user characters via `CharacterManager::getUserCharacters()`
  - Builds per-character URLs:
    - Select (completed character): `dungeoncrawler_content.campaign_select_character`
    - Continue (incomplete character): `dungeoncrawler_content.character_setup`
  - Returns theme `campaign_tavernentrance`

---

## Step 6 — Select a character for the campaign (GET)

**Route**
- `dungeoncrawler_content.campaign_select_character` → `GET /campaigns/{campaign_id}/select-character/{character_id}`
  - File: `dungeoncrawler_content.routing.yml`

**Controller**
- File: `src/Controller/CampaignController.php`
- Function: `CampaignController::selectCharacter(int $campaign_id, int $character_id)`

**Key writes / side-effects (in order):**
- Upsert `dc_campaign_characters` (runtime campaign character row)
  - sets `instance_id = pc-{campaign_id}-{canonical_character_id}`
  - persists location fields (`last_room_id`, `position_q`, `position_r`, etc.)
- Update `dc_campaigns.active_character_id = $canonical_character_id`
- Sync institution memberships
  - `InstitutionMembershipService::syncCampaignCharacterMemberships(...)`
- Start default starter quest
  - `CampaignController::startStarterQuest(...)` → `QuestTrackerService::startQuest(...)`
- Ensure starter dungeon exists
  - `CampaignController::ensureDefaultTavernDungeonExists(...)`
- Resolve launch query + redirect to hexmap
  - `CampaignController::buildHexmapLaunchQuery(...)`
  - `CampaignController::loadLatestCampaignDungeon(...)`
  - Redirect: `dungeoncrawler_content.hexmap_demo` → `GET /hexmap?campaign_id=...&character_id=...&map_id=...&dungeon_level_id=...&room_id=...`

## Step 7 — Render Hexmap v2 shell + bootstrap state (GET)

**Route**
- `dungeoncrawler_content.hexmap_demo` → `GET /hexmap`
  - File: `dungeoncrawler_content.routing.yml`

**Controller**
- File: `src/Controller/HexMapController.php`
- Function: `HexMapController::demo()`

**Sub-calls (critical):**
- `HexMapController::buildLaunchContextFromRequest()`
  - `HexMapController::hydrateLaunchContextFromCampaignCharacter(...)`
  - `HexMapController::ensureLaunchRuntimeCharacter(...)`
- `HexMapController::buildHexmapStateBundle(...)`
  - `HexMapController::loadDungeonPayload(...)`
  - entity injection + quest/storyline summaries
- Attaches JS library + emits bootstrap payload:
  - Library: `dungeoncrawler_content/hexmap-v2` (declared in `dungeoncrawler_content.libraries.yml`)
  - `drupalSettings.dungeoncrawlerContent.{hexmapLaunchContext, hexmapDungeonData, map_visual_state, hexmapLaunchCharacter, ...}`

## Step 8 — JS behavior boot (client)

**Behavior entry**
- File: `js/hexmap-v2.js`
- Function: `Drupal.behaviors.hexMapV2.attach()`
  - Instantiates `GameShell` and calls `GameShell.init()`

## Step 9 — GameShell wires API handlers and triggers initial chat history load

**Shell**
- File: `js/v2/GameShell.js`
- Function: `GameShell::init()`
  - Calls `_initApiHandlers()`

**API wiring**
- File: `js/v2/GameShell.js`
- Function: `GameShell::_initApiHandlers()`
  - Registers `bus.on('user:chat-submitted', ...)` → `_handleChatSubmit()`
  - Registers `bus.on('user:chat-history-requested', ...)` → `_loadChatHistory()`

**Initial chat history GET**
- File: `js/v2/GameShell.js`
- Function: `GameShell::_loadChatHistory()`
  - Fetches: `GET /api/campaign/{campaign_id}/room/{room_id}/chat?character_id=...`
  - Emits: `bus.emit('chat:history-loaded', result)`

## Step 10 — Room chat API endpoints (server)

**Routes** (file: `dungeoncrawler_content.routing.yml`)
- `dungeoncrawler_content.api.room_chat_get` → `GET /api/campaign/{campaign_id}/room/{room_id}/chat`
- `dungeoncrawler_content.api.room_chat_post` → `POST /api/campaign/{campaign_id}/room/{room_id}/chat`
- `dungeoncrawler_content.api.room_chat_player_suggestion` → `POST /api/campaign/{campaign_id}/room/{room_id}/chat/player-suggestion`

**Controller**
- File: `src/Controller/RoomChatController.php`
- Functions:
  - `getChatHistory(...)` → `RoomChatService::getChatHistory(...)`
  - `postChatMessage(...)`
    - for player+room channel, routes through encounter engine:
      - `postPlayerRoomChatViaEncounterTalk(...)` → `GameCoordinatorService::processAction(...)`

## Step 11 — First player chat submit (client → server → encounter engine)

**Client event → handler**
- File: `js/v2/GameShell.js`
- `bus.on('user:chat-submitted', ...)` → `GameShell::_handleChatSubmit({message, channel})`

**HTTP POST**
- Endpoint: `POST /api/campaign/{campaign_id}/room/{room_id}/chat`
- Payload includes: `{ speaker, message, type: 'player', character_id, channel, stream }`
  - Current client path sets `stream: false` (single JSON response)

**Server routing**
- File: `src/Controller/RoomChatController.php`
- Function: `RoomChatController::postChatMessage(...)`
  - Validates `channel==='room' && type==='player'`
  - Non-stream path:
    - `postPlayerRoomChatViaEncounterTalk(...)` → `GameCoordinatorService::processAction($campaign_id, $intent)` where `$intent['type']='talk'`
  - Stream path (if enabled): `RoomChatController::streamChatMessage(...)` (NDJSON)

**Client render contract**
- File: `js/v2/GameShell.js`
- Renders ONLY server-returned chat objects:
  - primary: `result.data.message`
  - additional: `result.data.messages[]`
  - (no optimistic/local echo)
