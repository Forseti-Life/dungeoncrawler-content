# Hex Map Architecture — DungeonCrawler.life

**Version**: 1.0.0  
**Last Updated**: 2026-02-17  
**Status**: Active Development

## Table of Contents

1. [Overview](#overview)
2. [Core Design Principles](#core-design-principles)
3. [Coordinate System](#coordinate-system)
4. [Schema Hierarchy](#schema-hierarchy)
5. [Schema Files Reference](#schema-files-reference)
6. [Entity Lifecycle & Runtime Model](#entity-lifecycle--runtime-model)
7. [Data Flow](#data-flow)
8. [Persistence Model](#persistence-model)
9. [PF2e Compatibility Reference](#pf2e-compatibility-reference)
10. [File Structure](#file-structure)
11. [Implementation Roadmap](#implementation-roadmap)
12. [Related Documentation](#related-documentation)

## Overview

A procedurally-generated, AI-driven hex dungeon crawl system built on PF2e open rules. Rooms are generated on first entry and persist permanently. Creatures have AI personalities that drive social and combat interactions. The entire system is defined by JSON schemas, stored as data files, and rendered through Drupal's content module.

**Key Technologies:**
- **Pathfinder 2E (ORC/OGL)**: Rules system for stats, combat, and encounters
- **JSON Schema Draft 07**: Data validation and documentation
- **Drupal 11**: Backend and database layer
- **Axial Hex Coordinates**: Flat-top hex grid for dungeon layout

## Core Design Principles

1. **Generate Once, Persist Forever** — When a party enters an unexplored room, the AI generates it. That room never changes (except through player interaction).
2. **PF2e-Compatible** — All stats, DCs, damage, conditions, and encounter budgets follow Pathfinder 2nd Edition rules (ORC/OGL licensed).
3. **Hex Grid** — Flat-top hexagonal grid, axial coordinates `(q, r)`, 5ft per hex (= 1 PF2e square).
4. **AI-Driven NPCs** — Creatures aren't just stat blocks. They have personalities, goals, fears, memories, and social options.
5. **Fog of War** — Players only see hexes they've explored. The map reveals as they move.

## Coordinate System

```
Flat-Top Hex Grid — Axial Coordinates (q, r)

        ___
       /   \
  ___/  0,0 \___
 /   \      /   \
/ -1,0\___/ 1,0  \
\      /   \     /
 \___/ 0,1  \___/
 /   \      /   \
/-1,1 \___/ 1,1  \
\      /   \     /
 \___/      \___/
```

- **q** = column (increases east)
- **r** = row (increases southeast)
- **Hex size** = 5ft (1 PF2e square equivalent)
- **Distance** = `max(|Δq|, |Δr|, |Δq + Δr|) × 5` feet
- **Neighbors** (flat-top): `(+1,0) (-1,0) (0,+1) (0,-1) (+1,-1) (-1,+1)`

## Schema Hierarchy

```
Campaign State (Runtime)
├── campaign.schema.json           ← Campaign state: progress, active_hex, ownership
├── party.schema.json              ← Adventuring party: members, fog_of_war, resources
└── dc_campaign_characters         ← Runtime entity instances table
    └── entity_instance.schema.json ← Placed entities: creatures, items, obstacles

Dungeon Level (Content Definition)
dungeon_level.schema.json          ← Top-level: one floor of the dungeon
├── hexmap.schema.json             ← The hex grid, terrain, regions
├── room.schema.json[]             ← Rooms spanning multiple hexes
│   └── entities[]                 → entity_instance references
├── creature.schema.json           ← PF2e stat blocks + AI personality (library)
├── item.schema.json               ← Weapons, armor, consumables (library)
├── obstacle.schema.json           ← Physical obstacles (library)
├── trap.schema.json               ← PF2e traps: simple/complex (library)
├── hazard.schema.json             ← Environmental hazards (library)
├── encounter.schema.json          ← Active combat encounters (runtime state)
└── stairways[]                    ← Connections to other levels
```

**Key Concepts:**
- **Library Schemas** (creature, item, trap, hazard): Template definitions reused across multiple dungeons
- **Runtime Schemas** (campaign, party, entity_instance, encounter): Mutable game state
- **Content Schemas** (dungeon_level, hexmap, room): Immutable dungeon structure (generated once)

## Schema Files Reference

All schemas are located in `config/schemas/` and follow JSON Schema Draft 07 specification.

### Core Runtime Schemas

| Schema | Purpose | Versioned | Size | Primary Use |
|--------|---------|-----------|------|-------------|
| **campaign.schema.json** | Campaign state & progress tracking | ✓ v1.0.0 | 71 lines | `dc_campaigns.campaign_data` JSON column |
| **party.schema.json** | Adventuring party with shared resources | ✓ v1.0.0 | 220 lines | Party management, fog of war tracking |
| **entity_instance.schema.json** | Runtime entity placement & state | ✗ | 264 lines | `dc_campaign_characters` table, `dungeon_level.entities[]` array |
| **encounter.schema.json** | Combat & social encounters | ✓ v1.0.0 | 355 lines | Combat engine, initiative tracking |

### Dungeon Content Schemas

| Schema | Purpose | Versioned | Size | Primary Use |
|--------|---------|-----------|------|-------------|
| **dungeon_level.schema.json** | Complete dungeon floor | ✓ v1.0.0 | 299 lines | Level generation, orchestrates all components |
| **hexmap.schema.json** | Hex-based dungeon map | ✓ v1.0.0 | 247 lines | Axial coordinate grid, fog of war, terrain |
| **room.schema.json** | Individual dungeon rooms | ✗ | 372 lines | Room generation, AI descriptions |

### Entity Library Schemas

| Schema | Purpose | Versioned | Size | Primary Use |
|--------|---------|-----------|------|-------------|
| **creature.schema.json** | Monsters, NPCs, beasts | ✓ v1.0.0 | 994 lines | Full PF2e stat blocks + AI personality |
| **item.schema.json** | Equipment & loot | ✓ v1.0.0 | 439 lines | Weapons, armor, magic items, consumables |
| **trap.schema.json** | Mechanical & magical traps | ✓ v1.0.0 | 77 lines | Hidden dangers, simple/complex traps |
| **hazard.schema.json** | Environmental hazards | ✗ | 206 lines | Ongoing dangers (fire, poison gas, etc.) |
| **obstacle.schema.json** | Map obstacles | ✗ | 78 lines | Physical blockers (pillars, chasms, etc.) |
| **obstacle_object_catalog.schema.json** | Reusable obstacle definitions | ✗ | 87 lines | Obstacle templates library |

### Character Creation Schemas

| Schema | Purpose | Versioned | Size | Primary Use |
|--------|---------|-----------|------|-------------|
| **character.schema.json** | Complete PF2e character | ✓ v1.0.0 | 540 lines | `dc_characters.character_data` JSON column |
| **character_options_step[1-8].json** | Character creation wizard | ✗ | 232-475 lines | UI options & validation per step |

### Key Schema Features

**Versioned Schemas** (marked ✓): Include `schema_version` field for migration compatibility and breaking change tracking.

**Campaign Schema** defines:
- Campaign ownership (`created_by` user ID)
- Progress tracking (quest events, discoveries)
- Current location (`active_hex` in axial coordinates like "q0r0")
- Metadata storage for custom extensions

**Party Schema** defines:
- Party members with PF2e conditions, spell slots, hero points
- Shared inventory and currency (cp/sp/gp/pp)
- Fog of war: revealed hexes, rooms, connections with player notes
- Exploration state: mode, lighting, movement speed
- Encounter history log

**Entity Instance Schema** is the canonical runtime model for all placed entities:
- Unified interface for creatures, items, obstacles, traps
- References library schemas via `entity_ref` field
- Tracks runtime state: `active`, `destroyed`, `hidden`, `collected`, `hit_points`
- Hex placement using axial coordinates
- Used in `dungeon_level.entities[]` and `dc_campaign_characters` table

**Room Schema** serves as content template:
- AI-generated descriptions and narrative text
- Multi-hex occupation with terrain overrides
- Lighting conditions (bright/dim/darkness/magical)
- Room state tracking (explored, active, cleared)
- Contains entity placements that merge with runtime instances

**For detailed schema documentation**, see [`config/schemas/README.md`](config/schemas/README.md).

## Entity Lifecycle & Runtime Model

### Entity Types

The system distinguishes between **library definitions** (templates) and **runtime instances** (placed entities):

**Library Schemas** (Templates):
- `creature.schema.json`: Monster/NPC stat blocks
- `item.schema.json`: Equipment definitions
- `obstacle.schema.json`: Obstacle types
- `trap.schema.json`: Trap mechanics
- `hazard.schema.json`: Hazard definitions

**Runtime Model**: `entity_instance.schema.json`
- Unified schema for all placed entities
- References library via `entity_ref` field
- Tracks mutable runtime state
- Stored in `dungeon_level.entities[]` or `dc_campaign_characters` table

### Entity Instance Structure

```json
{
  "instance_id": "goblin-scout-1",
  "entity_type": "creature",
  "entity_ref": "goblin-warrior",
  "entity_version": "1.0.0",
  "placement": {
    "hex": {"q": 0, "r": 0},
    "elevation": 0
  },
  "state": {
    "active": true,
    "destroyed": false,
    "hidden": true,
    "hit_points": 8,
    "detected": false,
    "metadata": {}
  }
}
```

### Runtime State Properties

| Property | Type | Description |
|----------|------|-------------|
| `active` | boolean | Currently active in game world |
| `destroyed` | boolean | Permanently destroyed (killed, consumed, demolished) |
| `disabled` | boolean | Temporarily disabled (disarmed trap, deactivated hazard) |
| `hidden` | boolean | Hidden from view (stealthy creature, concealed trap) |
| `detected` | boolean | Has been detected by party (affects visibility) |
| `collected` | boolean | Item collected by party |
| `hit_points` | integer | Current HP (for creatures) |
| `inventory` | array | Carried items (for creatures) |
| `metadata` | object | Entity-specific extensible data |

### Entity Lifecycle API

See [`API_DOCUMENTATION.md`](API_DOCUMENTATION.md) for complete API reference.

**Spawn Entity**: `POST /api/campaign/{campaignId}/entity/spawn`
```json
{
  "type": "npc",
  "instanceId": "goblin-scout-1",
  "characterId": 456,
  "locationType": "room",
  "locationRef": "room-3",
  "stateData": {
    "hexId": "hex-5",
    "hp": 8,
    "detected": false,
    "hidden": true
  }
}
```

**Move Entity**: `POST /api/campaign/{campaignId}/entity/{instanceId}/move`

**Despawn Entity**: `DELETE /api/campaign/{campaignId}/entity/{instanceId}`

**List Entities**: `GET /api/campaign/{campaignId}/entities?locationType=room&locationRef=room-3`

### Visibility & Detection Rules

**Fog of War (Hex-Level)**:
- Room state contains `visibleHexIds` array
- Only hexes in this array are returned in API responses
- Entities in non-visible hexes are filtered out

**Entity Detection**:
- **Traps**: Hidden by default unless `state.detected === true`
- **Hidden Entities**: Entities with `hidden: true` are hidden unless `state.detected === true`
- **Normal Entities**: Visible if in a visible hex (unless explicitly hidden)

**Detection Workflow**:
1. Party enters room → Server returns only visible hexes and detected entities
2. Party makes Perception check for hidden entities/traps
3. On success → Client updates room state to mark entities as detected
4. Server returns updated room state with newly detected entities visible

### Database Storage

**`dc_campaign_characters` Table**:
| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Primary key |
| `campaign_id` | int | Foreign key to campaign |
| `character_id` | int | Library character ID (0 for non-character entities) |
| `instance_id` | string | Unique instance identifier (campaign-scoped) |
| `type` | string | Entity type: `pc`, `npc`, `obstacle`, `trap`, `hazard` |
| `location_type` | string | Location type: `room`, `dungeon`, `tavern` |
| `location_ref` | string | Location reference (e.g., room ID) |
| `state_data` | JSON | Entity-specific runtime state |
| `created` | timestamp | Creation timestamp |
| `updated` | timestamp | Last update timestamp |



## Data Flow

### 1. Campaign Creation & Character Selection

```
User creates campaign
    ↓
POST /campaigns/create
  - Campaign record created in dc_campaigns
  - campaign_data initialized with schema v1.0.0
  - status: "draft"
    ↓
Redirect to /campaigns/{id}/tavernentrance
  - Select existing character OR create new
    ↓
POST /campaigns/{id}/select-character/{character_id}
  - Bind character to campaign
  - Create entity_instance in dc_campaign_characters
  - Set active_character_id in campaign
  - Campaign status: "ready" → "active"
    ↓
Launch into hexmap (dungeon level 1, hex q0r0)
```

### 2. Dungeon Level Generation (AI-Driven)

```
Party descends to new level
    ↓
DungeonGeneratorService generates dungeon_level:
  1. Determine theme (based on depth + randomness)
     - Depth 1-3: Goblin warrens, bandit hideouts
     - Depth 4-7: Undead crypts, kobold tunnels
     - Depth 8-12: Elemental planes, drow cities
  
  2. Create hexmap structure:
     - Grid dimensions (e.g., 50x50 hexes)
     - Terrain types (stone, dirt, water, etc.)
     - Elevation map for 3D positioning
  
  3. Generate entrance room immediately:
     - Place at origin (q0r0)
     - Create room instance with AI descriptions
     - Add basic lighting and connections
     - Mark as explored in party.fog_of_war
  
  4. Defer generation of adjacent rooms:
     - Create "unexplored" placeholder connections
     - Rooms generated when party approaches
     - Ensures infinite dungeon possibility
    ↓
Store dungeon_level to persistent storage
Update campaign.campaign_data with level reference
```

### 3. Room Generation (On First Entry)

```
Party approaches unexplored room
    ↓
DungeonGeneratorService.generateRoom():
  
  1. Room Layout:
     - Determine room size (3-15 hexes)
     - Select room shape (rectangular, circular, irregular)
     - Assign terrain types per hex
     - Set lighting (bright/dim/darkness/magical)
  
  2. Entity Placement (using entity_instance.schema):
     - Select creatures from library (creature.schema)
     - Create entity instances with:
       * instance_id: "goblin-{uuid}"
       * entity_ref: "goblin-warrior"
       * placement.hex: {q, r}
       * state.hidden: true (if stealthy)
     - Calculate XP budget (encounter.schema thresholds)
  
  3. Loot & Items:
     - Generate treasure (item.schema references)
     - Place items as entity instances
     - Set state.collected: false
  
  4. Traps & Hazards:
     - Place traps (trap.schema) with state.detected: false
     - Place hazards (hazard.schema) if environmental
  
  5. Connections:
     - Create doors/passages to adjacent rooms
     - Mark as locked/secret if appropriate
  
  6. AI Narrative:
     - Generate room description (read-aloud text)
     - Create ambient flavor text
     - Add GM notes for hidden elements
    ↓
Store room to dungeon_level
Mark room as generated (never regenerate)
```

### 4. Exploration Loop (Turn-Based)

```
Party at hex (q, r) in exploration mode
    ↓
1. Update Visibility:
   GET /api/dungeon/{id}/room/{roomId}/state
   - Server calculates visible hexes (light radius + LoS)
   - Returns room.state.visibleHexIds
   - Client renders fog of war overlay
    ↓
2. Reveal Entities:
   - Only entities in visible hexes returned
   - Hidden entities require Perception check
   - Traps remain hidden unless detected
    ↓
3. Check for Wandering Monsters:
   - Roll encounter check (1d20 vs DC)
   - If triggered: spawn wandering creature
   - POST /api/campaign/{id}/entity/spawn
    ↓
4. Player Actions (3 actions in exploration):
   - Move (1 action per hex)
   - Search (1 action, Perception check)
   - Interact (1 action, varies)
   - Detect Magic (1 action, focus spell)
    ↓
5. If entering new room:
   - Generate room (if not yet generated)
   - Update party.fog_of_war.revealed_rooms
   - POST /api/campaign/{id}/state (update active_hex)
   - Roll for traps (if not detected)
   - Roll encounter check (vs room occupants)
    ↓
6. If encounter triggered:
   → Switch to Combat Flow
```

### 5. Combat Flow (PF2e Initiative Order)

```
Encounter triggered
    ↓
1. Roll Initiative:
   - All combatants roll (Perception or Stealth)
   - Create encounter instance (encounter.schema)
   - Sort by initiative (ties: higher Perception wins)
   - POST /api/combat/start
    ↓
2. Combat Rounds (until victory/defeat/flee):
   
   Round Start:
     - Increment round counter
     - Process start-of-round effects (persistent damage, etc.)
   
   For each combatant (initiative order):
     Active Combatant Turn:
       ├─ 3 Actions available (stride/strike/cast/etc.)
       ├─ 1 Reaction available (attack of opportunity, shield block)
       ├─ Free actions (drop item, speak, etc.)
       └─ Movement budget (based on Speed)
     
     AI Decision Making (for NPCs):
       ├─ Check morale (if HP < threshold, consider fleeing)
       ├─ Evaluate threats (target selection)
       ├─ Choose tactics (from creature.combat_personality):
       │   ├─ aggression: 0-10 (0=defensive, 10=reckless)
       │   ├─ preferred_tactics: ["flank", "ranged", "spellcaster"]
       │   └─ flee_behavior: "nearest_exit" | "defend_boss"
       └─ Execute actions
     
     Action Resolution:
       ├─ Roll d20 + modifiers
       ├─ Compare to target DC/AC
       ├─ Determine degree of success:
       │   ├─ Critical Success (beat by 10+)
       │   ├─ Success (meet/beat DC)
       │   ├─ Failure (below DC)
       │   └─ Critical Failure (below by 10+)
       ├─ Apply damage/conditions
       └─ Log action to encounter.action_log
     
     POST /api/combat/action with:
       {
         "combatant_id": "...",
         "action_type": "strike",
         "target_id": "...",
         "roll": 18,
         "modifiers": ["+5 trained", "+2 str"],
         "result": "hit",
         "damage": "2d6+2 slashing"
       }
   
   Round End:
     - Process end-of-round effects
     - Update combatant HP/conditions
     - POST /api/combat/end-turn
    ↓
3. Combat End Conditions:
   
   Victory:
     - All enemies defeated/fled/surrendered
     - Award XP (encounter.xp_awarded)
     - Distribute loot
     - POST /api/combat/end
   
   Defeat:
     - All PCs unconscious
     - Track in party.encounter_log
     - Handle consequences (death/capture)
   
   Flee:
     - PCs successfully escape
     - Enemies may pursue
     - Room state: isCleared = false
   
   Social Resolution:
     - Negotiation/diplomacy successful
     - Convert combat to social encounter
     - Track in creature memory
    ↓
Return to Exploration Loop
```

### 6. State Synchronization & Persistence

```
All game state changes follow this pattern:

1. Client fetches current state:
   GET /api/campaign/{id}/state
   Response includes version: 42

2. Client makes local changes (optimistic UI)

3. Client submits update:
   POST /api/campaign/{id}/state
   Body: {
     "expectedVersion": 42,
     "state": { /* updated state */ }
   }

4. Server validates:
   - Check expectedVersion === current version
   - If mismatch: return 409 Conflict with current state
   - If match: apply update, increment version
   - Return new version: 43

5. Client handles response:
   - Success: Update local version to 43
   - Conflict: Re-fetch state, merge changes, retry

This optimistic locking prevents race conditions in multiplayer scenarios.
```

## Persistence Model

The system uses a hybrid persistence model balancing permanence with dynamic gameplay:

### Content Layer (Immutable After Generation)

```
✓ Dungeon Structure — Generated once per level, never changes
  ├── Hexmap layout — Grid, terrain, connections
  ├── Rooms — Floor plan, descriptions, connections
  └── Room templates — Base creature/item/trap placements
```

### State Layer (Mutable Runtime)

```
↻ Campaign State (dc_campaigns.campaign_data)
  ├── Campaign progress events
  ├── Active hex location
  └── Campaign metadata

↻ Party State (party.schema.json instance)
  ├── Party members: conditions, spell slots, resources
  ├── Shared inventory and currency
  ├── Fog of war: revealed hexes/rooms
  └── Exploration state

↻ Entity Instances (dc_campaign_characters table)
  ├── Runtime entity placement and state
  ├── Creature HP, conditions, inventory
  ├── Item collection status
  ├── Trap detection/disarm status
  └── Obstacle destruction status

↻ Encounter State (encounter.schema.json instances)
  ├── Active combat initiative order
  ├── Combatant HP and conditions
  ├── Action log with rolls
  └── Terrain effects
```

### Entity Lifecycle Rules

| Entity Type | Respawn | Persistence | Notes |
|-------------|---------|-------------|-------|
| **Boss Creatures** | ✗ Never | Permanent | Once defeated, never return |
| **Unique NPCs** | ✗ Never | Permanent | Death is permanent |
| **Standard Creatures** | ✓ Configurable | Room-specific | Respawn after interval if configured |
| **Wandering Monsters** | ✓ Random | Campaign-wide | Random encounters reset periodically |
| **Consumable Items** | ✗ Never | Permanent removal | Once collected, gone forever |
| **Permanent Items** | ✓ No | Permanent | Weapons, armor stay in world unless taken |
| **Traps** | ✓ Configurable | Room-specific | Can reset automatically or manually |
| **Hazards** | ~ Varies | Room-specific | Some permanent, some temporary |

### State Persistence Across Sessions

**Persists Forever**:
- Room discovery and exploration
- Fog of war reveals
- Boss/unique creature deaths
- Item collection
- Trap disarmament
- Room cleared status

**Persists Per Session**:
- Active combat encounters
- Current party position
- Spell slot usage
- Consumable resource depletion
- Temporary conditions

**Resets**:
- Wandering monster encounters (on timer)
- Respawning creature instances (if configured)
- Auto-reset traps (based on trap definition)

### Optimistic Locking

Campaign and dungeon state use optimistic locking to prevent race conditions:
- Each state update includes `expectedVersion` field
- Server rejects updates if version doesn't match current
- Client must re-fetch state and retry on `409 Conflict`
- Version numbers increment on every successful update

**Example**:
```json
{
  "expectedVersion": 42,
  "state": { /* updated state */ }
}
```

See [`API_DOCUMENTATION.md`](API_DOCUMENTATION.md) for complete versioning details.

## PF2e Compatibility Reference

### Encounter XP Budget (4 players)

| Threat Level | XP Budget | Use When |
|-------------|-----------|----------|
| Trivial | 40 | Routine, no real danger |
| Low | 60 | Some risk, resource drain |
| Moderate | 80 | Standard encounter |
| Severe | 120 | Challenging, real danger |
| Extreme | 160 | Boss fights, potential TPK |

### Creature XP by Level Difference

| Creature Level vs Party | XP Each |
|------------------------|---------|
| Party Level -4 | 10 |
| Party Level -3 | 15 |
| Party Level -2 | 20 |
| Party Level -1 | 30 |
| Party Level +0 | 40 |
| Party Level +1 | 60 |
| Party Level +2 | 80 |
| Party Level +3 | 120 |
| Party Level +4 | 160 |

### Degrees of Success

All d20 checks use the four-degree system:
- **Critical Success**: Beat DC by 10+
- **Success**: Meet or beat DC
- **Failure**: Below DC
- **Critical Failure**: Below DC by 10+

Natural 20 upgrades one degree. Natural 1 downgrades one degree.

### Hex Distance → PF2e Range

| Hexes | Feet | PF2e Equivalent |
|-------|------|-----------------|
| 1 | 5ft | Adjacent/melee reach |
| 2 | 10ft | Reach weapons |
| 3 | 15ft | Close burst |
| 6 | 30ft | Standard ranged |
| 12 | 60ft | Shortbow |
| 24 | 120ft | Longbow |

## File Structure

```
dungeoncrawler_content/
├── config/
│   ├── schemas/                         ← JSON Schema definitions (22 files)
│   │   ├── README.md                    ← Comprehensive schema documentation
│   │   ├── campaign.schema.json         ← Campaign state (v1.0.0)
│   │   ├── party.schema.json            ← Party management (v1.0.0)
│   │   ├── entity_instance.schema.json  ← Runtime entity model
│   │   ├── dungeon_level.schema.json    ← Floor orchestrator (v1.0.0)
│   │   ├── hexmap.schema.json           ← Hex grid (v1.0.0)
│   │   ├── room.schema.json             ← Room definitions
│   │   ├── creature.schema.json         ← Monster/NPC library (v1.0.0)
│   │   ├── item.schema.json             ← Equipment library (v1.0.0)
│   │   ├── trap.schema.json             ← Trap library (v1.0.0)
│   │   ├── hazard.schema.json           ← Hazard library
│   │   ├── obstacle.schema.json         ← Obstacle library
│   │   ├── encounter.schema.json        ← Combat encounters (v1.0.0)
│   │   ├── character.schema.json        ← PF2e character (v1.0.0)
│   │   └── character_options_step[1-8].json ← Character creation
│   └── examples/                        ← Example data files
│       └── (example files TBD)
├── src/
│   ├── Controller/
│   │   ├── CampaignController.php       ← Campaign management
│   │   ├── CampaignStateController.php  ← Campaign state API
│   │   ├── DungeonStateController.php   ← Dungeon state API
│   │   ├── RoomStateController.php      ← Room state API
│   │   ├── EntityLifecycleController.php ← Entity spawn/move/despawn
│   │   ├── CharacterViewController.php  ← Character display
│   │   ├── CharacterListController.php  ← Character list
│   │   └── DashboardController.php      ← Admin dashboard
│   ├── Form/
│   │   ├── CampaignCreateForm.php       ← Campaign creation
│   │   ├── CharacterCreateForm.php      ← Character creation wizard
│   │   └── CharacterDeleteForm.php      ← Character deletion
│   ├── Service/
│   │   ├── CharacterManager.php         ← Character CRUD operations
│   │   ├── SchemaLoader.php             ← Schema loading & validation
│   │   └── GameContentManager.php       ← Game content management
│   └── Access/
│       └── CampaignAccessCheck.php      ← Campaign ownership validation
├── templates/
│   ├── character-sheet.html.twig        ← Character display
│   ├── character-list.html.twig         ← Character listing
│   └── management_form_page.html.twig   ← Form wrapper
├── css/
│   ├── hexmap.css                       ← Hex grid display
│   ├── character-sheet.css              ← Character styling
│   ├── character-steps.css              ← Creation wizard styling
│   └── game-cards.css                   ← Card components
├── js/
│   ├── character-sheet.js               ← Character interactivity
│   └── game-cards.js                    ← Card interactions
├── tests/
│   ├── src/Unit/                        ← Unit tests
│   ├── src/Kernel/                      ← Kernel tests
│   └── src/Functional/                  ← Functional tests (137+ tests)
├── README.md                            ← Module documentation
├── API_DOCUMENTATION.md                 ← API reference
├── HEXMAP_ARCHITECTURE.md               ← This file
└── dungeoncrawler_content.*.yml         ← Drupal configuration
```

### Key File Locations

**Schema Documentation**: [`config/schemas/README.md`](config/schemas/README.md) - Comprehensive guide to all schemas

**API Documentation**: [`API_DOCUMENTATION.md`](API_DOCUMENTATION.md) - Complete REST API reference

**Module Documentation**: [`README.md`](README.md) - Setup, configuration, testing

**Database Tables**:
- `dc_campaigns`: Campaign records
- `dc_campaign_characters`: Runtime entity instances
- `dc_characters`: Character library



## Implementation Roadmap

### Phase 1: Data Layer ✓ COMPLETE

- [x] Define JSON schemas for all game objects (22 schemas)
- [x] Implement schema versioning for migration support (9 versioned schemas)
- [x] Create SchemaLoader service for validation
- [x] Document schemas in `config/schemas/README.md`
- [x] Document architecture in this file

### Phase 2: Runtime API ✓ COMPLETE

- [x] Campaign state API with optimistic locking
- [x] Dungeon state API
- [x] Room state API with visibility filtering
- [x] Entity lifecycle API (spawn/move/despawn)
- [x] Campaign ownership validation
- [x] API documentation in `API_DOCUMENTATION.md`

### Phase 3: Services (MOSTLY COMPLETE)

**Implemented**:
- [x] `CharacterManager` — Character CRUD operations
- [x] `SchemaLoader` — Schema loading & validation
- [x] `GameContentManager` — Basic content management
- [x] `DungeonGeneratorService` — Procedural dungeon generation with hex map, multi-level persistence, room connections
- [x] `EncounterBalancer` — PF2e XP budget encounter building, creature selection, 8-theme catalog
- [x] `DungeonCache` — In-memory + DB dungeon state caching with event-based updates
- [x] `RoomConnectionAlgorithm` — Delaunay triangulation, Kruskal MST, BFS validation, BSP generation

**Planned**:
- [ ] `HexMapService` — Hex math, pathfinding, line of sight
- [ ] `EncounterService` — Initiative, combat rounds, action resolution
- [ ] `CreatureAIService` — NPC decision-making, personality-driven behavior
- [ ] `PartyService` — Party state management, exploration tracking
- [ ] `LootService` — Treasure generation based on level/rarity tables
- [ ] `FogOfWarService` — Visibility calculations and fog of war updates

### Phase 4: Frontend (PLANNED)

- [ ] Hex grid renderer (Canvas or SVG with PixiJS)
- [ ] Fog of war overlay with reveal animations
- [ ] Combat UI with initiative tracker
- [ ] Character/creature stat panels
- [ ] Interactive room exploration UI
- [ ] Action selection and validation
- [ ] Roll results display with PF2e degree system
- [ ] Inventory management interface

### Phase 5: AI Integration (PLANNED)

- [ ] Room description generation via AI
- [ ] NPC dialogue generation
- [ ] Dynamic encounter scaling
- [ ] Creature personality-driven combat decisions
- [ ] Procedural loot naming and lore generation
- [ ] Theme-aware dungeon generation

### Current Focus

**Active Development**: Phase 3 - Services layer implementation (dungeon/room generation complete, encounter balancing complete)

**Next Priority**: HexMapService for coordinate math and pathfinding; FogOfWarService for visibility

**Testing**: 137+ tests covering routes, controllers, and workflows

## Related Documentation

- **[`README.md`](README.md)** - Module overview, installation, configuration
- **[`API_DOCUMENTATION.md`](API_DOCUMENTATION.md)** - Complete REST API reference
- **[`config/schemas/README.md`](config/schemas/README.md)** - Detailed schema documentation
- **Pathfinder 2E Rules**: [Archives of Nethys](https://2e.aonprd.com/)
- **JSON Schema Specification**: [json-schema.org](https://json-schema.org/)
