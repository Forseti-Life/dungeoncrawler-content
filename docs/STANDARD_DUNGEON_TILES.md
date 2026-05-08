# Standard Dungeon Tiles Reference

## Overview
This document defines the comprehensive set of standard dungeon tiles used across all dungeon themes in the Dungeon Crawler system. These tiles serve as the building blocks for procedurally generated and hand-crafted dungeons.

Tiles are categorized by:
- **Passability**: Whether characters can move through the tile
- **Interaction**: Whether the tile has interactive elements
- **Function**: Floor traversal, obstacles, hazards, etc.

---

## Tile Categories

### 1. FLOOR TILES (Passable - Base terrain)

**Clear Floor**
- tile_id: `floor_clear`
- description: Basic traversable floor, no obstacles
- passable: true
- interactive: false
- variants: stone, dirt, wood, metal
- hazard_level: none

**Damaged Floor**
- tile_id: `floor_damaged`
- description: Cracked or broken floor; slows movement slightly
- passable: true
- interactive: false
- variants: cracked_stone, broken_tiles, sunken, warped_wood
- hazard_level: low (difficult terrain)

**Slippery Floor** (wet/icy/oily)
- tile_id: `floor_slippery`
- description: Slick surface causing reduced traction
- passable: true
- interactive: false
- variants: wet_stone, icy, oil_slick, algae_covered
- hazard_level: medium (movement checks required)
- effects: reduced movement speed, falling prone more easily

**Grated/Metal Floor**
- tile_id: `floor_grated`
- description: Metal grating allowing sight/items to pass through below
- passable: true
- interactive: false
- variants: iron, steel, rusted, reinforced
- hazard_level: none (or low from noise)
- special: permits vision/missiles through to level below

**Raised Platform**
- tile_id: `floor_platform`
- description: Elevated floor section; requires climbing or jumping
- passable: true (with difficulty)
- interactive: true (climb interaction)
- variants: stone_dias, wooden_scaffold, metal_catwalk
- hazard_level: medium (falls possible)

**Lava/Magma Floor**
- tile_id: `floor_lava`
- description: Extreme hazard; causes damage over time
- passable: false (or true with immunity/protection)
- interactive: false
- variants: flowing, cooling, crusted
- hazard_level: extreme (lethal)
- effects: fire/heat damage per turn

---

### 2. WALL TILES (Impassable - Barriers)

**Standard Wall**
- tile_id: `wall_standard`
- description: Solid wall; completely impassable
- passable: false
- interactive: false
- variants: stone, brick, clay, reinforced
- sound_properties: solid, echoing
- destroyable: false (or breakable with high difficulty)

**Crumbling Wall**
- tile_id: `wall_crumbling`
- description: Deteriorating wall; may collapse
- passable: false
- interactive: true (push/demolish)
- variants: stone, brick, weathered_wood
- hazard_level: medium (collapse damage possible)
- destroyable: true (breakable/pushable)

**Ice Wall**
- tile_id: `wall_ice`
- description: Transparent frozen barrier
- passable: false
- interactive: true (melt)
- variants: clear, thick, magical
- hazard_level: low (cold damage touching)
- destroyable: true (fire/heat melts)

**Web Wall**
- tile_id: `wall_web`
- description: Dense webbing creating barrier
- passable: false (or slow)
- interactive: true (cut through)
- variants: normal, reinforced, poisoned
- hazard_level: low-medium
- destroyable: true (fire destroys)
- effects: possible poison damage

---

### 3. DOORWAY TILES (Transitions between spaces)

**Wooden Door - Closed**
- tile_id: `door_wooden_closed`
- description: Standard wooden door, locked or closed
- passable: false
- interactive: true (open/unlock/bash)
- variants: reinforced, ornate, weathered, barred
- interaction_types: [open, unlock, pick_lock, bash, burn]
- locked: true/false
- locked_difficulty: depends on lock quality

**Wooden Door - Open**
- tile_id: `door_wooden_open`
- description: Open wooden doorway
- passable: true
- interactive: false (or close)
- variants: (as above)

**Metal Door - Closed**
- tile_id: `door_metal_closed`
- description: Heavy metal door, highly secure
- passable: false
- interactive: true (open/unlock/bash)
- variants: steel, iron, reinforced, magical
- interaction_types: [open, unlock, pick_lock]
- locked: true (usually)
- locked_difficulty: very hard (or impossible without key)
- bash_resistance: very high

**Metal Door - Open**
- tile_id: `door_metal_open`
- description: Open metal doorway
- passable: true
- interactive: false (or close)

**Secret Door**
- tile_id: `door_secret`
- description: Hidden doorway blending with walls
- passable: false (until discovered)
- interactive: true (search, trigger)
- variants: seamless_stone, hidden_panel, illusory
- interaction_types: [search, detect_magic, trigger_mechanism]
- perception_dc: high (detect requires search/investigation)
- mechanism: mechanical, magical, or key-based

**Portcullis/Grate**
- tile_id: `door_portcullis`
- description: Vertical sliding metal gate/grate
- passable: false
- interactive: true (raise/lower)
- variants: iron, heavy, reinforced, trapped
- interaction_types: [raise, lower, bash]
- mechanical: lever-based, magical, or permanent

**Barred Window/Grate**
- tile_id: `door_barred`
- description: Metal bars blocking passage but allowing vision
- passable: false
- interactive: true (bend bars, squeeze through)
- variants: iron, steel, reinforced
- squeeze_difficulty: high (for small creatures)

---

### 4. INTERACTIVE OBJECTS - CONTAINERS/INVENTORY (Passable, interactive)

**Treasure Chest**
- tile_id: `object_chest`
- description: Container with valuable items; may be locked or trapped
- passable: true (can move around)
- interactive: true (open/loot)
- variants: locked, trapped, empty, ornate, simple
- interaction_types: [open, lock_pick, bash, examine, loot]
- inventory: holds items/treasure
- trap_possible: true
- locked: true/false

**Crate/Box**
- tile_id: `object_crate`
- description: Wooden container; may contain supplies or be climbable
- passable: true (can move around)
- interactive: true (open/search/move/climb)
- variants: wooden, reinforced, sealed, empty
- interaction_types: [open, search, bash, move, climb]
- inventory: may hold items
- climbable: true

**Barrel**
- tile_id: `object_barrel`
- description: Large cylindrical container
- passable: true (can move around)
- interactive: true (open/tap/examine/search)
- variants: wooden, metal, sealed, reinforced
- interaction_types: [open, tap, search, examine]
- contains: liquid, items, or empty
- flammable: true (wooden barrels)

**Table**
- tile_id: `object_table`
- description: Furniture for dining, work, or ritual
- passable: false (around it)
- interactive: true (search, eat, use)
- variants: wooden, stone, ornate, simple
- interaction_types: [search, examine, eat_from, use]
- inventory: may have items on surface
- climbable: true

**Shelf/Cabinet**
- tile_id: `object_shelf`
- description: Wall-mounted or standing storage
- passable: false (in front)
- interactive: true (search/retrieve)
- variants: wooden, stone, ornate, simple
- interaction_types: [search, retrieve, examine]
- inventory: holds items at heights

**Altar**
- tile_id: `object_altar`
- description: Religious or magical ceremonial platform
- passable: false (in front of)
- interactive: true (examine/pray/perform_ritual)
- variants: stone, ornate, simple, defaced
- interaction_types: [examine, pray, ritual_skill, desecrate]
- magical: true/false
- effects: possible buffs, curses, or magical reactions

**Cauldron**
- tile_id: `object_cauldron`
- description: Large pot for brewing or cooking
- passable: true (can move around)
- interactive: true (examine/taste/use)
- variants: iron, stone, magical, simple
- interaction_types: [examine, taste, use, drink]
- contains: liquid/potion or empty
- hazard: may contain poison or dangerous substances

**Weapon Rack/Armor Stand**
- tile_id: `object_weapon_rack`
- description: Display and storage for weapons and armor
- passable: true (can move around)
- interactive: true (remove/examine)
- variants: wooden, metal, ornate, simple
- interaction_types: [take_item, examine]
- inventory: holds weapons/armor

**Bookshelf**
- tile_id: `object_bookshelf`
- description: Storage for books, scrolls, and knowledge
- passable: false (in front)
- interactive: true (read/search/take)
- variants: wooden, stone, ornate, simple
- interaction_types: [read, search, take_book, examine]
- inventory: books/scrolls with lore/spells

---

### 5. INTERACTIVE OBJECTS - MECHANICAL (Passable, interactive, functional)

**Lever/Switch**
- tile_id: `object_lever`
- description: Mechanical control device
- passable: true (can walk past)
- interactive: true (pull/push/toggle)
- variants: stone, metal, simple, ornate
- interaction_types: [pull, push, toggle]
- affects: doors, bridges, traps, lights
- state: on/off or multiple positions

**Pressure Plate**
- tile_id: `object_pressure_plate`
- description: Sensor tile that triggers mechanisms
- passable: true (can walk on)
- interactive: true (step on)
- variants: stone, metal, hidden, obvious
- interaction_types: [step_on, examine, avoid]
- affects: doors, traps, lights
- reset_time: immediate, timed, or manual

**Rope/Chain**
- tile_id: `object_rope`
- description: Climbing or pulling tool
- passable: false (blocks path if slack)
- interactive: true (climb/pull)
- variants: rope, chain, magical, frayed
- interaction_types: [climb, pull, cut, swing]
- strength: breakable, unbreakable, magical

**Pulley/Winch**
- tile_id: `object_pulley`
- description: Mechanical lifting system
- passable: true
- interactive: true (use/operate)
- variants: wooden, metal, magical
- interaction_types: [operate, examine, pull_rope]
- affects: doors, platforms, drawbridges

**Torch/Light Source**
- tile_id: `object_torch`
- description: Fixed or portable light source
- passable: true
- interactive: true (light/extinguish/take)
- variants: torch, candle, magical_light, lantern
- interaction_types: [light, extinguish, take, examine]
- effects: illuminates area, may attract creatures

---

### 6. OBSTACLE OBJECTS - IMPASSABLE (Impassable, may be movable)

**Pillar/Column**
- tile_id: `obstacle_pillar`
- description: Structural support; cannot be easily moved
- passable: false
- interactive: true (climb/hide/examine)
- variants: stone, marble, reinforced, decorated
- interaction_types: [examine, climb, hide_behind]
- movable: false
- climbable: true

**Rubble Pile**
- tile_id: `obstacle_rubble`
- description: Scattered debris blocking passage
- passable: false
- interactive: true (climb/search/clear)
- variants: stone, brick, wood, mixed
- interaction_types: [climb, search, clear, examine]
- movable: true (requires strength/effort)
- climbable: true

**Large Rock/Boulder**
- tile_id: `obstacle_boulder`
- description: Impassable stone; may be pushable
- passable: false
- interactive: true (push/examine/climb)
- variants: smooth, jagged, embedded, uncut
- interaction_types: [push, examine, climb]
- movable: sometimes (requires strength check)
- climbable: true

**Statue/Sculpture**
- tile_id: `obstacle_statue`
- description: Art piece or symbolic object
- passable: false
- interactive: true (examine/interact/push)
- variants: stone, marble, metal, cursed
- interaction_types: [examine, push, search_base]
- movable: false (usually)
- magical: possible

---

### 7. HAZARD TILES (Passable but dangerous)

**Spike Trap - Floor**
- tile_id: `hazard_spike_floor`
- description: Sharp spikes projecting from floor
- passable: false (or with damage)
- interactive: true (dodge/trigger/examine)
- variants: iron, poisoned, retractable, hidden
- damage: piercing, poison possible
- trigger_type: pressure, time-based, always active
- difficulty: avoid (acrobatics/reflex check)

**Pit/Chasm**
- tile_id: `hazard_pit`
- description: Deep hole with no bottom visible
- passable: false (or jump across)
- interactive: true (examine/attempt_jump/climb_edge)
- variants: open, sharpened_bottom, spiked, filled_with_liquid
- damage: falling damage (varies by depth)
- difficulty: jump (athletics check) or climb wall
- special: may contain creatures/water

**Fire Hazard**
- tile_id: `hazard_fire`
- description: Flames or burning area
- passable: false (or with fire resistance)
- interactive: true (examine/approach/extinguish)
- variants: normal_fire, magical_fire, lava_touched, cold_fire
- damage: fire/heat damage per turn
- effects: ignites flammable objects
- extinguish_difficulty: varies

**Acid Pool**
- tile_id: `hazard_acid`
- description: Corrosive liquid hazard
- passable: false
- interactive: true (examine/avoid/attempt_jump)
- variants: sulfuric, magical, weak, concentrated
- damage: acid damage per turn; corrodes equipment
- effects: poisons atmosphere (fumes)
- difficulty: jump across

**Slime/Ooze**
- tile_id: `hazard_slime`
- description: Gelatinous or sticky substance
- passable: false (or slow)
- interactive: true (examine/attempt_pass/avoid)
- variants: green_slime, black_ooze, gelatinous, toxic
- damage: possible poison or acid damage
- effects: slows movement; may corrode equipment/flesh
- difficulty: escape difficulty check

**Poisonous Gas Cloud**
- tile_id: `hazard_gas`
- description: Noxious vapor covering area
- passable: false (or with holding breath)
- interactive: true (examine/enter_with_protection)
- variants: green_gas, yellow_fog, invisible, colored
- damage: poison damage per turn
- effects: requires breath-holding or mask
- difficulty: fortitude save or take damage

**Cursed Ground**
- tile_id: `hazard_cursed`
- description: Magically corrupted earth causing ill effects
- passable: true (but dangerous)
- interactive: true (examine/remove_curse)
- variants: necrotic, profane, cursed energies
- damage: necrotic damage or ability drain per turn
- effects: possible stat penalties or curses
- removal: dispel magic or specific rituals

---

### 8. CREATURE FEATURES (Environmental)

**Spider Web**
- tile_id: `feature_web`
- description: Dense webbing in corner/wall; may slow movement
- passable: true (slow)
- interactive: true (clear/burn)
- variants: normal, reinforced, poisoned
- effects: movement penalty; possible spider spawn
- hazard_level: low-medium

**Bones/Skeletal Remains**
- tile_id: `feature_bones`
- description: Skeleton or pile of bones
- passable: true
- interactive: true (examine/search)
- variants: scattered, arranged, intact_skeleton, articulated
- interaction_types: [examine, search, move]
- information: may contain clues or history

**Pool of Blood**
- tile_id: `feature_blood`
- description: Dried or fresh blood stain
- passable: true
- interactive: true (examine/investigate)
- variants: fresh, dried, arterial_spray
- interaction_types: [examine, investigate, detect_tracks]
- information: survival skill reveals age/details

**Corpse**
- tile_id: `feature_corpse`
- description: Dead body (recent or decomposed)
- passable: true (can move around)
- interactive: true (examine/search/bury)
- variants: fresh, decomposed, skeletal, zombie_host
- interaction_types: [examine, search, identify, loot]
- inventory: may have items/equipment
- disease_risk: decomposed bodies

---

### 9. DECORATIVE/ENVIRONMENTAL

**Chains**
- tile_id: `feature_chains`
- description: Metal or magical chains on walls
- passable: true
- interactive: true (examine/pull/break)
- variants: iron, magical, decorative, torturous
- interaction_types: [examine, pull, break, climb]
- possible: bound creature or item

**Prison Cell**
- tile_id: `feature_prison_cell`
- description: Barred enclosure for captives
- passable: false (barred)
- interactive: true (open/examine/search)
- variants: stone, metal, magical, equipped
- interaction_types: [examine, open, search]
- possible: imprisoned creature/NPC

**Staircase**
- tile_id: `feature_stairs`
- description: Change of elevation
- passable: true (movement cost higher)
- interactive: true (climb/descend)
- variants: stone, spiral, grand, simple
- direction: up, down, spiral
- destination: adjacent room or level below

**Ladder**
- tile_id: `feature_ladder`
- description: Climbable access to upper/lower level
- passable: false (must climb)
- interactive: true (climb/examine)
- variants: wooden, rope, metal, broken
- interaction_types: [climb, examine, repair]
- difficulty: simple (normal) or hard (broken)

**Bridge/Walkway**
- tile_id: `feature_bridge`
- description: Structure spanning gap
- passable: true
- interactive: true (cross/examine)
- variants: wooden, stone, rope, magical
- interaction_types: [cross, examine, destroy]
- hazard: may be unstable/breakable

**Alcove/Niche**
- tile_id: `feature_alcove`
- description: Recessed wall space, often containing statue/item
- passable: false (or true if shallow)
- interactive: true (examine/reach/hide)
- variants: shallow, deep, decorated, hidden
- interaction_types: [examine, search, hide_in]
- may contain: item, statue, hidden compartment

**Chandelier/Hanging Object**
- tile_id: `feature_chandelier`
- description: Suspended object that may fall
- passable: true (directly below)
- interactive: true (swing/cut/examine)
- variants: crystal, metal, enchanted, rusted
- interaction_types: [swing, cut_rope, examine]
- hazard: may fall and deal damage

---

## Tile Properties Matrix

### Common Properties for All Tiles

```
tile_id (string): Unique identifier
category (string): Category classification
description (string): Human-readable description
passable (boolean): Can creatures move through?
interactive (boolean): Can creatures interact?
movable (boolean): Can tile be moved/destroyed?
placeable (boolean): Can this tile be placed by spawner?
visual_variants (array): Available visual appearances
```

### Passability Types

| Type | Description | Traversal Cost |
|------|-------------|-----------------|
| impassable | Cannot move through | Infinite |
| passable | Normal traversal | 1 movement point |
| difficult | Slow terrain | 2 movement points |
| slow | Heavily restricted | 3+ movement points |
| blockable | Can be blocked by doors/gates | Variable |

### Interaction Types

| Type | Description | Consequences |
|------|-------------|--------------|
| open | Reveal hidden/access | May trigger trap |
| take | Pick up/remove item | Inventory affected |
| examine | Investigate closely | Perception check |
| use | Use mechanism/tool | Variable effects |
| destroy | Break/demolish | Item ruined/effect triggered |
| search | Thorough investigation | Investigation skill check |

---

## Tile Generation Rules

### Theme Variations
Each tile can be themed for different dungeon types:
- **Goblin Warren**: Natural stone, crude construction, organic elements
- **Dwarven Hold**: Finished stone, angular design, industrial feel
- **Elven Ruins**: Elegant materials, overgrown, magical atmosphere
- **Undead Crypt**: Bones, decay, necromantic imagery
- **Construct Factory**: Metal, geometric, magical constructs
- **Primordial Cave**: Raw stone, organic forms, geological features

### Difficulty Tiers
Tiles may scale in difficulty/complexity:
- **Basic**: Simple, common tiles
- **Standard**: Normal difficulty/complexity
- **Advanced**: Trapped, magical, or complex tiles
- **Hazardous**: Extreme danger
- **Legendary**: Unique or exceptionally dangerous

---

## Usage Guidelines

### Minimum Viable Dungeon
A basic dungeon requires:
- floor_clear (traversable base)
- wall_standard (barriers)
- door_wooden_closed (transitions)
- object_chest (rewards)
- obstacle_pillar (structure)

### Recommended Variety
For interesting dungeons, include:
- 2-3 floor types
- 2-3 wall types
- 3-4 doorway types
- 5-6 interactive objects
- 2-3 obstacles
- 2-3 hazards
- 2-3 environmental features

### Hazard Distribution
- 10-20% of non-wall tiles should be hazards
- Difficulty should increase toward dungeon center/depth
- Early areas: primarily floors, walls, doors, containers
- Mid areas: add obstacles, some hazards, interactive mechanics
- Deep areas: hazards dominate with occasional safe spots

