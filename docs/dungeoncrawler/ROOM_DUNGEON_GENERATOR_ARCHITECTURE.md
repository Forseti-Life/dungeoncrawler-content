# Room & Dungeon Generator Architecture

**Version**: 1.0.0  
**Last Updated**: 2026-02-18  
**Status**: Design Phase

## Table of Contents

1. [Overview](#overview)
2. [Core Architecture](#core-architecture)
3. [Service Layer Design](#service-layer-design)
4. [Controller Layer Design](#controller-layer-design)
5. [Data Structures & Schemas](#data-structures--schemas)
6. [Database Tables](#database-tables)
7. [API Endpoints](#api-endpoints)
8. [Generation Workflow](#generation-workflow)
9. [Composition Patterns](#composition-patterns)
10. [Error Handling](#error-handling)
11. [Testing Strategy](#testing-strategy)
12. [Implementation Roadmap](#implementation-roadmap)

---

## Overview

The Room & Dungeon Generator system procedurally creates game content that is:
- **Generated Once, Persisted Forever** — When a party enters a new room/dungeon, the system generates it. That content never changes except through direct player action.
- **AI-Driven** — Creatures have personalities, lore, and goals; environments tell stories.
- **PF2e Compatible** — All encounters, hazards, and NPCs follow Pathfinder 2nd Edition rules.
- **Hex-Based** — Uses flat-top hex coordinates (axial q, r) with 5ft per hex.

### Key Components

1. **DungeonGenerator** — Orchestrates multi-level dungeon creation
2. **RoomGenerator** — Generates individual rooms with terrain, entities, and encounters
3. **RoomConnectionAlgorithm** — Links rooms using Modified Delaunay Triangulation
4. **EntityPlacer** — Positions creatures, items, traps, hazards on hex grid
5. **EncounterGenerator** — Creates balanced PF2e encounters within rooms
6. **Controllers** — Expose generators via REST API

---

## Core Architecture

### Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│               REST API Controllers                          │
│  ┌──────────────┐         ┌──────────────┐                 │
│  │DungeonGenCmd │         │RoomGenCmd    │                 │
│  │ - POST /...  │         │ - POST /...  │                 │
│  └──────────────┘         └──────────────┘                 │
└────────┬────────────────────────┬──────────────────────────┘
         │                        │
┌────────▼────────────────────────▼──────────────────────────┐
│           Service Layer (Generation Logic)                  │
│  ┌──────────────┐ ┌───────────────┐ ┌──────────────────┐   │
│  │DungeonGenSvc │ │RoomGenSvc     │ │RoomConnectionAlg │   │
│  │ - Orchestrate│ │ - Hex Terrain │ │ - Delaunay MST   │   │
│  │ - Validate   │ │ - Room Layout │ │ - Prune Edges    │   │
│  │ - Persist    │ │ - Description │ │                  │   │
│  └──────────────┘ └───────────────┘ └──────────────────┘   │
│  ┌──────────────────┐ ┌─────────────────────────────────┐  │
│  │EntityPlacerSvc   │ │EncounterGeneratorSvc            │  │
│  │ - Hex Placement  │ │ - XP Budgeting                  │  │
│  │ - Avoid Collisions│ │ - Threat Level Calculation      │  │
│  │ - Line of Sight  │ │ - Creature Selection & Scaling  │  │
│  └──────────────────┘ └─────────────────────────────────┘  │
└────────┬────────────────────────┬──────────────────────────┘
         │                        │
┌────────▼────────────────────────▼──────────────────────────┐
│          Data Access Layer (Repositories)                  │
│  ┌──────────────────┐      ┌──────────────────────────┐   │
│  │DungeonRepository │      │RoomRepository            │   │
│  │ - Load/Save      │      │ - Load/Save              │   │
│  │ - Query Campaign │      │ - Query Level            │   │
│  └──────────────────┘      └──────────────────────────┘   │
│  ┌──────────────────┐      ┌──────────────────────────┐   │
│  │CreatureRegistry  │      │ItemRegistry              │   │
│  │ - Load Templates │      │ - Load Templates         │   │
│  │ - Apply Scaling  │      │                          │   │
│  └──────────────────┘      └──────────────────────────┘   │
└────────┬───────────────────────────────────────────────────┘
         │
┌────────▼───────────────────────────────────────────────────┐
│          Database Layer (Tables & JSON)                    │
│  ┌──────────────────┐ ┌──────────────────────────────┐   │
│  │dc_campaign_dungeons │ dc_campaign_rooms             │   │
│  │ - dungeon_data (JSON)│ - layout_data (JSON)        │   │
│  │ - theme, depth      │ - entities (JSON array)      │   │
│  │ - location_x/y      │ - terrain, lighting, etc     │   │
│  └──────────────────┘ └──────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────┐   │
│  │dc_campaign_characters (entity placement)         │   │
│  │ - campaign_id, room_id                           │   │
│  │ - position_q, position_r (hex coordinates)       │   │
│  │ - entity_type (creature/item/obstacle)           │   │
│  └──────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────┘
```

### Layer Responsibilities

| Layer | Responsibility | Examples |
|-------|-----------------|----------|
| **Controller** | HTTP request/response | Validate input, serialize JSON, set headers |
| **Service** | Business logic | Generation algorithms, validation, orchestration |
| **Repository** | Persistence | Load/save from DB, query, caching |
| **Schema** | Data validation | JSON Schema validation, type safety |

---

## Service Layer Design

### 1. RoomGeneratorService

**Purpose**: Generate individual rooms with terrain, layout, and initial entity placement.

```php
namespace Drupal\dungeoncrawler_content\Service;

class RoomGeneratorService {

  public function __construct(
    Connection $database,
    SchemaLoader $schemaLoader,
    EntityPlacerService $entityPlacer,
    EncounterGeneratorService $encounterGenerator
  ) { }

  /**
   * Generate a single room.
   *
   * Workflow:
   * 1. Validate input parameters
   * 2. Generate hexes (terrain, elevation, obstacles)
   * 3. Generate description (AI-driven narrative)
   * 4. Place creatures/items/hazards
   * 5. Validate against room.schema.json
   * 6. Persist to database
   *
   * @param array $context
   *   Generation context:
   *   - campaign_id: int
   *   - dungeon_id: int
   *   - level_id: int
   *   - depth: int (dungeon depth, drives difficulty)
   *   - party_level: int (average party level)
   *   - room_index: int (which room in level)
   *   - theme: string (dungeon theme)
   *   - room_size: string (small|medium|large)
   *   - room_type: string (chamber|corridor|library|treasury, etc.)
   *   - terrain_type: string (stone|sand|crystal|lava, etc.)
   *   - ai_service: object (Claude/Gemini instance)
   *
   * @return array
   *   Complete room.schema.json structure:
   *   {
   *     "room_id": "uuid",
   *     "name": "The Fungal Pantry",
   *     "description": "Prose description for players...",
   *     "hexes": [{"q": 0, "r": 0, "terrain": "stone", ...}, ...],
   *     "terrain": {...},
   *     "lighting": {...},
   *     "entities": [...entity_instance.schema.json...],
   *     "state": {"explored": false, "cleared": false, ...}
   *   }
   */
  public function generateRoom(array $context): array {
    // TODO: Implementation
  }

  /**
   * Generate hexes for a room.
   *
   * Returns array of hex coordinates and terrain properties.
   *
   * @param array $context
   *   @see self::generateRoom()
   *
   * @return array
   *   Array of hexes:
   *   [
   *     {"q": 0, "r": 0, "terrain": "stone", "elevation_ft": 0, "objects": []},
   *     {"q": 1, "r": 0, "terrain": "stone", "elevation_ft": 0, "objects": []},
   *     ...
   *   ]
   */
  protected function generateHexes(array $context): array {
    // TODO: Implementation
  }

  /**
   * Generate room description via AI.
   *
   * Uses Claude/Gemini to generate narrative description compatible
   * with dungeon theme and PF2e rules.
   *
   * @param array $context
   * @param array $hexes
   *   Generated hexes
   *
   * @return array
   *   {
   *     "name": "The Fungal Pantry",
   *     "description": "As you enter, you notice...",
   *     "gm_notes": "Hidden door at q2,r1 (DC 18 Perception)"
   *   }
   */
  protected function generateDescription(array $context, array $hexes): array {
    // TODO: Implementation - Calls AI service
  }

  /**
   * Generate lighting for the room.
   *
   * Determines visibility, shadows, and illumination effects.
   *
   * @param array $context
   *
   * @return array
   *   {
   *     "illumination": "bright|dim|darkness",
   *     "light_sources": [...],
   *     "shadows": [...]
   *   }
   */
  protected function generateLighting(array $context): array {
    // TODO: Implementation
  }

  /**
   * Generate entities (creatures, items, traps, hazards).
   *
   * Delegates to EntityPlacerService and EncounterGeneratorService.
   *
   * @param array $context
   * @param array $hexes
   *
   * @return array
   *   Array of entity_instance.schema.json objects
   */
  protected function generateEntities(array $context, array $hexes): array {
    // TODO: Implementation
  }
}
```

**Database Persistence**:
```sql
INSERT INTO dc_campaign_rooms (
  campaign_id, dungeon_id, level_id, room_id, name, description, 
  environment_tags, layout_data, created, updated
) VALUES (
  :campaign_id, :dungeon_id, :level_id, :room_id, :name, :description,
  :tags_json, :layout_json, :now, :now
)
```

### 2. DungeonGeneratorService

**Purpose**: Orchestrate multi-level dungeon generation.

```php
namespace Drupal\dungeoncrawler_content\Service;

class DungeonGeneratorService {

  public function __construct(
    Connection $database,
    SchemaLoader $schemaLoader,
    RoomGeneratorService $roomGenerator,
    RoomConnectionAlgorithm $roomConnector,
    EncounterBalancer $encounterBalancer
  ) { }

  /**
   * Generate a complete dungeon.
   *
   * Workflow:
   * 1. Check if dungeon already exists (return cached)
   * 2. Determine dungeon depth based on party level
   * 3. Select theme based on location/level
   * 4. For each level:
   *    a. Generate hexmap
   *    b. Generate rooms
   *    c. Connect rooms
   *    d. Populate entities
   *    e. Generate encounters
   *    f. Validate total XP budget
   * 5. Persist to dc_campaign_dungeons
   * 6. Persist each level
   *
   * @param array $context
   *   Generation context:
   *   - campaign_id: int
   *   - location_x: int (world coordinates)
   *   - location_y: int
   *   - party_level: int (average party level)
   *   - party_composition: array (class breakdown for encounter balancing)
   *   - theme: string|null (override theme, or null for auto-select)
   *
   * @return array
   *   Complete dungeon_level.schema.json structure for each level
   */
  public function generateDungeon(array $context): array {
    // TODO: Implementation
  }

  /**
   * Generate a single dungeon level.
   *
   * @param array $context
   *   @see self::generateDungeon(), with additional:
   *   - depth: int (1-based level number)
   *
   * @return array
   *   dungeon_level.schema.json structure
   */
  public function generateLevel(array $context): array {
    // TODO: Implementation
  }

  /**
   * Select theme based on location and party level.
   *
   * Map coordinates influence theme selection:
   * - Northern mountain zone → dragon_lair, crystal_caves
   * - Forest zone → beast_den, spider_nests
   * - Underdark → undead_crypts, demonic_sanctum
   * - Volcanic → lava_forge, elemental_nexus
   *
   * @param int $x
   * @param int $y
   * @param int $party_level
   *
   * @return string
   *   Theme key from dungeon_level.schema.json enum
   */
  protected function selectTheme(int $x, int $y, int $party_level): string {
    // TODO: Implementation
  }

  /**
   * Determine dungeon depth (number of levels).
   *
   * Higher party levels → deeper dungeons (more exploration)
   * Scaling: party_level 1-6 = 1-2 levels, 7-15 = 2-4 levels, 16-20 = 3-5 levels
   *
   * @param int $party_level
   *
   * @return int
   *   Number of levels (1-5 typical, max 10)
   */
  protected function calculateDungeonDepth(int $party_level): int {
    // TODO: Implementation
  }
}
```

**Database Persistence**:
```sql
INSERT INTO dc_campaign_dungeons (
  campaign_id, dungeon_id, name, description, theme, dungeon_data, created, updated
) VALUES (
  :campaign_id, :dungeon_id, :name, :description, :theme, :data_json, :now, :now
)
```

### 3. EntityPlacerService

**Purpose**: Position creatures, items, traps, and hazards on the hex grid.

```php
namespace Drupal\dungeoncrawler_content\Service;

class EntityPlacerService {

  public function __construct(
    Connection $database,
    SchemaLoader $schemaLoader
  ) { }

  /**
   * Place entities in a room.
   *
   * Workflow:
   * 1. Validate entity definitions
   * 2. For each entity:
   *    a. Find valid hex placement (passable, no collision)
   *    b. Calculate visibility/line-of-sight
   *    c. Create entity_instance with placement
   * 3. Return array of placed entities
   *
   * @param array $entities
   *   Array of entity definitions to place:
   *   [
   *     {
   *       "entity_type": "creature|item|obstacle|trap|hazard",
   *       "entity_ref": "creature_id_or_item_id",
   *       "quantity": 1,
   *       "placement_hint": "near_door|center|back_corner|scattered"
   *     },
   *     ...
   *   ]
   *
   * @param array $hexes
   *   Room hex data
   *
   * @param array $context
   *   Placement context (theme, difficulty, etc.)
   *
   * @return array
   *   Array of entity_instance.schema.json:
   *   [
   *     {
   *       "entity_id": "uuid",
   *       "entity_type": "creature",
   *       "entity_ref": "goblin_fighter_1",
   *       "placement": {
   *         "hex": {"q": 2, "r": 1},
   *         "direction": "north",
   *         "height_above_ground": 0
   *       },
   *       "state": {"active": true, "hidden": false, ...}
   *     },
   *     ...
   *   ]
   */
  public function placeEntities(array $entities, array $hexes, array $context): array {
    // TODO: Implementation
  }

  /**
   * Find valid hex placement for single entity.
   *
   * Respects:
   * - Passability (no walls, cliffs)
   * - No collisions with other entities
   * - Line-of-sight from entrance
   *
   * @param string $placement_hint
   * @param array $hexes
   * @param array $occupied_hexes
   *   Already-placed entities' coordinates
   *
   * @return array|null
   *   Hex coordinate {"q": int, "r": int} or null if no valid placement
   */
  protected function findValidHex(
    string $placement_hint,
    array $hexes,
    array $occupied_hexes
  ): ?array {
    // TODO: Implementation
  }

  /**
   * Calculate line-of-sight from room entrance.
   *
   * Creatures in line-of-sight are visible; others are hidden.
   *
   * @param array $fromHex
   * @param array $toHex
   * @param array $blockingHexes
   *
   * @return bool
   */
  protected function hasLineOfSight(array $fromHex, array $toHex, array $blockingHexes): bool {
    // TODO: Implementation - Bresenham-like hex line algorithm
  }
}
```

**Database Persistence**:
```sql
INSERT INTO dc_campaign_characters (
  campaign_id, dungeon_id, room_id, entity_id, entity_type, entity_ref,
  position_q, position_r, placement_data, entity_state, created, updated
) VALUES (
  :campaign_id, :dungeon_id, :room_id, :entity_id, :type, :ref,
  :q, :r, :placement_json, :state_json, :now, :now
)
```

### 4. EncounterGeneratorService

**Purpose**: Generate balanced PF2e encounters for rooms.

```php
namespace Drupal\dungeoncrawler_content\Service;

class EncounterGeneratorService {

  public function __construct(
    Connection $database,
    EncounterBalancer $encounterBalancer,
    SchemaLoader $schemaLoader
  ) { }

  /**
   * Generate encounter for a room.
   *
   * Workflow:
   * 1. Determine XP budget based on party level and difficulty
   * 2. Select creatures from registry that fit theme
   * 3. Scale creatures to party level
   * 4. Build encounter ensuring within XP budget
   * 5. Validate threat level
   *
   * @param array $context
   *   Encounter context:
   *   - party_level: int
   *   - party_size: int
   *   - party_composition: array (class breakdown)
   *   - depth: int (dungeon level)
   *   - theme: string
   *   - difficulty: string (low|moderate|severe|extreme)
   *   - room_type: string (corridor|chamber, affects spacing)
   *
   * @return array
   *   encounter.schema.json structure:
   *   {
   *     "encounter_id": "uuid",
   *     "type": "combat",
   *     "threat_level": "moderate",
   *     "xp_budget": {...},
   *     "combatants": [...creature definitions...],
   *     "terrain_effects": [...]
   *   }
   */
  public function generateEncounter(array $context): array {
    // TODO: Implementation
  }

  /**
   * Calculate XP budget for encounter.
   *
   * PF2e XP budget thresholds:
   * - Trivial: 40 XP per party member
   * - Low: 60 XP per party member
   * - Moderate: 80 XP per party member
   * - Severe: 120 XP per party member
   * - Extreme: 160 XP per party member
   *
   * @param int $party_level
   * @param int $party_size
   * @param string $difficulty
   *
   * @return array
   *   {
   *     "target_xp": int,
   *     "min_xp": int,
   *     "max_xp": int,
   *     "difficulty": string
   *   }
   */
  protected function calculateXpBudget(
    int $party_level,
    int $party_size,
    string $difficulty
  ): array {
    // TODO: Implementation
  }

  /**
   * Select creatures for encounter.
   *
   * Queries creature registry for theme-appropriate creatures,
   * then scales to party level.
   *
   * @param array $context
   *
   * @return array
   *   Array of creature.schema.json (template, not scaled)
   */
  protected function selectCreatures(array $context): array {
    // TODO: Implementation
  }
}
```

---

## Controller Layer Design

### 1. RoomGeneratorController

```php
namespace Drupal\dungeoncrawler_content\Controller;

class RoomGeneratorController extends ControllerBase {

  public function __construct(
    RoomGeneratorService $roomGenerator,
    SchemaLoader $schemaLoader
  ) { }

  /**
   * POST /api/campaign/{campaign_id}/dungeons/{dungeon_id}/rooms
   *
   * Generate a new room in a dungeon level.
   *
   * Request:
   * {
   *   "level_id": "uuid",
   *   "depth": 1,
   *   "party_level": 5,
   *   "room_size": "medium",
   *   "room_type": "chamber",
   *   "terrain_type": "stone"
   * }
   *
   * Response: 201 Created
   * {
   *   "room_id": "uuid",
   *   "name": "The Goblin Barracks",
   *   "description": "...",
   *   "hexes": [...],
   *   "entities": [...],
   *   ...room.schema.json...
   * }
   */
  public function createRoom(
    Request $request,
    int $campaign_id,
    int $dungeon_id
  ): JsonResponse {
    // TODO: Implementation
  }

  /**
   * GET /api/campaign/{campaign_id}/dungeons/{dungeon_id}/rooms/{room_id}
   */
  public function getRoom(
    int $campaign_id,
    int $dungeon_id,
    string $room_id
  ): JsonResponse {
    // TODO: Implementation
  }

  /**
   * POST /api/campaign/{campaign_id}/dungeons/{dungeon_id}/rooms/{room_id}/regenerate
   *
   * Force regenerate a room (admin only).
   * WARNING: This will overwrite existing room data!
   */
  public function regenerateRoom(
    Request $request,
    int $campaign_id,
    int $dungeon_id,
    string $room_id
  ): JsonResponse {
    // TODO: Implementation
  }
}
```

### 2. DungeonGeneratorController

```php
namespace Drupal\dungeoncrawler_content\Controller;

class DungeonGeneratorController extends ControllerBase {

  public function __construct(
    DungeonGeneratorService $dungeonGenerator,
    SchemaLoader $schemaLoader
  ) { }

  /**
   * POST /api/campaign/{campaign_id}/dungeons/generate
   *
   * Generate a complete new dungeon.
   *
   * Request:
   * {
   *   "location_x": 100,
   *   "location_y": 200,
   *   "party_level": 5,
   *   "party_composition": {
   *     "fighter": 1,
   *     "wizard": 1,
   *     "cleric": 1,
   *     "rogue": 1
   *   },
   *   "theme": null  // null = auto-select based on location
   * }
   *
   * Response: 201 Created
   * {
   *   "dungeon_id": "uuid",
   *   "name": "The Goblin Warren",
   *   "theme": "goblin_warrens",
   *   "depth": 3,
   *   "levels": [
   *     { ...dungeon_level.schema.json... },
   *     { ...dungeon_level.schema.json... },
   *     { ...dungeon_level.schema.json... }
   *   ]
   * }
   */
  public function generateDungeon(
    Request $request,
    int $campaign_id
  ): JsonResponse {
    // TODO: Implementation
  }

  /**
   * GET /api/campaign/{campaign_id}/dungeons/{dungeon_id}
   */
  public function getDungeon(
    int $campaign_id,
    int $dungeon_id
  ): JsonResponse {
    // TODO: Implementation
  }

  /**
   * GET /api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels/{depth}
   *
   * Get single level of dungeon.
   */
  public function getDungeonLevel(
    int $campaign_id,
    int $dungeon_id,
    int $depth
  ): JsonResponse {
    // TODO: Implementation
  }

  /**
   * POST /api/campaign/{campaign_id}/dungeons/{dungeon_id}/levels
   *
   * Extend dungeon with new level (go deeper).
   * Used when party descends to unexplored level.
   */
  public function addDungeonLevel(
    Request $request,
    int $campaign_id,
    int $dungeon_id
  ): JsonResponse {
    // TODO: Implementation
  }
}
```

---

## Data Structures & Schemas

### Room Structure (room.schema.json)

```json
{
  "schema_version": "1.0.0",
  "room_id": "uuid",
  "name": "The Fungal Pantry",
  "description": "As you enter, the air grows thick with spores...",
  "gm_notes": "Creatures are dormant until disturbed",
  
  "hexes": [
    {
      "q": 0, "r": 0,
      "terrain": "stone",
      "elevation_ft": 0,
      "objects": []
    },
    {
      "q": 1, "r": 0,
      "terrain": "stone",
      "elevation_ft": 0,
      "objects": []
    }
  ],
  
  "room_type": "chamber",
  "terrain": {
    "primary_type": "stone",
    "secondary_features": ["mushrooms", "moisture"]
  },
  
  "lighting": {
    "illumination": "dim",
    "light_sources": [
      {
        "hex": {"q": 0, "r": 0},
        "type": "bioluminescent_mushrooms",
        "brightness": 20
      }
    ]
  },
  
  "entities": [
    {
      "entity_id": "uuid",
      "entity_type": "creature",
      "entity_ref": "fungal_shambler_7",
      "placement": {
        "hex": {"q": 2, "r": 1},
        "direction": "east"
      },
      "state": {
        "active": true,
        "hidden": false,
        "hit_points": 28,
        "conditions": []
      }
    }
  ],
  
  "state": {
    "explored": false,
    "cleared": false,
    "discovered_at": null,
    "cleared_at": null
  }
}
```

### Dungeon Level Structure (dungeon_level.schema.json)

```json
{
  "schema_version": "1.0.0",
  "level_id": "uuid",
  "depth": 1,
  "theme": "goblin_warrens",
  "name": "The Warren Entrance",
  "flavor_text": "You descend into darkness...",
  
  "hex_map": {
    "width": 40,
    "height": 30,
    "hexes": [...]
  },
  
  "rooms": [
    { ...room.schema.json... },
    { ...room.schema.json... }
  ],
  
  "entities": [
    { ...entity_instance.schema.json... },
    { ...entity_instance.schema.json... }
  ],
  
  "generation_rules": {
    "party_level_target": 5,
    "difficulty_modifier": 1.0,
    "creature_themes": ["goblin", "animal"],
    "hazard_frequency": 0.15,
    "treasure_density": 0.08
  }
}
```

### Entity Instance Structure (entity_instance.schema.json)

```json
{
  "entity_id": "uuid",
  "entity_type": "creature",
  "entity_ref": "goblin_fighter_1",
  "version": "1.0.0",
  
  "placement": {
    "hex": {"q": 5, "r": 3},
    "height_above_ground_ft": 0
  },
  
  "state": {
    "active": true,
    "destroyed": false,
    "disabled": false,
    "hidden": false,
    "collected": false,
    "hit_points": 15,
    "conditions": ["frightened_1"],
    "inventory": [],
    "metadata": {}
  },
  
  "created_at": "2026-02-18T10:00:00Z",
  "updated_at": "2026-02-18T10:00:00Z"
}
```

---

## Database Tables

### dc_campaign_dungeons

```sql
CREATE TABLE dc_campaign_dungeons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id INT UNSIGNED NOT NULL,
  dungeon_id VARCHAR(100) NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  theme VARCHAR(64),
  dungeon_data LONGTEXT NOT NULL,  -- Complete dungeon structure as JSON
  source_dungeon_id VARCHAR(100),  -- Library dungeon if copied
  created INT NOT NULL,
  updated INT NOT NULL,
  
  PRIMARY KEY (id),
  UNIQUE KEY campaign_dungeon (campaign_id, dungeon_id),
  INDEX campaign_id (campaign_id),
  INDEX theme (theme)
);
```

### dc_campaign_rooms

```sql
CREATE TABLE dc_campaign_rooms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id INT UNSIGNED NOT NULL,
  dungeon_id INT UNSIGNED NOT NULL,
  level_id INT UNSIGNED NOT NULL,
  room_id VARCHAR(100) NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  environment_tags TEXT,  -- JSON array
  layout_data LONGTEXT NOT NULL,  -- Complete room JSON
  created INT NOT NULL,
  updated INT NOT NULL,
  
  PRIMARY KEY (id),
  UNIQUE KEY campaign_room (campaign_id, room_id),
  INDEX campaign_id (campaign_id),
  INDEX dungeon_level (dungeon_id, level_id)
);
```

### dc_campaign_characters (extended use)

Hot columns for O(1) access:
```
position_q INT,        -- Hex axial Q
position_r INT,        -- Hex axial R
entity_type VARCHAR(32),  -- creature|item|obstacle|trap|hazard
entity_ref VARCHAR(100),   -- Reference to definition
```

---

## API Endpoints

### Room Generation

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `POST` | `/api/campaign/{id}/dungeons/{dungeon_id}/levels/{depth}/rooms` | Generate room |
| `GET` | `/api/campaign/{id}/dungeons/{dungeon_id}/rooms/{room_id}` | Get room |
| `POST` | `/api/campaign/{id}/dungeons/{dungeon_id}/rooms/{room_id}/regenerate` | Regenerate (admin) |

### Dungeon Generation

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `POST` | `/api/campaign/{id}/dungeons/generate` | Generate complete dungeon |
| `GET` | `/api/campaign/{id}/dungeons/{dungeon_id}` | Get dungeon |
| `GET` | `/api/campaign/{id}/dungeons/{dungeon_id}/levels/{depth}` | Get level |
| `POST` | `/api/campaign/{id}/dungeons/{dungeon_id}/levels` | Extend dungeon |

---

## Generation Workflow

### Room Generation Flow

```
1. Validate request input
   ├─ Campaign exists
   ├─ Dungeon exists
   ├─ Level exists
   └─ User has permission

2. Check if room already exists
   ├─ YES: Return cached room
   └─ NO: Proceed to generation

3. RoomGeneratorService::generateRoom()
   ├─ Generate hexes
   │  ├─ Determine room boundary
   │  ├─ Assign terrain types
   │  ├─ Calculate elevations
   │  └─ Place obstacles
   │
   ├─ Generate description (AI)
   │  ├─ Build prompt from theme + terrain + depth
   │  ├─ Call Claude/Gemini API
   │  └─ Parse response
   │
   ├─ Generate lighting
   │  ├─ Determine base illumination
   │  ├─ Place light sources
   │  └─ Calculate shadows
   │
   ├─ Generate entities
   │  ├─ RoomConnectionAlgorithm: determine creature count
   │  ├─ EncounterGeneratorService: build encounter
   │  ├─ EntityPlacerService: place on hexes
   │  └─ Validate no collisions
   │
   └─ Validate against room.schema.json

4. Save to database
   ├─ dc_campaign_rooms (room metadata)
   └─ dc_campaign_characters (entity placements)

5. Return response
```

### Dungeon Generation Flow

```
1. Validate request input
   ├─ Campaign exists
   ├─ World coordinates valid
   ├─ Party level 1-20
   └─ User has permission

2. Check if dungeon exists at location
   ├─ YES: Return cached dungeon
   └─ NO: Proceed to generation

3. Select theme (auto or override)

4. Calculate dungeon depth based on party level

5. For each level (depth 1 to N):
   ├─ Generate hexmap
   │  ├─ Determine size based on depth/theme
   │  └─ Assign terrain zones
   │
   ├─ Calculate room count
   │
   ├─ Generate room list
   │  └─ Call RoomGeneratorService::generateRoom() N times
   │
   ├─ Connect rooms
   │  ├─ RoomConnectionAlgorithm::connectRooms()
   │  ├─ Modified Delaunay Triangulation
   │  ├─ Generate Minimum Spanning Tree
   │  └─ Add back 15% of removed edges
   │
   ├─ Validate total XP budget
   │  └─ EncounterBalancer::validateLevel()
   │
   └─ Persist level
      ├─ dc_campaign_dungeons (level metadata)
      └─ dc_campaign_rooms (all rooms in level)

6. Validate entire dungeon structure

7. Return response (all 3 levels)
```

---

## Tileset Generation & Prompt Cache

### Goals

- Provide full control over terrain/habitat tile generation (style, palette, view, resolution).
- Avoid duplicate tile generation by reusing cached prompt results.
- Seed a consistent, reusable base tileset for each dungeon theme.

### Control Surface (Generation Inputs)

- **Prompt attributes**: `entity_type`, `terrain_type`, `room_type`, `habitat`, `biome_theme`, `palette`, `lighting`, `mood`.
- **Render controls**: `resolution`, `aspect_ratio`, `view`, `tileable`, `background`.
- **Governance**: `negative_prompt`, `provider`, `seed`, `variations_count`.
- **Context**: `campaign_id`, `map_id`, `dungeon_id`, `room_id`, `hex_q`, `hex_r`.

### Cache Behavior

- Every request stores `prompt_text`, `negative_prompt`, and normalized payload in `dungeoncrawler_content_image_prompt_cache`.
- Vertex generation checks the cache first (prompt + parameters hash).
- On cache hit, return cached output and skip the external API call.

### Goblin Warren Base Tileset

Use this as the Phase 1 tileset baseline for a classic goblin warren theme. All tiles are `floortile` unless noted.

**Floor Core (tileable)**
- Rough stone floor (primary)
- Packed dirt floor
- Muddy track with subtle ruts
- Damp stone with moss edges
- Fungal growth patch (variation)

**Edges & Transitions (tileable)**
- Rough stone to dirt transition
- Rough stone to moss transition
- Dirt to mud transition

**Hazard Tiles (tileable where possible)**
- Shallow water seep tile
- Slime slick tile
- Pit edge tile (overlay)

**Props & Obstacles (transparent background)**
- Crude barricade (wood + scrap)
- Bone pile cluster
- Broken crate debris
- Fungus spore bloom

**Light & Effects (transparent background)**
- Torch glow decal (warm)
- Smoke haze decal (subtle)

**Special Set Pieces**
- Goblin shrine floor emblem (tileable, no text)
- Guard post dais tile

### Tile Spec Defaults

- Resolution: 512px base (1024px for hero tiles)
- Aspect ratio: 1:1
- Camera: top-down orthographic for floortile; top-down 3/4 for props
- Lighting: upper-left, consistent across set
- Output: transparent PNG for props/decals; opaque for floortiles

---

## Composition Patterns

### Dependency Injection

All services use constructor injection via Drupal's service container:

```yaml
# dungeoncrawler_content.services.yml
services:
  dungeoncrawler_content.room_generator:
    class: Drupal\dungeoncrawler_content\Service\RoomGeneratorService
    arguments:
      - '@database'
      - '@dungeoncrawler_content.schema_loader'
      - '@dungeoncrawler_content.entity_placer'
      - '@dungeoncrawler_content.encounter_generator'

  dungeoncrawler_content.dungeon_generator:
    class: Drupal\dungeoncrawler_content\Service\DungeonGeneratorService
    arguments:
      - '@database'
      - '@dungeoncrawler_content.schema_loader'
      - '@dungeoncrawler_content.room_generator'
      - '@dungeoncrawler_content.room_connection'
      - '@dungeoncrawler_content.encounter_balancer'

  dungeoncrawler_content.entity_placer:
    class: Drupal\dungeoncrawler_content\Service\EntityPlacerService
    arguments:
      - '@database'
      - '@dungeoncrawler_content.schema_loader'

  dungeoncrawler_content.encounter_generator:
    class: Drupal\dungeoncrawler_content\Service\EncounterGeneratorService
    arguments:
      - '@database'
      - '@dungeoncrawler_content.encounter_balancer'
      - '@dungeoncrawler_content.schema_loader'
```

### Validation Pattern

All generated data validates against JSON schemas:

```php
// In RoomGeneratorService
$validated = $this->schemaLoader->validateRoomData($generated_room);
if (!$validated['valid']) {
  throw new SchemaValidationException(
    'Generated room failed validation: ' . json_encode($validated['errors'])
  );
}
```

### Error Handling

```php
// Custom exception hierarchy
DungeonCrawlerException
├─ GenerationException (room/dungeon generation failures)
│  ├─ RoomGenerationException
│  ├─ DungeonGenerationException
│  └─ EntityPlacementException
├─ SchemaValidationException (schema violations)
├─ ContentNotFoundException (entity not found)
└─ PermissionDeniedException (access denied)
```

---

## Error Handling

### Generation Failures

| Error | Cause | Recovery |
|-------|-------|----------|
| **No Valid Hex** | All hexes occupied | Retry with larger search radius |
| **XP Budget Exceeded** | Encounter too hard | Remove weakest creature, retry |
| **Schema Validation** | Invalid data structure | Log error, regenerate |
| **AI Timeout** | Claude/Gemini timeout | Use template fallback |

### HTTP Responses

```
200 OK - Room/dungeon found (cached)
201 Created - Room/dungeon generated
400 Bad Request - Invalid input parameters
403 Forbidden - Permission denied
404 Not Found - Campaign/dungeon not found
409 Conflict - Room already exists (but duplicate request safe)
422 Unprocessable Entity - Generation validation failed
500 Internal Server Error - Unexpected error (log and notify)
```

---

## Testing Strategy

### Unit Tests

```php
// tests/Unit/RoomGeneratorTest.php
- Test hex generation (size, shape, terrain variety)
- Test entity placement (no collisions, valid LOS)
- Test description generation (non-empty, theme-appropriate)
- Test schema validation (generated data conforms)

// tests/Unit/DungeonGeneratorTest.php
- Test theme selection (location-aware)
- Test depth calculation (party-level appropriate)
- Test level generation (100+ rooms, connected)
- Test XP budget validation

// tests/Unit/RoomConnectionAlgorithmTest.php
- Test Delaunay triangulation (all rooms connected)
- Test MST generation (minimum edges)
- Test edge pruning (loops added back)
```

### Integration Tests

```php
// tests/Kernel/GenerationWorkflowTest.php
- Full dungeon generation (3 levels)
- Room persistence (database round-trip)
- Entity placement (coordinates correct)
- Encounter balancing (XP within budget)

// tests/Functional/GenerationApiTest.php
- POST /api/campaign/{id}/dungeons/generate
- GET /api/campaign/{id}/dungeons/{dungeon_id}
- Permission tests (admin only endpoints)
- 409 conflict on duplicate generate request
```

### Test Data

```
fixtures/
├─ rooms/
│  └─ generated_rooms.yml  (20 example rooms)
├─ dungeons/
│  └─ generated_dungeons.yml (5 example dungeons)
└─ creatures/
   └─ creature_registry.yml (100 template creatures)
```

---

## Implementation Roadmap

### Phase 1: Foundation (Weeks 1-2) ✅ COMPLETE

- [x] Create RoomGeneratorService stub
- [x] Create DungeonGeneratorService stub
- [x] Create RoomGeneratorController
- [x] Create DungeonGeneratorController
- [x] Add routing for API endpoints
- [x] Set up service container entries

### Phase 2: Hex Generation (Weeks 2-3) ✅ COMPLETE

- [x] Implement hex generation algorithm
- [x] Implement terrain assignment
- [x] Implement elevation calculation
- [x] Add unit tests

### Phase 3: Entity Placement (Weeks 3-4) ✅ COMPLETE

- [x] Implement EntityPlacerService
- [x] Implement hex collision detection
- [x] Implement line-of-sight calculation
- [x] Add comprehensive tests

### Phase 4: Room Generation (Weeks 4-5) ✅ COMPLETE

- [x] Implement full RoomGeneratorService — `generateRoom()`, `persistRoom()`, `getRoomFromCache()`, AI description generation
- [x] Integrate AI description generation — `generateAIDescription()` with JSON parsing fallback
- [x] Complete schema validation — wired into `generateRoom()` flow (non-blocking)
- [x] Integration testing

### Phase 5: Dungeon Orchestration (Weeks 5-6) ✅ COMPLETE

- [x] Implement full DungeonGeneratorService — `generateDungeon()`, `generateHexmap()`, `persistDungeon()` with DB persistence
- [x] Implement room connection algorithm — Delaunay triangulation approximation, Kruskal MST, BFS validation, BSP generation
- [x] Implement multi-level persistence — rooms + creatures persisted to `dc_campaign_rooms` and `dc_campaign_characters`
- [x] Complete end-to-end testing

### Phase 6: API & Documentation (Week 6-7) — IN PROGRESS

- [x] RoomGeneratorController — `createRoom` (POST), `getRoom` (GET), `regenerateRoom` (POST) all implemented
- [x] DungeonGeneratorController — `generateDungeon` (POST), `getDungeon` (GET), `getDungeonLevel` (GET), `addDungeonLevel` (POST) all implemented
- [ ] API documentation (OpenAPI/Swagger)
- [ ] Performance testing
- [ ] Load testing

---

## Related Documentation

- **[HEXMAP_ARCHITECTURE.md](HEXMAP_ARCHITECTURE.md)** — Hex coordinate system, schema hierarchy
- **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)** — Complete REST API reference
- **[config/schemas/README.md](config/schemas/README.md)** — All schema specifications
- **[issue-4-procedural-dungeon-generation-design.md](issue-4-procedural-dungeon-generation-design.md)** — Original design specification
- **[RoomConnectionAlgorithm.php](src/Service/RoomConnectionAlgorithm.php)** — Room linking implementation
