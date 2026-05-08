# Standard Dungeon Tile System

## Overview

This document defines the comprehensive tile system for the tactical dungeon crawler. Tiles are the fundamental building blocks of dungeon maps, defining terrain, obstacles, interactive objects, and environmental features.

## Tile Categories

### 1. Floor Tiles (Passable Terrain)
Basic movement surfaces with varying costs and effects:

- **floor_stone_clean** - Standard passable floor (movement cost: 1.0)
- **floor_stone_cracked** - Weathered but passable (movement cost: 1.0)
- **floor_stone_rubble** - Difficult terrain (movement cost: 2.0)
- **floor_wood_planks** - Wooden flooring (movement cost: 1.0)
- **floor_dirt_packed** - Compacted earth (movement cost: 1.0)
- **floor_water_shallow** - Difficult terrain with "wet" effect (movement cost: 2.0)

### 2. Wall Tiles (Impassable)
Solid barriers that block movement and vision:

- **wall_stone_solid** - Indestructible dungeon wall
- **wall_stone_damaged** - Destructible (50 HP)
- **wall_wood_barricade** - Blocks movement but not vision (20 HP)

### 3. Door Tiles (Conditional Passability)
Barriers with state-based passability:

#### Standard Doors
- **door_wood_closed** - Requires "open" action, blocks vision
- **door_wood_open** - Passable, doesn't block vision
- **door_wood_locked** - Requires key or lock picking (DC 15), breakable (DC 18)

#### Reinforced Doors
- **door_iron_closed** - Heavy door, harder to break (DC 23, 40 HP)
- **door_portcullis_down** - Gate requiring strength check (DC 20) or break (DC 25, 50 HP)

#### Special Doors
- **door_secret_closed** - Hidden door requiring perception (DC 18) to discover

### 4. Container Tiles (Inventory Storage)
Interactive objects that store items:

#### Chests
- **container_chest_wood_closed** - 20 item capacity
- **container_chest_wood_locked** - Requires key or lock pick (DC 15)
- **container_chest_iron** - 30 item capacity, harder lock (DC 20)

#### Storage Objects
- **container_barrel** - 10 item capacity, breakable (10 HP)
- **container_crate** - 15 item capacity, breakable (8 HP)
- **container_sarcophagus** - 25 item capacity, requires strength check (DC 15), may trigger undead

### 5. Interactive Tiles (State & Triggers)
Objects that trigger events or change state:

- **interactive_lever** - Toggle mechanism (up/down states)
- **interactive_button** - Pressure plate (pressed/unpressed)
- **interactive_altar** - Accepts offerings, triggers events
- **interactive_fountain** - Healing fountain with limited charges (3 uses)
- **interactive_bookshelf** - Searchable with investigation check (DC 12), may contain secrets

### 6. Hazard Tiles (Environmental Dangers)
Tiles that deal damage or apply negative effects:

#### Traps
- **hazard_spike_trap** - Pressure-activated (2d6 piercing, DEX save DC 13)
- **hazard_pit_covered** - Hidden pit (2d6 falling, DEX save DC 15, Perception DC 15)

#### Environmental Hazards
- **hazard_fire_grate** - Hot metal grating (1d6 fire damage)
- **hazard_acid_pool** - Impassable acid (4d6 acid damage)
- **floor_lava** - Impassable molten rock (4d6 fire damage)

### 7. Transition Tiles
Tiles that smoothly blend between terrain types:

- **transition_stone_to_wood** - Stone to wooden floor transition
- **transition_stone_to_dirt** - Stone to dirt transition

### 8. Decoration Tiles (Atmosphere)
Non-interactive props for visual variety:

#### Structural
- **decoration_pillar** - Stone pillar (blocks movement and vision)
- **decoration_statue** - Carved statue (blocks movement and vision)

#### Light Sources
- **decoration_torch_wall** - Wall-mounted torch (light radius: 4 hexes)
- **decoration_brazier** - Standing brazier (light radius: 6 hexes)

#### Atmospheric
- **decoration_bones** - Scattered bones (passable)
- **decoration_web** - Cobwebs (passable, difficult terrain)

## Tile Properties

### Core Properties

#### Passability
- **passable** - Standard movement allowed (cost based on `movement_cost`)
- **difficult** - Movement costs extra (typically 2x normal)
- **conditional** - Passable only under certain conditions (keys, actions, states)
- **impassable** - Cannot be moved through

#### Movement Cost
Multiplier for movement point expenditure:
- `1.0` = Normal terrain (1 hex = 1 movement point)
- `2.0` = Difficult terrain (1 hex = 2 movement points)
- `null` = Impassable

#### Vision Blocking
- `blocks_vision: true` - Blocks line of sight for targeting and perception
- `blocks_vision: false` - Transparent to line of sight

### Interactive Properties

#### Destructible Objects
```json
{
  "destructible": true,
  "hp": 20,
  "dc_break": 18
}
```
- **hp** - Hit points before destruction
- **dc_break** - Strength check DC to break with single action

#### Container Objects
```json
{
  "has_inventory": true,
  "capacity": 20,
  "state": "locked",
  "dc_pick": 15
}
```
- **capacity** - Maximum number of items
- **state** - Current state (open/closed/locked)
- **dc_pick** - Thieves' Tools check DC to pick lock

#### State-Based Objects
```json
{
  "has_state": true,
  "state": "up",
  "state_options": ["up", "down"],
  "trigger_type": "door"
}
```
- **state** - Current state value
- **state_options** - Valid state transitions
- **trigger_type** - What this object affects (door/trap/event)

### Discovery & Interaction

#### Hidden Objects
```json
{
  "discovery_required": true,
  "dc_perception": 18,
  "disguised_as": "wall_stone_solid"
}
```
- **dc_perception** - Perception check DC to discover
- **disguised_as** - Tile ID used for initial display

#### Interaction Options
Available actions depend on tile category:
- **open/close** - Doors, containers
- **unlock/lock** - Doors, chests
- **pick_lock** - Locked objects (requires Thieves' Tools)
- **break** - Destructible objects
- **search** - Containers, bookshelves
- **loot** - Take items from containers
- **pull/push/toggle** - Levers, switches
- **press** - Buttons, pressure plates
- **examine** - Detailed inspection
- **read** - Books, scrolls, inscriptions
- **drink** - Fountains, potions
- **place_offering** - Altars, shrines
- **disable** - Traps (requires tools)

### Effects & Consequences

#### Status Effects
Applied when character enters or interacts with tile:
- **wet** - Character is soaked (may affect fire resistance)
- **fire_damage** - Ongoing fire damage per turn
- **acid_damage** - Acid corrosion damage
- **poison** - Poisoned condition
- **trap_triggered** - Activates trap mechanism
- **fall_damage** - Character falls (pit traps)
- **difficult_terrain** - Reduced movement
- **light_source** - Provides illumination

#### Damage Values
Format: `XdY damage_type`
- Example: `2d6 piercing` = Roll 2d6, apply piercing damage
- Associated with **save_type** and **save_dc** for mitigation

## Usage in Game Engine

### Tile Rendering
1. Load tile definition from JSON
2. Generate or retrieve cached tile image
3. Apply tile to hex coordinate on map
4. Store tile properties in dungeon state

### Movement Calculation
```php
$movement_cost = $tile['movement_cost'] ?? 1.0;
$required_points = 1 * $movement_cost; // 1 hex base cost
if ($character->movement_points >= $required_points) {
  $character->movement_points -= $required_points;
  $character->position = $target_hex;
  
  // Apply tile effects
  if (isset($tile['effects'])) {
    apply_tile_effects($character, $tile['effects']);
  }
}
```

### Interaction System
```php
function can_interact($tile, $action) {
  return in_array($action, $tile['interactions'] ?? []);
}

function perform_interaction($tile, $action, $character) {
  switch ($action) {
    case 'open':
      if ($tile['state'] === 'locked') {
        return ['success' => false, 'message' => 'Door is locked'];
      }
      $tile['state'] = 'open';
      $tile['passability'] = 'passable';
      return ['success' => true];
      
    case 'pick_lock':
      $roll = roll_skill_check($character, 'thieves_tools');
      if ($roll >= $tile['dc_pick']) {
        $tile['state'] = 'unlocked';
        return ['success' => true];
      }
      return ['success' => false, 'message' => 'Lock pick failed'];
      
    // ... other interactions
  }
}
```

### Vision & Line of Sight
```php
function blocks_line_of_sight($tile) {
  return $tile['blocks_vision'] ?? false;
}

function calculate_visible_hexes($character_pos, $dungeon_map) {
  $visible = [];
  foreach ($dungeon_map as $hex => $tile) {
    if (has_line_of_sight($character_pos, $hex, $dungeon_map)) {
      $visible[] = $hex;
    }
  }
  return $visible;
}
```

### Trap Detection & Disarm
```php
function detect_trap($tile, $character) {
  if (!isset($tile['dc_perception'])) return null;
  
  $roll = roll_skill_check($character, 'perception');
  if ($roll >= $tile['dc_perception']) {
    return [
      'detected' => true,
      'tile' => $tile,
      'dc_disable' => $tile['dc_disable'] ?? null
    ];
  }
  return ['detected' => false];
}

function disable_trap($tile, $character) {
  $roll = roll_skill_check($character, 'thieves_tools');
  if ($roll >= $tile['dc_disable']) {
    $tile['state'] = 'disabled';
    return ['success' => true];
  }
  
  // Failed disarm may trigger trap
  return ['success' => false, 'triggered' => true];
}
```

## Integration with Image Generation

Each tile can be generated using the terrain image generation service:

```php
$generator = \Drupal::service('dungeoncrawler_content.terrain_image_generator');

$tile_config = [
  'tile_id' => 'door_wood_closed',
  'category' => 'door',
  'terrain_type' => 'wood',
  'resolution' => 512,
  'aspect_ratio' => '1:1',
  'tileable' => false,
  'background' => 'opaque',
  'view' => 'top-down orthographic',
  'notes' => 'Wooden door, currently closed, medieval dungeon style'
];

$result = $generator->generateTerrainImage($tile_config, [
  'provider' => 'vertex',
  'persist' => true
]);
```

## Best Practices

### Tile Design
1. **Keep tiles mechanically consistent** - Same category tiles should have similar properties
2. **Use clear naming conventions** - `category_material_state` format
3. **Balance gameplay vs. realism** - Prioritize fun over simulation
4. **Provide visual feedback** - Tile appearance should match function

### Map Building
1. **Use transition tiles** - Blend between terrain types naturally
2. **Vary floor textures** - Mix clean/cracked/rubble for visual interest
3. **Strategic door placement** - Control flow and create chokepoints
4. **Balance hazards** - Don't overwhelm players with traps
5. **Light sources** - Place torches/braziers for atmosphere and gameplay

### Performance
1. **Cache tile images** - Generate once, reuse many times
2. **Batch tile generation** - Create full tilesets in single pass
3. **Preload common tiles** - Floor/wall tiles used most frequently
4. **Lazy load decorations** - Generate atmospheric tiles on demand

## Extending the System

### Adding New Tiles
1. Define tile in `standard-dungeon-tiles.json`
2. Add to appropriate category
3. Specify all required properties
4. Generate tile image using terrain service
5. Test in game engine

### Custom Properties
Add domain-specific properties as needed:
```json
{
  "tile_id": "custom_portal",
  "category": "interactive",
  "teleports_to": "dungeon_2_entrance",
  "requires_activation": true,
  "activation_items": ["portal_key"]
}
```

### Tile Variants
Create variations for different biomes:
- `floor_stone_clean` → Base tile
- `floor_stone_clean_frozen` → Ice cave variant
- `floor_stone_clean_corrupted` → Corrupted variant
- `floor_stone_clean_ancient` → Ruins variant

## See Also
- [Goblin Warren Tileset](goblin-warren-tileset.json) - Theme-specific implementation
- [Terrain Image Generation Service](../src/Service/TerrainImageGenerationService.php)
- [Dungeon Generation Engine](../src/Service/DungeonGenerationEngine.php)
