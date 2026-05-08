# Tileset Management & Tracking Architecture

> **Status (Current)**: Design proposal / not fully implemented.
>
> This document describes a target-state tileset system. Several items here are architectural proposals rather than active runtime components.
>
> Current implementation note:
> - The proposed tables (`dc_tilesets`, `dc_tile_instances`) are not present in active module schema.
> - The proposed service `dungeoncrawler_content.tileset_manager` / `TilesetManagerService` is not currently registered.
>
> Treat SQL and service sections below as planning/reference material unless and until corresponding schema hooks and service wiring are merged.

## Overview

Tilesets define the visual vocabulary and mechanical properties for dungeon levels. Each dungeon map should reference a tileset that determines available tiles, their appearance, and behavior.

## Core Concepts

### 1. Tileset Definition (Static)
JSON files defining available tiles and their properties:
- `/config/examples/standard-dungeon-tiles.json` - Base tile definitions
- `/config/examples/goblin-warren-tileset.json` - Theme-specific tile collection
- `/config/examples/ancient-crypt-tileset.json` - Another theme

### 2. Tileset Instance (Per Campaign)
A specific tileset applied to a dungeon map or level:
- Links dungeon map to tileset definition
- Tracks which tiles have been generated
- Stores tile image URLs
- Records tile state changes

### 3. Tile Instance (Per Hex)
Individual tile placement in a specific dungeon:
- Position (hex coordinates)
- Current state (open/closed, looted, triggered)
- Entity links (contains what items, which NPCs)
- Custom properties (overrides)

## Proposed Database Schema

### Table: `dc_tilesets`
Master registry of available tilesets.

```sql
CREATE TABLE dc_tilesets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tileset_id VARCHAR(64) NOT NULL UNIQUE COMMENT 'Unique identifier (e.g., goblin-warren-v1)',
  name VARCHAR(255) NOT NULL COMMENT 'Human-readable name',
  description TEXT COMMENT 'Tileset description',
  version VARCHAR(16) NOT NULL DEFAULT '1.0.0',
  definition_file VARCHAR(255) NOT NULL COMMENT 'Path to JSON definition file',
  tile_size INT UNSIGNED NOT NULL DEFAULT 512,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created INT NOT NULL DEFAULT 0,
  updated INT NOT NULL DEFAULT 0,
  INDEX idx_tileset_id (tileset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Master registry of available tilesets';
```

### Table: `dc_tileset_tiles`
Catalog of tiles within each tileset (for quick lookup).

```sql
CREATE TABLE dc_tileset_tiles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tileset_id VARCHAR(64) NOT NULL COMMENT 'References dc_tilesets.tileset_id',
  tile_id VARCHAR(64) NOT NULL COMMENT 'Tile identifier within tileset',
  tile_name VARCHAR(255) NOT NULL,
  category VARCHAR(32) NOT NULL COMMENT 'floor, wall, door, container, etc.',
  passability VARCHAR(16) COMMENT 'passable, impassable, difficult, conditional',
  properties LONGTEXT COMMENT 'Full tile properties as JSON',
  image_uuid VARCHAR(36) COMMENT 'References dc_generated_images.image_uuid if generated',
  created INT NOT NULL DEFAULT 0,
  updated INT NOT NULL DEFAULT 0,
  UNIQUE KEY unique_tileset_tile (tileset_id, tile_id),
  INDEX idx_tileset_id (tileset_id),
  INDEX idx_category (category),
  INDEX idx_image (image_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catalog of tiles within each tileset';
```

### Table: `dungeon_maps` (extend existing)
Link dungeons to tilesets.

```sql
ALTER TABLE dungeon_maps 
ADD COLUMN tileset_id VARCHAR(64) NULL 
COMMENT 'Tileset used for this map' AFTER map_id,
ADD INDEX idx_tileset_id (tileset_id);
```

### Table: `dc_tile_instances`
Individual tile placements in dungeons with state tracking.

```sql
CREATE TABLE dc_tile_instances (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT UNSIGNED NOT NULL,
  map_id VARCHAR(255) NOT NULL COMMENT 'Dungeon map identifier',
  hex_q INT NOT NULL COMMENT 'Hex Q coordinate',
  hex_r INT NOT NULL COMMENT 'Hex R coordinate',
  tileset_id VARCHAR(64) NOT NULL COMMENT 'Source tileset',
  tile_id VARCHAR(64) NOT NULL COMMENT 'Base tile definition',
  
  -- Current state
  state VARCHAR(32) COMMENT 'Current state (open/closed/locked/triggered/etc)',
  passability VARCHAR(16) COMMENT 'Current passability (may differ from base)',
  blocks_vision TINYINT(1) COMMENT 'Current vision blocking (may change)',
  
  -- Inventory/container tracking
  has_inventory TINYINT(1) NOT NULL DEFAULT 0,
  inventory_items LONGTEXT COMMENT 'JSON array of item IDs in container',
  is_looted TINYINT(1) NOT NULL DEFAULT 0,
  
  -- Interactive state
  interaction_count INT NOT NULL DEFAULT 0 COMMENT 'Number of times interacted',
  last_interaction INT COMMENT 'Timestamp of last interaction',
  triggered_by_uid INT UNSIGNED COMMENT 'User who triggered trap/interaction',
  
  -- Custom properties (overrides)
  custom_properties LONGTEXT COMMENT 'JSON object with property overrides',
  
  -- Lighting and effects
  provides_light TINYINT(1) NOT NULL DEFAULT 0,
  light_radius INT COMMENT 'Current light radius if providing light',
  active_effects LONGTEXT COMMENT 'JSON array of active effect IDs',
  
  created INT NOT NULL DEFAULT 0,
  updated INT NOT NULL DEFAULT 0,
  
  UNIQUE KEY unique_tile_position (campaign_id, map_id, hex_q, hex_r),
  INDEX idx_campaign_map (campaign_id, map_id),
  INDEX idx_tileset (tileset_id),
  INDEX idx_tile_id (tile_id),
  INDEX idx_has_inventory (has_inventory),
  INDEX idx_provides_light (provides_light)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual tile instances in dungeons with state tracking';
```

## Workflow: Tileset to Dungeon

### 1. Tileset Registration
Import tileset definitions into database:

```php
$tileset_service = \Drupal::service('dungeoncrawler_content.tileset_manager');

$tileset_service->registerTileset([
  'tileset_id' => 'goblin-warren-v1',
  'name' => 'Goblin Warren',
  'description' => 'Grimy underground goblin lair',
  'version' => '1.0.0',
  'definition_file' => 'config/examples/goblin-warren-tileset.json',
]);
```

### 2. Dungeon Creation
Assign tileset to dungeon map:

```php
$dungeon_service->createDungeon([
  'campaign_id' => 123,
  'map_id' => 'goblin_lair_level_1',
  'tileset_id' => 'goblin-warren-v1',
  'dimensions' => ['width' => 20, 'height' => 15],
]);
```

### 3. Tile Placement (Procedural Generation)
Place tiles during dungeon generation:

```php
$dungeon_generator->placeTile([
  'campaign_id' => 123,
  'map_id' => 'goblin_lair_level_1',
  'hex_q' => 5,
  'hex_r' => 7,
  'tile_id' => 'floor_stone_clean',
  'properties' => [], // Use defaults from tileset
]);

$dungeon_generator->placeTile([
  'campaign_id' => 123,
  'map_id' => 'goblin_lair_level_1',
  'hex_q' => 6,
  'hex_r' => 7,
  'tile_id' => 'door_wood_closed',
  'properties' => [
    'state' => 'locked',
    'dc_pick' => 15,
  ],
]);
```

### 4. Runtime State Updates
Update tile state during gameplay:

```php
// Player opens door
$tile_manager->updateTileState(
  $campaign_id,
  $map_id,
  $hex_q,
  $hex_r,
  ['state' => 'open', 'passability' => 'passable']
);

// Player loots chest
$tile_manager->lootContainer(
  $campaign_id,
  $map_id,
  $hex_q,
  $hex_r,
  $character_id
);
```

### 5. Tile Image Generation
Generate images for all tiles in tileset:

```php
$terrain_gen = \Drupal::service('dungeoncrawler_content.terrain_image_generator');

$result = $terrain_gen->generateTilesetFromFile([
  'tileset_file' => 'goblin-warren-tileset.json',
  'provider' => 'vertex',
  'persist' => true,
]);

// Link generated images back to tileset
foreach ($result['tiles'] as $tile_result) {
  if ($tile_result['status'] === 'success') {
    $tileset_service->linkTileImage(
      'goblin-warren-v1',
      $tile_result['tile_id'],
      $tile_result['image_uuid']
    );
  }
}
```

## Service Layer Architecture

### TilesetManagerService
Core service for tileset operations:

```php
namespace Drupal\dungeoncrawler_content\Service;

class TilesetManagerService {
  
  /**
   * Register a tileset definition
   */
  public function registerTileset(array $config): array;
  
  /**
   * Load tileset definition from JSON
   */
  public function loadTileset(string $tileset_id): array;
  
  /**
   * Get all tiles in a tileset
   */
  public function getTilesetTiles(string $tileset_id): array;
  
  /**
   * Link generated image to tile
   */
  public function linkTileImage(string $tileset_id, string $tile_id, string $image_uuid): bool;
  
  /**
   * Get tile definition with image URL
   */
  public function getTileWithImage(string $tileset_id, string $tile_id): array;
  
  /**
   * Import tileset from JSON file
   */
  public function importTilesetFromFile(string $file_path): array;
  
  /**
   * Check if all tiles in tileset have images
   */
  public function isTilesetComplete(string $tileset_id): bool;
  
  /**
   * Get tileset generation progress
   */
  public function getTilesetProgress(string $tileset_id): array;
}
```

### TileInstanceManagerService
Manage tile instances in dungeons:

```php
namespace Drupal\dungeoncrawler_content\Service;

class TileInstanceManagerService {
  
  /**
   * Place tile instance in dungeon
   */
  public function placeTile(array $config): int;
  
  /**
   * Get tile instance at coordinates
   */
  public function getTileAt(int $campaign_id, string $map_id, int $q, int $r): ?array;
  
  /**
   * Update tile state
   */
  public function updateTileState(int $campaign_id, string $map_id, int $q, int $r, array $state): bool;
  
  /**
   * Interact with tile
   */
  public function interactWithTile(int $campaign_id, string $map_id, int $q, int $r, string $action, int $character_id): array;
  
  /**
   * Check if tile is passable
   */
  public function isPassable(int $campaign_id, string $map_id, int $q, int $r): bool;
  
  /**
   * Get all tiles in map area
   */
  public function getTilesInArea(int $campaign_id, string $map_id, array $hex_range): array;
  
  /**
   * Loot container tile
   */
  public function lootContainer(int $campaign_id, string $map_id, int $q, int $r, int $character_id): array;
  
  /**
   * Trigger trap tile
   */
  public function triggerTrap(int $campaign_id, string $map_id, int $q, int $r, int $character_id): array;
  
  /**
   * Get tiles providing light
   */
  public function getLightSources(int $campaign_id, string $map_id): array;
  
  /**
   * Destroy/break tile
   */
  public function destroyTile(int $campaign_id, string $map_id, int $q, int $r, int $damage): array;
}
```

## Multi-Level Dungeon Support

### Scenario: 3-Level Goblin Warren

```php
// Level 1: Upper warren (75% standard tiles, 25% decorative)
$dungeon_service->createDungeon([
  'campaign_id' => 123,
  'map_id' => 'goblin_warren_level_1',
  'tileset_id' => 'goblin-warren-v1',
  'floor' => 1,
  'tile_distribution' => [
    'floor_stone_clean' => 0.40,
    'floor_stone_cracked' => 0.30,
    'floor_stone_rubble' => 0.05,
    'floor_dirt_packed' => 0.20,
    'decoration_bones' => 0.05,
  ],
]);

// Level 2: Middle warren (more hazards, more rubble)
$dungeon_service->createDungeon([
  'campaign_id' => 123,
  'map_id' => 'goblin_warren_level_2',
  'tileset_id' => 'goblin-warren-v1',
  'floor' => 2,
  'tile_distribution' => [
    'floor_stone_cracked' => 0.30,
    'floor_stone_rubble' => 0.20,
    'floor_water_shallow' => 0.10,
    'hazard_spike_trap' => 0.05,
    'hazard_pit_covered' => 0.03,
    'decoration_bones' => 0.07,
  ],
]);

// Level 3: Deep caverns (switch tileset to volcanic)
$dungeon_service->createDungeon([
  'campaign_id' => 123,
  'map_id' => 'goblin_warren_level_3',
  'tileset_id' => 'volcanic-cavern-v1', // Different tileset!
  'floor' => 3,
  'tile_distribution' => [
    'floor_stone_hot' => 0.50,
    'floor_lava' => 0.10,
    'hazard_fire_grate' => 0.15,
  ],
]);
```

## Tileset Inheritance & Variants

### Base Tileset + Variants

```php
// Register base tileset
$tileset_service->registerTileset([
  'tileset_id' => 'standard-dungeon-v1',
  'name' => 'Standard Dungeon',
  'is_default' => true,
]);

// Register themed variants
$tileset_service->registerTileset([
  'tileset_id' => 'goblin-warren-v1',
  'name' => 'Goblin Warren',
  'parent_tileset_id' => 'standard-dungeon-v1', // Inherits base tiles
  'overrides' => ['floor_stone_clean', 'wall_stone_solid'], // Custom versions
]);

$tileset_service->registerTileset([
  'tileset_id' => 'ancient-crypt-v1',
  'name' => 'Ancient Crypt',
  'parent_tileset_id' => 'standard-dungeon-v1',
  'overrides' => ['floor_stone_clean', 'decoration_statue'],
  'additions' => ['floor_cursed', 'hazard_spectral_trap'], // New tiles
]);
```

## Caching Strategy

### 1. Definition Caching
Cache parsed tileset JSON in memory:
```php
$cache = \Drupal::cache('dungeoncrawler_tileset');
$tileset = $cache->get("tileset:{$tileset_id}");
```

### 2. Image Caching
Prompt cache already handles this via `dungeoncrawler_content_image_prompt_cache`

### 3. Tile Instance Caching
Cache active dungeon tiles:
```php
$cache = \Drupal::cache('dungeoncrawler_tiles');
$tiles = $cache->get("map_tiles:{$campaign_id}:{$map_id}");
```

## Migration Path

### Phase 1: Schema Creation
1. Create `dc_tilesets` table
2. Create `dc_tileset_tiles` table
3. Add `tileset_id` to `dungeon_maps`
4. Create `dc_tile_instances` table

### Phase 2: Service Implementation
1. Implement `TilesetManagerService`
2. Implement `TileInstanceManagerService`
3. Update `DungeonGenerationEngine` to use tilesets
4. Update dungeon state APIs to track tile instances

### Phase 3: Data Population
1. Import standard tileset definitions
2. Generate base tile images
3. Link existing dungeons to default tileset
4. Migrate any existing tile data

### Phase 4: UI Integration
1. Admin UI for tileset management
2. Tileset selection in dungeon creation
3. Tile palette/browser for manual placement
4. Tile state visualization in game UI

## Best Practices

### 1. Tileset Versioning
Always include version in tileset_id:
- `goblin-warren-v1` (initial)
- `goblin-warren-v2` (updated art)
- Allows dungeons to keep stable references

### 2. Default Fallbacks
Provide sensible defaults:
```php
$tileset_id = $map['tileset_id'] ?? 'standard-dungeon-v1';
```

### 3. Lazy Loading
Don't load all tileset images upfront:
- Load tile instances for current dungeon floor
- Load images for visible hexes only
- Progressive image loading

### 4. State Validation
Validate state transitions:
```php
if ($tile['state'] === 'locked' && $action === 'open') {
  return ['error' => 'Door is locked, use unlock action'];
}
```

### 5. Audit Trail
Track tile interactions:
- Who opened which door
- Who looted which chest
- Who triggered which trap
- Useful for debugging and player stats

## Example: Complete Workflow

```php
// 1. Register tileset
$tileset_service->registerTileset([
  'tileset_id' => 'goblin-warren-v1',
  'definition_file' => 'config/examples/goblin-warren-tileset.json',
]);

// 2. Generate tile images
$terrain_gen->generateTilesetFromFile([
  'tileset_file' => 'goblin-warren-tileset.json',
  'provider' => 'vertex',
]);

// 3. Create dungeon with tileset
$dungeon_service->createDungeon([
  'campaign_id' => 123,
  'map_id' => 'test_dungeon',
  'tileset_id' => 'goblin-warren-v1',
]);

// 4. Generate dungeon layout (places tiles)
$dungeon_generator->generateDungeon([
  'campaign_id' => 123,
  'map_id' => 'test_dungeon',
  'size' => 'medium',
]);

// 5. Load dungeon for rendering
$tiles = $tile_manager->getTilesInArea(
  123,
  'test_dungeon',
  ['q' => [0, 10], 'r' => [0, 10]]
);

// 6. Player interacts with door
$result = $tile_manager->interactWithTile(
  123,
  'test_dungeon',
  5, 7, // hex coordinates
  'open',
  $character_id
);
```

## Conclusion

This architecture provides:
- ✅ **Separation of Concerns**: Definition vs. Instance vs. State
- ✅ **Reusability**: Same tileset across multiple dungeons
- ✅ **Performance**: Caching at multiple levels
- ✅ **Flexibility**: Custom tiles, overrides, variants
- ✅ **Scalability**: Efficient queries, indexed lookups
- ✅ **Maintainability**: Clear service boundaries
- ✅ **Tracability**: Full audit trail of tile interactions
