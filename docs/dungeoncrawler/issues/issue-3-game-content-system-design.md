# Issue #3: Game Content System - Design Document

## Table of Contents

1. [Overview](#overview)
2. [Architecture Goals](#architecture-goals)
3. [Data Flow Design](#data-flow-design)
   - High-Level Content Flow
   - Detailed Component Architecture
   - Encounter Generation Flow
   - Loot Generation Flow
4. [Database Architecture](#database-architecture)
   - content_registry Table
   - content_loot_tables Table
   - content_encounter_templates Table
5. [Service Layer Design](#service-layer-design)
   - ContentRegistry Service
   - ContentQuery Service
   - ContentGenerator Service
6. [Content File Structure](#content-file-structure)
7. [JSON Schema Definitions](#json-schema-definitions)
   - Creature Schema Example
   - Item Schema Example
   - Trap Schema Example
   - Loot Table Schema Example
   - Encounter Template Schema Example
8. [API Endpoints](#api-endpoints)
9. [Admin Interface Design](#admin-interface-design)
10. [Content Validation Rules](#content-validation-rules)
11. [Performance Considerations](#performance-considerations)
12. [Generation Algorithms](#generation-algorithms)
    - Encounter Generation Algorithm
    - Loot Table Roll Algorithm
    - Content Query Algorithm
    - Treasure Hoard Generation Algorithm
13. [Content Validation Schema](#content-validation-schema)
14. [Testing Scenarios](#testing-scenarios)
15. [Cross-References](#cross-references)
16. [Open Questions](#open-questions)
17. [Implementation Phases](#implementation-phases)

---

## Overview
Design the system for loading, managing, and accessing game content (creatures, items, traps) from JSON schemas into a queryable format for procedural generation and gameplay.

## Architecture Goals

1. **Schema-Driven**: All content defined in JSON schemas
2. **Lazy Loading**: Load content only when needed
3. **Caching**: Cache parsed content for performance
4. **Query Interface**: Filter/search content by criteria
5. **Validation**: Ensure content matches schemas
6. **Extensibility**: Easy to add new content types

## Data Flow Design

### High-Level Content Flow

```
JSON Schema Files
    ↓
ContentLoader Service
    ↓
Parse & Validate
    ↓
Cache in Memory/DB
    ↓
ContentQuery Service
    ↓
Filter by level, type, tags
    ↓
Return content for generation
```

### Detailed Component Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Content Sources                          │
├─────────────────────────────────────────────────────────────┤
│  creatures/*.json  │  items/*.json  │  traps/*.json         │
│  loot_tables/*.json │ encounter_templates/*.json            │
└──────────────┬──────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────┐
│              ContentRegistry Service                         │
├─────────────────────────────────────────────────────────────┤
│  • Import JSON files                                         │
│  • Validate against schemas                                  │
│  • Store in database                                         │
│  • Track versions                                            │
└──────────────┬──────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────┐
│                    Database Layer                            │
├─────────────────────────────────────────────────────────────┤
│  content_registry  │  loot_tables  │  encounter_templates   │
└──────────────┬──────────────────────────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────────────────────────┐
│                   Cache Layer (Redis)                        │
├─────────────────────────────────────────────────────────────┤
│  • Parsed content objects                                    │
│  • Query results                                             │
│  • Frequently accessed items                                 │
└──────────────┬──────────────────────────────────────────────┘
               │
               ├──────────────┬─────────────┬─────────────────┐
               ▼              ▼             ▼                 ▼
         ┌──────────┐  ┌──────────┐  ┌──────────┐   ┌──────────┐
         │ Content  │  │ Content  │  │ Content  │   │  Admin   │
         │  Query   │  │Generator │  │ Loot     │   │   API    │
         │ Service  │  │ Service  │  │ Service  │   │          │
         └────┬─────┘  └────┬─────┘  └────┬─────┘   └────┬─────┘
              │             │             │              │
              └─────────────┴─────────────┴──────────────┘
                                   │
                                   ▼
                    ┌──────────────────────────┐
                    │    Game Systems          │
                    ├──────────────────────────┤
                    │ • Encounter Generator    │
                    │ • Dungeon Generator      │
                    │ • Combat System          │
                    │ • Loot Distribution      │
                    └──────────────────────────┘
```

### Encounter Generation Flow

```
Party enters room
    ↓
DungeonGenerator.generateRoom()
    ↓
Determine room_type: 'combat', 'treasure', 'trap', 'empty'
    ↓
If combat:
    │
    ├─→ ContentGenerator.generateRoomContent()
    │       │
    │       ├─→ Calculate XP budget from party level
    │       │
    │       ├─→ ContentQuery.buildEncounterFromTemplate()
    │       │       │
    │       │       ├─→ Query encounter templates
    │       │       │
    │       │       ├─→ For each creature slot:
    │       │       │       │
    │       │       │       ├─→ ContentQuery.queryCreatures(filters)
    │       │       │       │       │
    │       │       │       │       └─→ Database query with level, tags
    │       │       │       │
    │       │       │       └─→ Select random creature from results
    │       │       │
    │       │       └─→ Return creature list
    │       │
    │       └─→ Return encounter data
    │
    └─→ Spawn creatures on hexmap
        │
        └─→ Initialize combat encounter
```

### Loot Generation Flow

```
Creature defeated
    ↓
Get creature.loot_table_id
    ↓
ContentQuery.rollLootTable(loot_table_id)
    │
    ├─→ Load loot table from database
    │
    ├─→ Roll for number of items (roll_count)
    │
    ├─→ For each roll:
    │       │
    │       ├─→ Calculate total weight
    │       │
    │       ├─→ Roll random number (1 to total_weight)
    │       │
    │       ├─→ Select entry based on weight
    │       │
    │       ├─→ If entry.item_id:
    │       │       └─→ Roll quantity dice
    │       │
    │       └─→ If entry.table_ref:
    │               └─→ Recursive rollLootTable()
    │
    ├─→ Consolidate duplicate items
    │
    └─→ Return loot items
        ↓
Add items to creature inventory
    ↓
Display loot to players
```

## Database Architecture

### content_registry Table
```sql
CREATE TABLE dungeoncrawler_content_registry (
  id INT PRIMARY KEY AUTO_INCREMENT,
  content_type VARCHAR(50) NOT NULL,     -- 'creature', 'item', 'trap', 'hazard'
  content_id VARCHAR(100) NOT NULL,      -- e.g. 'goblin_warrior'
  name VARCHAR(255) NOT NULL,            -- Display name
  level INT,                             -- Challenge level (for creatures/traps)
  rarity VARCHAR(20),                    -- 'common', 'uncommon', 'rare', 'unique'
  tags JSON,                             -- ['undead', 'goblinoid', 'small']
  schema_data JSON NOT NULL,             -- Full schema content
  source_file VARCHAR(255),              -- Path to JSON file
  version VARCHAR(20),                   -- Content version
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_content (content_type, content_id),
  INDEX idx_type_level (content_type, level),
  INDEX idx_rarity (rarity),
  INDEX idx_tags (tags) USING GIN       -- For tag searches
)
```

### content_loot_tables Table
```sql
CREATE TABLE dungeoncrawler_content_loot_tables (
  id INT PRIMARY KEY AUTO_INCREMENT,
  table_id VARCHAR(100) NOT NULL UNIQUE, -- 'goblin_common', 'treasure_hoard_5'
  name VARCHAR(255) NOT NULL,
  description TEXT,
  level_range VARCHAR(20),               -- '1-3', '4-7', etc
  entries JSON NOT NULL,                 -- Array of loot entries with weights
  /*
  entries structure:
  [
    {
      "item_id": "gold_piece",
      "quantity": "1d20",
      "weight": 50,
      "condition": ""
    },
    {
      "table_ref": "common_weapons",
      "weight": 30
    }
  ]
  */
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_level (level_range)
)
```

### content_encounter_templates Table
```sql
CREATE TABLE dungeoncrawler_content_encounter_templates (
  id INT PRIMARY KEY AUTO_INCREMENT,
  template_id VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  level INT NOT NULL,                    -- Average party level
  xp_budget INT NOT NULL,                -- Total XP for encounter
  threat_level VARCHAR(20),              -- 'trivial', 'low', 'moderate', 'severe', 'extreme'
  creature_slots JSON NOT NULL,          -- Array of creature requirements
  /*
  creature_slots structure:
  [
    {
      "quantity": 4,
      "level_offset": 0,
      "tags_required": ["goblinoid"],
      "tags_excluded": ["boss"],
      "role": "minion"
    },
    {
      "quantity": 1, 
      "level_offset": 2,
      "tags_required": ["goblinoid", "leader"],
      "role": "boss"
    }
  ]
  */
  environment_tags JSON,                 -- ['dungeon', 'underground', 'cramped']
  INDEX idx_level_threat (level, threat_level)
)
```

## Service Layer Design

### ContentRegistry Service
```php
class ContentRegistry {
  
  /**
   * Load all content from JSON files into database
   * Should be run during module installation/update
   * 
   * @param string|null $content_type - Load specific type or all
   * @return int - Number of items loaded
   */
  public function importContentFromJson(string $content_type = NULL): int
  
  /**
   * Get content by ID and type
   * 
   * @param string $content_type - 'creature', 'item', 'trap', 'hazard'
   * @param string $content_id - Unique identifier
   * @return array|null - Full schema data or null if not found
   */
  public function getContent(string $content_type, string $content_id): ?array
  
  /**
   * Validate content against schema
   * 
   * @param string $content_type
   * @param array $content_data
   * @return array - ['valid' => bool, 'errors' => array]
   */
  public function validateContent(string $content_type, array $content_data): array
  
  /**
   * Update content in registry
   * 
   * @param string $content_type
   * @param string $content_id
   * @param array $content_data
   * @return bool - Success
   */
  public function updateContent(string $content_type, string $content_id, array $content_data): bool
}
```

### ContentQuery Service
```php
class ContentQuery {
  
  /**
   * Query creatures by filters
   * 
   * @param array $filters
   *   - level_min: int
   *   - level_max: int
   *   - tags_include: array
   *   - tags_exclude: array
   *   - rarity: array
   *   - size: string
   *   - alignment: string
   * @param int $limit
   * @return array - Array of creature data
   */
  public function queryCreatures(array $filters, int $limit = 10): array
  
  /**
   * Query items by filters
   * 
   * @param array $filters
   *   - item_type: string ('weapon', 'armor', 'consumable', 'treasure')
   *   - level_min: int
   *   - level_max: int
   *   - tags: array
   *   - rarity: array
   * @param int $limit
   * @return array - Array of item data
   */
  public function queryItems(array $filters, int $limit = 10): array
  
  /**
   * Get random content matching criteria
   * 
   * @param string $content_type
   * @param array $filters
   * @param int $count - Number of items to return
   * @return array - Random selection
   */
  public function getRandomContent(string $content_type, array $filters, int $count = 1): array
  
  /**
   * Get loot table and roll for items
   * 
   * @param string $table_id
   * @return array - Array of rolled items with quantities
   *   [
   *     ['item_id' => 'gold_piece', 'quantity' => 15],
   *     ['item_id' => 'healing_potion', 'quantity' => 1]
   *   ]
   */
  public function rollLootTable(string $table_id): array
  
  /**
   * Build encounter from template
   * 
   * @param string $template_id
   * @param int $party_level
   * @return array - Complete encounter data
   *   [
   *     'creatures' => [...],
   *     'xp_total' => 120,
   *     'threat_level' => 'moderate'
   *   ]
   */
  public function buildEncounterFromTemplate(string $template_id, int $party_level): array
}
```

### ContentGenerator Service
```php
class ContentGenerator {
  
  /**
   * Generate appropriate content for dungeon level
   * 
   * @param int $dungeon_level
   * @param string $theme - 'goblin_warrens', 'undead_crypt', etc
   * @param string $room_type - 'combat', 'treasure', 'trap', 'empty'
   * @return array - Generated content
   *   [
   *     'creatures' => [...],
   *     'items' => [...],
   *     'traps' => [...],
   *     'hazards' => [...]
   *   ]
   */
  public function generateRoomContent(int $dungeon_level, string $theme, string $room_type): array
  
  /**
   * Populate creature with AI personality
   * 
   * @param array $creature_data - Base creature from schema
   * @return array - Creature with generated personality
   */
  public function generateCreaturePersonality(array $creature_data): array
  
  /**
   * Generate treasure hoard for level
   * 
   * @param int $level
   * @param string $hoard_type - 'minor', 'moderate', 'major'
   * @return array - Array of items
   */
  public function generateTreasureHoard(int $level, string $hoard_type): array
}
```

## Content File Structure

```
sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/
  content/
    creatures/
      goblinoids/
        goblin_warrior.json
        goblin_commando.json
        hobgoblin_general.json
      undead/
        skeleton.json
        zombie.json
        wraith.json
    items/
      weapons/
        longsword.json
        shortbow.json
      armor/
        leather_armor.json
        chainmail.json
      consumables/
        healing_potion.json
        antidote.json
      treasure/
        gold_piece.json
        silver_piece.json
        gem_ruby.json
    traps/
      arrow_trap.json
      pit_trap.json
      poison_needle.json
    hazards/
      toxic_mold.json
      unstable_floor.json
    loot_tables/
      goblin_common.json
      treasure_hoard_level_1.json
    encounter_templates/
      goblin_patrol_easy.json
      goblin_camp_moderate.json
  schemas/
    creature_schema.json
    item_schema.json
    trap_schema.json
    loot_table_schema.json
    encounter_template_schema.json
```

## JSON Schema Definitions

### Creature Schema Example

```json
{
  "content_id": "goblin_warrior",
  "name": "Goblin Warrior",
  "type": "creature",
  "level": 1,
  "rarity": "common",
  "size": "Small",
  "alignment": "CE",
  "tags": ["goblinoid", "humanoid", "minion"],
  "perception": {
    "modifier": 5,
    "senses": ["darkvision 60ft"]
  },
  "languages": ["Goblin"],
  "abilities": {
    "STR": 10,
    "DEX": 16,
    "CON": 12,
    "INT": 8,
    "WIS": 10,
    "CHA": 8
  },
  "stats": {
    "ac": 17,
    "hp": 16,
    "fortitude": 6,
    "reflex": 9,
    "will": 4
  },
  "speed": {
    "land": 25
  },
  "attacks": [
    {
      "name": "Dogslasher",
      "type": "melee",
      "bonus": 8,
      "damage": "1d6+2 slashing",
      "traits": ["agile", "backstabber", "finesse"]
    },
    {
      "name": "Shortbow",
      "type": "ranged",
      "range": "60ft",
      "bonus": 8,
      "damage": "1d6 piercing",
      "traits": ["deadly d10"]
    }
  ],
  "special_abilities": [
    {
      "name": "Goblin Scuttle",
      "type": "reaction",
      "trigger": "An ally ends a move action adjacent to the goblin",
      "effect": "The goblin Steps."
    }
  ],
  "ai_behavior": {
    "aggression": 0.7,
    "tactics": "skirmisher",
    "preferred_range": "ranged",
    "retreat_threshold": 0.3,
    "priority_targets": ["spellcaster", "healer"]
  },
  "loot_table": "goblin_common",
  "xp_value": 15,
  "description": "These small humanoids are cowardly but cunning raiders.",
  "source": "Pathfinder Bestiary 1"
}
```

### Item Schema Example

```json
{
  "content_id": "longsword_plus_1",
  "name": "+1 Longsword",
  "type": "item",
  "item_category": "weapon",
  "level": 2,
  "rarity": "common",
  "price": {
    "gold": 35
  },
  "bulk": 1,
  "hands": 1,
  "tags": ["magical", "martial", "sword"],
  "weapon_stats": {
    "damage": "1d8",
    "damage_type": "slashing",
    "traits": ["versatile P"],
    "group": "sword",
    "critical_specialization": true
  },
  "magical_properties": {
    "attack_bonus": 1,
    "damage_bonus": 1,
    "properties": []
  },
  "requirements": {
    "proficiency": "martial weapons"
  },
  "description": "This finely crafted longsword has a +1 potency rune etched into the blade.",
  "source": "Core Rulebook"
}
```

### Trap Schema Example

```json
{
  "content_id": "arrow_trap_simple",
  "name": "Simple Arrow Trap",
  "type": "trap",
  "level": 1,
  "rarity": "common",
  "tags": ["mechanical", "trap"],
  "stealth_dc": 15,
  "disable_dc": 15,
  "disable_skills": ["Thievery"],
  "trigger": {
    "type": "pressure_plate",
    "description": "A creature steps on the pressure plate."
  },
  "effect": {
    "type": "attack",
    "attack_bonus": 8,
    "target": "triggering creature",
    "damage": "1d8+2 piercing",
    "save": null
  },
  "reset": {
    "type": "manual",
    "time": "10 minutes"
  },
  "description": "A hidden pressure plate triggers a concealed arrow to fire.",
  "source": "Core Rulebook"
}
```

### Loot Table Schema Example

```json
{
  "table_id": "goblin_common",
  "name": "Goblin Common Loot",
  "description": "Standard loot from goblin warriors and scouts",
  "level_range": "1-2",
  "entries": [
    {
      "item_id": "gold_piece",
      "quantity_dice": "2d6",
      "weight": 40,
      "condition": null
    },
    {
      "item_id": "silver_piece", 
      "quantity_dice": "3d10",
      "weight": 30,
      "condition": null
    },
    {
      "item_id": "shortbow",
      "quantity_dice": "1",
      "weight": 15,
      "condition": "worn"
    },
    {
      "item_id": "leather_armor",
      "quantity_dice": "1",
      "weight": 10,
      "condition": "worn"
    },
    {
      "table_ref": "consumables_minor",
      "weight": 5,
      "condition": null
    }
  ],
  "roll_count": {
    "min": 1,
    "max": 3
  }
}
```

### Encounter Template Schema Example

```json
{
  "template_id": "goblin_patrol_moderate",
  "name": "Goblin Patrol (Moderate)",
  "description": "A patrol of goblin warriors led by a commando",
  "level": 1,
  "xp_budget": 80,
  "threat_level": "moderate",
  "creature_slots": [
    {
      "quantity": 3,
      "level_offset": 0,
      "tags_required": ["goblinoid"],
      "tags_excluded": ["boss", "elite"],
      "role": "minion",
      "suggested_ids": ["goblin_warrior"]
    },
    {
      "quantity": 1,
      "level_offset": 1,
      "tags_required": ["goblinoid"],
      "tags_excluded": ["boss"],
      "role": "leader",
      "suggested_ids": ["goblin_commando"]
    }
  ],
  "environment_tags": ["dungeon", "underground", "tunnel"],
  "setup": {
    "formation": "scattered",
    "distance_from_party": "30ft",
    "surprise_chance": 0.3
  },
  "tactics": {
    "initial_behavior": "aggressive",
    "morale": "medium",
    "retreat_condition": "half_casualties"
  }
}
```

## API Endpoints

### GET /api/content/query
```
Request:
{
  "content_type": "creature",
  "filters": {
    "level_min": 1,
    "level_max": 3,
    "tags_include": ["goblinoid"],
    "tags_exclude": ["boss"]
  },
  "limit": 10,
  "random": true
}

Response:
{
  "results": [
    {
      "content_id": "goblin_warrior",
      "name": "Goblin Warrior",
      "level": 1,
      "data": { /* full schema data */ }
    }
  ],
  "count": 5,
  "filters_applied": { /* echo filters */ }
}
```

### POST /api/content/generate-encounter
```
Request:
{
  "dungeon_level": 1,
  "party_level": 2,
  "party_size": 4,
  "theme": "goblin_warrens",
  "threat_level": "moderate"
}

Response:
{
  "encounter": {
    "xp_budget": 80,
    "threat_level": "moderate",
    "creatures": [
      {
        "creature_id": "goblin_warrior",
        "count": 3,
        "total_xp": 36
      },
      {
        "creature_id": "goblin_commando",
        "count": 1,
        "total_xp": 44
      }
    ]
  }
}
```

### POST /api/content/roll-loot
```
Request:
{
  "table_id": "goblin_common",
  "quantity": 1
}

Response:
{
  "items": [
    {
      "item_id": "gold_piece",
      "quantity": 12
    },
    {
      "item_id": "leather_armor",
      "quantity": 1,
      "condition": "worn"
    }
  ]
}
```

## Admin Interface Design

### Content Browser (/admin/content/dungeoncrawler/browse)
- Filterable list of all content
- Search by name, type, level, tags
- View/edit content
- Import new content from JSON
- Validate existing content

### Content Editor (/admin/content/dungeoncrawler/edit/{type}/{id})
- JSON editor with schema validation
- Preview rendered content
- Test queries/generation

### Loot Table Editor (/admin/content/dungeoncrawler/loot-tables)
- Manage loot tables
- Test table rolls (simulate drops)
- Weight balancing tools

## Content Validation Rules

1. **Creatures**:
   - Must have valid PF2e stat block
   - Level matches CR/difficulty
   - AI personality fields complete
   - Loot table references exist

2. **Items**:
   - Level appropriate for rarity
   - Valid item type
   - Stats match PF2e rules
   - Bulk calculations correct

3. **Traps**:
   - DC appropriate for level
   - Damage scaled correctly
   - Disable mechanics valid

## Performance Considerations

1. **Cache Strategy**:
   - Cache parsed JSON in memory
   - Invalidate on content update
   - Pre-load common queries

2. **Query Optimization**:
   - Index on level, type, tags
   - Limit result sets
   - Batch queries when possible

3. **Lazy Loading**:
   - Load full content only when needed
   - Store metadata for queries
   - Stream large result sets

## Generation Algorithms

### Encounter Generation Algorithm

```
Function: generateEncounter(party_level, party_size, threat_level, theme)

1. Calculate XP Budget
   xp_budget = calculateXPBudget(party_level, party_size, threat_level)
   
   XP Budget by Threat Level:
   - trivial:  XP = party_size * 10
   - low:      XP = party_size * 15
   - moderate: XP = party_size * 20
   - severe:   XP = party_size * 30
   - extreme:  XP = party_size * 40

2. Select Encounter Template (optional)
   template = queryTemplates({
     level: party_level ± 1,
     threat_level: threat_level,
     tags_include: [theme]
   })
   
   If template exists:
     Use template creature_slots
   Else:
     Generate creature_slots dynamically

3. Fill Creature Slots
   remaining_xp = xp_budget
   creatures = []
   
   For each slot in creature_slots:
     target_level = party_level + slot.level_offset
     filters = {
       level: target_level ± 1,
       tags_include: slot.tags_required,
       tags_exclude: slot.tags_excluded
     }
     
     candidate_creatures = queryCreatures(filters)
     selected = randomSelect(candidate_creatures)
     
     creature_xp = selected.xp_value * slot.quantity
     
     If creature_xp <= remaining_xp:
       creatures.push({
         creature: selected,
         quantity: slot.quantity
       })
       remaining_xp -= creature_xp

4. Adjust for XP Balance
   total_xp = sum(creatures.xp_value * quantity)
   
   If total_xp < xp_budget * 0.8:
     Add weak creatures (level < party_level)
   
   If total_xp > xp_budget * 1.2:
     Remove weakest creatures or reduce quantity

5. Return Encounter
   return {
     creatures: creatures,
     total_xp: total_xp,
     threat_level: threat_level,
     xp_budget: xp_budget
   }
```

### Loot Table Roll Algorithm

```
Function: rollLootTable(table_id, luck_modifier = 0)

1. Load Loot Table
   table = getLootTable(table_id)

2. Determine Number of Rolls
   If table.roll_count:
     num_rolls = random(table.roll_count.min, table.roll_count.max)
   Else:
     num_rolls = 1

3. Roll Each Entry
   loot_items = []
   
   For i = 1 to num_rolls:
     total_weight = sum(entry.weight for entry in table.entries)
     roll = random(1, total_weight) + luck_modifier
     
     current_weight = 0
     For entry in table.entries:
       current_weight += entry.weight
       
       If roll <= current_weight:
         If entry.item_id:
           quantity = rollDice(entry.quantity_dice)
           loot_items.push({
             item_id: entry.item_id,
             quantity: quantity,
             condition: entry.condition
           })
         
         Else if entry.table_ref:
           // Recursive table roll
           sub_items = rollLootTable(entry.table_ref)
           loot_items.push(...sub_items)
         
         break

4. Consolidate Duplicate Items
   consolidated = {}
   For item in loot_items:
     If consolidated[item.item_id]:
       consolidated[item.item_id].quantity += item.quantity
     Else:
       consolidated[item.item_id] = item
   
   return values(consolidated)
```

### Content Query Algorithm

```
Function: queryContent(content_type, filters, limit, random)

1. Build Base Query
   query = "SELECT * FROM content_registry WHERE content_type = ?"
   params = [content_type]

2. Apply Level Filters
   If filters.level_min:
     query += " AND level >= ?"
     params.push(filters.level_min)
   
   If filters.level_max:
     query += " AND level <= ?"
     params.push(filters.level_max)

3. Apply Rarity Filters
   If filters.rarity:
     query += " AND rarity IN (?)"
     params.push(filters.rarity)

4. Apply Tag Filters
   If filters.tags_include:
     For each tag in filters.tags_include:
       query += " AND JSON_CONTAINS(tags, ?)"
       params.push(JSON.stringify(tag))
   
   If filters.tags_exclude:
     For each tag in filters.tags_exclude:
       query += " AND NOT JSON_CONTAINS(tags, ?)"
       params.push(JSON.stringify(tag))

5. Apply Size/Alignment Filters (for creatures)
   If filters.size:
     query += " AND JSON_EXTRACT(schema_data, '$.size') = ?"
     params.push(filters.size)
   
   If filters.alignment:
     query += " AND JSON_EXTRACT(schema_data, '$.alignment') LIKE ?"
     params.push('%' + filters.alignment + '%')

6. Apply Ordering
   If random:
     query += " ORDER BY RAND()"
   Else:
     query += " ORDER BY level, name"

7. Apply Limit
   If limit:
     query += " LIMIT ?"
     params.push(limit)

8. Execute and Return
   results = database.execute(query, params)
   return results.map(row => parseContentData(row))
```

### Treasure Hoard Generation Algorithm

```
Function: generateTreasureHoard(level, hoard_type)

1. Determine Base Currency
   currency_table = {
     'minor': { gp: '1d10', sp: '2d20', cp: '5d20' },
     'moderate': { gp: '2d20', sp: '5d20', cp: '10d20', pp: '1d4' },
     'major': { gp: '5d20', sp: '10d20', pp: '1d10', gems: true }
   }
   
   base = currency_table[hoard_type]
   currency = {
     gp: rollDice(base.gp),
     sp: rollDice(base.sp),
     cp: rollDice(base.cp),
     pp: base.pp ? rollDice(base.pp) : 0
   }

2. Add Level-Appropriate Items
   item_budget = calculateItemBudget(level, hoard_type)
   /*
   item_budget calculation:
   - minor: 1 item of level
   - moderate: 2-3 items of level ± 1
   - major: 3-5 items of level ± 2, includes 1 rare item
   */
   
   items = []
   For i = 1 to item_budget.count:
     item_level = level + random(item_budget.level_min, item_budget.level_max)
     
     rarity_weights = hoard_type == 'major' ? 
       ['common': 50, 'uncommon': 30, 'rare': 20] :
       ['common': 70, 'uncommon': 30]
     
     rarity = weightedRandom(rarity_weights)
     
     item = getRandomContent('item', {
       level_min: item_level - 1,
       level_max: item_level + 1,
       rarity: [rarity]
     }, 1)[0]
     
     items.push(item)

3. Add Gems and Art Objects (for major hoards)
   If hoard_type == 'major':
     gem_count = random(1, 5)
     gems = queryItems({
       item_category: 'treasure',
       tags_include: ['gem', 'art'],
       level: level
     }, gem_count)
     
     items.push(...gems)

4. Return Hoard
   return {
     currency: currency,
     items: items,
     total_value_gp: calculateTotalValue(currency, items)
   }
```

## Content Validation Schema

### JSON Schema Validator Structure

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "definitions": {
    "creature": {
      "type": "object",
      "required": ["content_id", "name", "type", "level", "abilities", "stats"],
      "properties": {
        "content_id": {
          "type": "string",
          "pattern": "^[a-z0-9_]+$"
        },
        "name": {
          "type": "string",
          "minLength": 1
        },
        "type": {
          "const": "creature"
        },
        "level": {
          "type": "integer",
          "minimum": -1,
          "maximum": 25
        },
        "abilities": {
          "type": "object",
          "required": ["STR", "DEX", "CON", "INT", "WIS", "CHA"],
          "properties": {
            "STR": {"type": "integer", "minimum": 1, "maximum": 30},
            "DEX": {"type": "integer", "minimum": 1, "maximum": 30},
            "CON": {"type": "integer", "minimum": 1, "maximum": 30},
            "INT": {"type": "integer", "minimum": 1, "maximum": 30},
            "WIS": {"type": "integer", "minimum": 1, "maximum": 30},
            "CHA": {"type": "integer", "minimum": 1, "maximum": 30}
          }
        },
        "stats": {
          "type": "object",
          "required": ["ac", "hp", "fortitude", "reflex", "will"],
          "properties": {
            "ac": {"type": "integer", "minimum": 1},
            "hp": {"type": "integer", "minimum": 1},
            "fortitude": {"type": "integer"},
            "reflex": {"type": "integer"},
            "will": {"type": "integer"}
          }
        }
      }
    }
  }
}
```

### Validation Rules Reference

#### Creature Validation
- **Level**: Must be -1 to 25 (PF2e range)
- **Abilities**: All six ability scores 1-30
- **AC**: Typically 14-45 based on level
- **HP**: Must be > 0, typically 15-400 based on level and CON
- **Saves**: Should be within reasonable range for level
- **XP**: Must match PF2e XP chart for level
- **Attacks**: Bonus should be proficiency + level + modifier
- **Loot Table**: Must reference existing table or be null

#### Item Validation
- **Level**: Must be 0-25
- **Price**: Must match level in PF2e economy
- **Bulk**: 0-10 for most items (L = 0.1)
- **Weapon Damage**: Must follow weapon damage dice progression
- **Armor AC**: Must follow armor AC bonuses
- **Magical Bonus**: Must not exceed +3 (PF2e limit)
- **Rarity**: Common items level 0-20, uncommon/rare level varies

#### Trap Validation
- **Level**: Must be 0-25
- **DCs**: Must follow DC by Level table (14-50)
- **Damage**: Must scale appropriately with level
- **Disable Skills**: Must be valid PF2e skill names
- **Reset Type**: 'manual', 'automatic', or 'once'

## Testing Scenarios

### Unit Tests

1. **Content Loading**
   - Load 100 creatures from JSON files
   - Validate each creature against schema
   - Verify all required fields present
   - Check cross-references (loot tables)

2. **Query Performance**
   - Query creatures level 1-3 with "goblinoid" tag
   - Query items with multiple tag filters
   - Query with complex JSON path conditions
   - Benchmark query time (<100ms for basic queries)

3. **Generation**
   - Generate encounter for party of 4, level 2
   - Verify XP budget within 10% tolerance
   - Ensure all creatures match filters
   - Test edge cases (party level 1, level 20)

4. **Loot Tables**
   - Roll loot table 1000 times (distribution test)
   - Verify weights produce expected distribution
   - Test nested table references
   - Test quantity dice rolling

5. **Validation**
   - Validate all content against schemas
   - Test invalid content rejection
   - Test partial content updates
   - Verify error messages are helpful

6. **Performance**
   - Load 10,000+ content items
   - Query performance with large datasets
   - Cache hit rates
   - Memory usage monitoring

### Integration Tests

1. **End-to-End Encounter Generation**
   - Request encounter generation via API
   - Verify creatures are spawned on map
   - Verify loot drops after combat
   - Verify XP awards to party

2. **Content Updates**
   - Import new creature JSON
   - Update existing item stats
   - Verify cache invalidation
   - Verify content version tracking

3. **Admin Interface**
   - Create new content via UI
   - Edit existing content
   - Test validation feedback
   - Simulate loot table rolls

### Load Tests

1. **Concurrent Queries**
   - 100 simultaneous content queries
   - 50 simultaneous encounter generations
   - Database connection pooling
   - Response time under load

2. **Large Dataset**
   - 10,000 creatures loaded
   - 5,000 items loaded
   - 1,000 loot tables
   - Query performance maintained

## Cross-References

### Related Design Documents

- [Database Schema Design](../database-schema-design.md) - Core database architecture
- [Character Creation Process](../01-character-creation-process.md) - Character creation flow
- [Combat Encounter Mechanics](../02-combat-encounter-mechanics.md) - Combat system integration
- [Hex Map Rendering Design](./issue-2-hexmap-rendering-design.md) - Map integration for encounters

### Integration Points

1. **Character System**: Characters equip items, use abilities against creatures
2. **Combat System**: Encounters spawn creatures with stats from content
3. **Map System**: Creatures placed on hexmap, traps integrated with terrain
4. **Loot System**: Treasure generation from loot tables after combat
5. **AI System**: Creature AI behaviors from content definitions

## Open Questions

1. **Content Versioning**: How to handle content updates mid-campaign?
   - Option A: Lock content version per campaign
   - Option B: Apply updates globally with migration
   - Option C: Allow GMs to choose per-campaign

2. **Custom Content**: Should GMs be able to create custom creatures/items?
   - If yes, how to ensure balance?
   - Should custom content be shareable?

3. **Content Packs**: Support for community-created content packs?
   - Marketplace for verified content?
   - Moderation system?

4. **Localization**: Support for multiple languages?
   - Translate creature names and descriptions?
   - Store in separate language tables?

5. **Live Updates**: Push content updates to active sessions?
   - WebSocket notifications?
   - Require session restart?

## Implementation Phases

**Phase 1**: Database schema and ContentRegistry service
- Create content_registry, loot_tables, encounter_templates tables
- Implement ContentRegistry service with import/validate methods
- Set up JSON schema validators

**Phase 2**: ContentQuery service with basic filters
- Implement queryCreatures, queryItems methods
- Add level, tag, rarity filtering
- Optimize database indexes

**Phase 3**: Content import from JSON files
- Create initial creature/item/trap JSON files
- Implement batch import script
- Set up content directory structure

**Phase 4**: ContentGenerator service
- Implement encounter generation algorithm
- Implement loot table rolling
- Add treasure hoard generation

**Phase 5**: API endpoints
- Create REST API for content queries
- Add encounter generation endpoint
- Add loot rolling endpoint

**Phase 6**: Admin interface
- Build content browser UI
- Create content editor with validation
- Add loot table testing tools

**Phase 7**: Caching and optimization
- Implement Redis caching layer
- Add query result caching
- Performance testing and tuning

---

## Summary

This design document provides a comprehensive blueprint for the Game Content System, covering:

### Key Features
- **Schema-driven content**: All game content defined in validated JSON files
- **Flexible querying**: Filter content by level, type, tags, rarity, and custom attributes
- **Procedural generation**: Algorithms for encounters, loot, and treasure hoards
- **Extensibility**: Easy to add new content types and properties
- **Performance**: Caching and indexing strategies for fast queries

### Technical Approach
- **Database**: Hybrid relational + JSON storage for flexibility and performance
- **Services**: Three core services (Registry, Query, Generator) with clear responsibilities
- **Validation**: JSON Schema validation ensures content integrity
- **APIs**: RESTful endpoints for integration with game systems

### Content Types Supported
1. **Creatures**: Full PF2e stat blocks with AI behaviors
2. **Items**: Weapons, armor, consumables, and treasure
3. **Traps**: Mechanical and magical hazards
4. **Loot Tables**: Weighted random item generation
5. **Encounter Templates**: Pre-designed combat encounters

### Design Strengths
- Clear separation of concerns (loading, querying, generation)
- Comprehensive validation at multiple levels
- Performance optimization built-in from start
- Extensive testing strategy defined
- Well-documented APIs and data structures

### Next Steps
This document started as **DESIGN-ONLY**, but core implementation now exists for key service and schema components. Remaining work should focus on closing gaps between this target-state design and active runtime behavior. Next steps:
1. Review and approve this design
2. Reconcile documented target architecture with implemented services/tables
3. Complete missing advanced flows and integrations
4. Iterate on design based on implementation feedback

**Document Status**: ✅ Complete - Ready for review and implementation
