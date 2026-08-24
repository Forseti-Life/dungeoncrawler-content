# Hexmap UI API Framework

## Purpose

This document defines the API framework for **querying** and **posting** between the Hexmap V2 UI and the server runtime.

The goal is simple:

- every UI surface knows **which API owns its reads**
- every UI action knows **which API owns its writes**
- every mutation has a defined **post-mutation refresh path**

If a panel cannot answer those three questions, its state ownership is incomplete.

## 1. Core framework

### 1.1 Query framework

Every query must declare:

1. **authority family** — which endpoint family owns the data
2. **object scope** — runtime, character, inventory, room, merchant, quest, etc.
3. **consumer surface** — which panel/element renders it
4. **refresh trigger** — when it is re-queried or invalidated

### 1.2 Post/mutation framework

Every mutation must declare:

1. **user intent** — what the player clicked/changed
2. **authority family** — which endpoint owns the mutation
3. **payload contract** — minimum fields required
4. **authoritative result path** — direct response, event stream, or explicit re-query
5. **failure behavior** — what the UI does on rejection

## 2. Authority families

## 2A. Authority families mapped to data objects

| Authority family | Primary data objects | Typical object keys / shapes | Main UI consumers |
|---|---|---|---|
| Bootstrap / world projection | `launch_context`, `dungeon_payload`, `map_visual_state`, `launch_character`, `quest_summary`, `campaign_access` | `{ launch_context, dungeon_payload, map_visual_state, launch_character, quest_summary, campaign_access }` | `GameShell`, map canvas, Action Rail bootstrap, Room View bootstrap, Chat bootstrap |
| Gameplay runtime authority | phase snapshot, turn envelope, action availability set, action contract, event stream | `{ phase, turn, available_actions/availableActions, action_contract/actionContract, campaign_clock/gameTime, timed_activities, snapshot_id/stateVersion }`; `{ events: [...] }` | `GameCoordinator`, `ActionRailPanel`, `CombatPanel`, `ChatPanel` |
| Character authority | character state object, character action payloads, spell-cast payloads | character identity + stats + resources + conditions + spells + skills + features + actions; action/spell result payloads | `CharacterPanel`, `ActionRailPanel`, `GameShell` |
| Inventory authority | inventory object, equipment/location object, currency/capacity object | `{ inventory, equipment, currency, capacity, items }`; location mutation payload/result | `InventoryPanel`, `MerchantPanel`, Action Rail consumables/rest views |
| Room/chat authority | room transcript, channel map, session transcript, room-entry acknowledgement object | `{ data: { messages, roomId, channel } }`; `{ data: { channels } }`; `{ acknowledged }` | `ChatPanel`, `GameShellFetchBridge` |
| Room/merchant/navigation authority | room view object, merchant context, merchant catalog search result, merchant transaction result, entity placement object, navigation resolution object | `{ room, entries, status, available }`; `{ context }`; `{ items }`; `{ success, message, context }`; `{ locationType, locationRef, stateData.placement }`; `{ data: { navigation } }` | `RoomViewPanel`, `MerchantPanel`, `GameShell`, `NavigationSystem` |
| Quest/relationship/settings authority | quest journal, relationships matrix, campaign settings payload | `{ active, offers, leads, completed }`; relationship matrix payload; `{ settings, members, campaign_name, mode }` | `QuestPanel`, `CharacterPanel`, settings coordinator |

### A. Bootstrap / world projection

**Reads**

- `GET /api/map/visual-state`

**Owns**

- launch context
- map visual state
- launch character bootstrap
- quest summary bootstrap
- room topology/bootstrap room context

### B. Gameplay runtime authority

**Reads**

- `GET /api/game/{campaign_id}/state`
- `GET /api/game/{campaign_id}/events`

**Writes**

- `POST /api/game/{campaign_id}/action`

**Owns**

- current phase
- active turn
- action legality
- action contract
- canonical gameplay mutation results
- encounter/system event stream

### C. Character authority

**Reads**

- `GET /api/character/{character_id}/state`
- `GET /api/character/{character_id}/actions`

**Writes**

- `POST /api/character/{character_id}/actions`
- `POST /api/character/{character_id}/cast-spell`
- `POST /api/character/{character_id}/convert-library`

**Owns**

- character sheet state
- character resources
- out-of-band character action posts
- character-scoped utility mutations

### D. Inventory authority

**Reads**

- `GET /api/inventory/{owner_type}/{owner_id}`

**Writes**

- `POST /api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}/location`

**Owns**

- inventory items
- equipment location
- currency/bulk state

### E. Room/chat authority

**Reads**

- `GET /api/campaign/{campaign_id}/room/{room_id}/chat`
- `GET /api/campaign/{campaign_id}/room/{room_id}/channels...`
- session chat reads via `ChatSessionApi`

**Writes**

- `POST /api/campaign/{campaign_id}/room/{room_id}/chat`
- `POST /api/campaign/{campaign_id}/room/{room_id}/chat/entry-acknowledgement`
- `POST /api/campaign/{campaign_id}/room/{room_id}/channels`
- `DELETE /api/campaign/{campaign_id}/room/{room_id}/channels/{channel_key}`

**Owns**

- room transcript
- private/ability channels
- room-entry acknowledgement
- sessionized chat views

### F. Room/merchant/navigation authority

**Reads**

- `GET /api/campaign/{campaign_id}/room/{room_id}/view-image`
- `GET /api/campaign/{campaign_id}/room/{room_id}/merchant/{merchant_ref}`
- `GET /api/campaign/{campaign_id}/room/{room_id}/merchant/{merchant_ref}/search`
- `GET /api/campaign/{campaign_id}/visited-locations`

**Writes**

- `POST /api/campaign/{campaign_id}/room/{room_id}/merchant/{merchant_ref}/transaction`
- `POST /api/campaign/{campaign_id}/entity/{instance_id}/move`
- `POST /api/campaign/{campaign_id}/navigation/locations/request`

**Owns**

- room scene images
- merchant catalog + trading
- entity room placement
- navigation destination resolution

### G. Quest/relationship/settings authority

**Reads**

- `GET /api/campaign/{campaign_id}/character/{character_id}/quest-journal`
- `GET /api/campaign/{campaign_id}/quest-journal`
- `GET /api/campaign/{campaign_id}/relationships/matrix`
- `GET /api/campaign/{campaign_id}/settings`

**Writes**

- `POST /api/campaign/{campaign_id}/settings/mode`
- `POST /api/campaign/{campaign_id}/settings/members/{member_uid}`

**Owns**

- quest journal
- relationship matrix
- campaign settings/membership mode

## 3. Read rules

### Rule 1: one panel, one read owner per concern

Examples:

- **Action legality** comes from gameplay runtime authority, not CharacterPanel or local shell caches.
- **Inventory placement** comes from inventory authority, not merchant response side data.
- **Room transcript** comes from room/chat authority, not encounter event reconstruction.

### Rule 2: bootstrap is not permanent authority

`/api/map/visual-state` is the launch projection, not the long-lived owner of turn legality or evolving encounter state.

After bootstrap:

- gameplay state moves to `/api/game/{campaign_id}/state`
- character state moves to `/api/character/{character_id}/state`
- inventory state moves to `/api/inventory/...`

### Rule 3: local UI state is presentation-only

Allowed local state:

- selected tab
- expanded section
- filter text
- pending spinner
- current button lock

Not allowed as authority:

- canonical actor identity
- current turn owner
- legal action set
- authoritative HP/conditions/resources

## 4. Post rules

### Rule 4: every UI mutation posts to one authoritative family

Examples:

- Action Rail combat action → gameplay runtime authority
- Equip/unequip → inventory authority
- Chat send → room/chat authority
- Merchant buy/sell → merchant authority

### Rule 5: every mutation defines its refresh path

Allowed refresh patterns:

1. **authoritative mutation response already contains updated state**
2. **authoritative mutation response emits events that update projections**
3. **UI performs an explicit follow-up re-query**

Every mutation must pick one.

### Rule 6: no silent split authority

If a button posts to one API but refreshes from a different ad-hoc local source, the contract is broken.

## 5. Current UI query/write matrix

| UI surface | Read authority | Write authority | Post-mutation refresh |
|---|---|---|---|
| Action Rail | `/api/map/visual-state`, `/api/game/{campaign_id}/state`, `/api/character/{character_id}/actions` | `/api/game/{campaign_id}/action`, `/api/character/{character_id}/actions`, `/api/character/{character_id}/cast-spell` | authoritative action response and/or `loadCharacterFromApi()` |
| Combat Panel | `/api/game/{campaign_id}/state`, `/api/game/{campaign_id}/events` | none directly; reacts to gameplay posts | authoritative update + event stream |
| Character Panel | `/api/character/{character_id}/state`, `/relationships/matrix` | `/api/character/{character_id}/convert-library` | `loadCharacterFromApi()` |
| Inventory Panel | `/api/inventory/{owner_type}/{owner_id}` | `/api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}/location` | inventory mutation response |
| Chat Panel | room chat/channel/session APIs, `/api/game/{campaign_id}/events` | room chat POST, channel POST/DELETE | transcript response and event stream |
| Room View Panel | `/api/campaign/{campaign_id}/room/{room_id}/view-image` | none | explicit refresh intent |
| Merchant Panel | merchant context/search APIs | merchant transaction POST | transaction response + inventory sync |
| Navigation/map movement | `/api/map/visual-state`, `/api/game/{campaign_id}/state`, visited locations | entity move POST, navigation location request POST | coordinator resync and/or runtime bundle reload |
| Quest Panel | quest journal APIs | quest lifecycle routes outside this panel family | explicit journal refresh |
| Settings Panel | campaign settings APIs | settings mode/member POSTs | reload settings payload |

## 6. Current UI-originated mutation matrix

| UI trigger | Client surface | Endpoint | Server owner |
|---|---|---|---|
| click `data-action-rail-execute` | `ActionRailPanel` / `EncounterSystem` | `POST /api/game/{campaign_id}/action` | `GameCoordinatorController` |
| click `data-action-rail-execute` (non-coordinator action lane) | `ActionRailPanel` / `EncounterSystem` | `POST /api/character/{character_id}/actions` | character action controller/service lane |
| click spell action | `ActionRailPanel` / `EncounterSystem` | `POST /api/character/{character_id}/cast-spell` | spell/character action lane |
| submit `#chat-form` / click `#chat-send` | `ChatPanel` | `POST /api/campaign/{campaign_id}/room/{room_id}/chat` | `RoomChatController` |
| open private/spell channel | `ChatPanel` | `POST /api/campaign/{campaign_id}/room/{room_id}/channels` | `RoomChatController` |
| close private/spell channel | `ChatPanel` | `DELETE /api/campaign/{campaign_id}/room/{room_id}/channels/{channel_key}` | `RoomChatController` |
| room transition acknowledgement | `GameShell` | `POST /api/campaign/{campaign_id}/room/{room_id}/chat/entry-acknowledgement` | `RoomChatController` |
| click inventory assign/unequip | `InventoryPanel` | `POST /api/inventory/{owner_type}/{owner_id}/item/{item_instance_id}/location` | `InventoryManagementController` |
| click merchant buy/sell | `MerchantPanel` | `POST /api/campaign/{campaign_id}/room/{room_id}/merchant/{merchant_ref}/transaction` | `MerchantApiController` |
| drag/drop token or persist room placement | `GameShell` | `POST /api/campaign/{campaign_id}/entity/{instance_id}/move` | `CampaignEntityController` |
| request in-session location | `NavigationSystem` / room generation coordinator | `POST /api/campaign/{campaign_id}/navigation/locations/request` | navigation controller/service lane |
| change campaign mode | settings coordinator | `POST /api/campaign/{campaign_id}/settings/mode` | `CampaignSettingsController` |
| change campaign member role | settings coordinator | `POST /api/campaign/{campaign_id}/settings/members/{member_uid}` | `CampaignSettingsController` |
| save current character to library | `CharacterPanel` | `POST /api/character/{character_id}/convert-library` | character conversion controller/service lane |

## 6A. Mutation payload objects

| Mutation family | Request object | Response / follow-up object |
|---|---|---|
| Gameplay action | action intent object: `{ type, actor, target?, params }` | authoritative gameplay result object + events + refreshed runtime snapshot |
| Character action | character action post object: `{ actionType, actionName, payload, campaignId, instanceId }` | action result object, then character refresh when needed |
| Spell cast | spell cast object: `{ spellId, level, isFocusSpell, campaignId, instanceId }` | spell result object, then character refresh |
| Chat send | room chat post object: `{ speaker, message, type, character_id, channel, stream }` | transcript lines / GM response / room chat data |
| Channel open/close | channel command object: `{ channel_key, opened_by, target_entity, target_name, source_ability }` | updated channel object / channel list |
| Entry acknowledgement | room-entry object: `{ character_id, map_id, transition_id }` | acknowledgement object `{ acknowledged }` |
| Inventory move | inventory location object: `{ location, equippedSlotKey?, equippedSlotIndex? }` | updated inventory object |
| Merchant transaction | merchant trade object: `{ action, character_id, quantity, item_id? / item_instance_id? }` | merchant transaction result + updated merchant context |
| Entity move | entity placement object: `{ locationType, locationRef, stateData: { placement } }` | placement success, then coordinator resync |
| Navigation request | destination request object: `{ destination, origin_room_id, character_id, map_id, dungeon_level_id }` | navigation resolution object |
| Settings update | settings mutation object: `{ mode }` or `{ role, status }` | settings success + settings reload |
| Character conversion | conversion object: `{ campaignId }` | conversion result object with runtime/library ids |

## 7. Enforcement rule for future work

When adding any new Hexmap UI element:

1. name the **read authority**
2. name the **write authority**
3. define the **refresh path**
4. forbid panel-local fallback authority for that concern

That is the required framework for both querying and posting in the system.

## 8. Current implementation confirmation

This framework is **already mostly present** in the codebase. The analysis confirms these authoritative lanes are implemented today:

- **bootstrap query lane** — `HexMapController` serves `GET /api/map/visual-state`, and `GameShell.loadRuntimeStateBundle()` consumes the same contract shape
- **gameplay runtime lane** — `GameCoordinatorController` serves `GET /api/game/{campaign_id}/state`, `GET /api/game/{campaign_id}/events`, and `POST /api/game/{campaign_id}/action`
- **character lane** — `CharacterStateController` and character action routes back `CharacterPanel` and Action Rail character-scoped posts
- **inventory lane** — `InventoryManagementController` owns inventory reads and location mutations
- **room/chat lane** — `RoomChatController` owns transcript posts, channel open/close, and room-entry acknowledgement
- **merchant lane** — `MerchantApiController` owns merchant context, search, and transaction posts
- **settings lane** — `CampaignSettingsController` owns settings reads and writes

### Confirmed client posting/querying split

The client already separates most reads and writes correctly:

- `ChatPanel` posts chat and channel mutations to room/chat endpoints
- `InventoryPanel` posts equipment/location mutations to inventory endpoints
- `MerchantPanel` posts buy/sell to merchant transaction endpoints
- `GameShell` posts room placement to entity move endpoints
- `EncounterSystem` posts combat/runtime actions to coordinator or character-action endpoints

### Confirmed gaps

What is **not** fully enforced everywhere is the final ownership rule inside the UI:

- some panels still keep local cached/projection state
- some UI paths can prefer local shell state before API-owned identity/state
- Action Rail was the clearest example of that violation

So the system is **in place**, but **enforcement is uneven at a few client seams**. The work here is to make the UI obey the existing server-side authority model consistently.
