# CharacterState Schema Alignment Documentation

## Overview

This document explains the three-layer schema architecture for character data in the Dungeon Crawler module and how they align with each other.

## Schema Layers

### 1. TypeScript Interface (`character-state.types.ts`)
**Purpose**: Client-side type safety and API contract  
**Convention**: camelCase  
**Source of Truth**: Design-time interface for frontend consumption

The TypeScript `CharacterState` interface defines the structure of character data as consumed by client-side JavaScript. This is the **runtime API format** returned by PHP services.

### 2. JSON Schema (`character.schema.json`)
**Purpose**: Storage validation and database persistence  
**Convention**: snake_case  
**Source of Truth**: Storage-time schema for character_data column

The JSON schema validates data stored in the `dc_campaign_characters.character_data` JSON column. This is the **persistence format** used by the database.

### 3. Database Table Schema (`dungeoncrawler_content.install`)
**Purpose**: Relational storage with hot columns  
**Convention**: snake_case with hot columns  
**Source of Truth**: Physical database structure

The database includes both:
- **Hot columns**: Frequently-accessed fields (`name`, `level`, `ancestry`, `class`) for indexing and quick queries
- **JSON columns**: Full character data (`character_data`, `state_data`) for comprehensive storage

## Schema Translation

### PHP Service Layer (`CharacterStateService.php`)
The PHP `CharacterStateService` acts as the **translator** between storage and runtime formats:

```php
// Database (snake_case) → API (camelCase)
'basicInfo' => [
  'name' => $record->name,              // Hot column
  'level' => $record->level,            // Hot column
  'experiencePoints' => $data['experience_points'] ?? 0,
  'ancestry' => $record->ancestry,      // Hot column
  'class' => $record->class,            // Hot column
]
```

### TypeScript Service Layer (`character-state-service.ts`)
The TypeScript service consumes the **camelCase API format** and does not need to know about snake_case storage:

```typescript
interface CharacterState {
  characterId: string;
  basicInfo: {
    name: string;
    level: number;
    experiencePoints: number;
    ancestry: string;
    class: string;
  };
  // ... other fields
}
```

## Hot Column References

### Database Hot Columns
These fields are **denormalized** in `dc_campaign_characters` for performance:

**Core Identity** (indexed for filtering):
- `name` (varchar, indexed)
- `level` (int, indexed)
- `ancestry` (varchar, indexed)
- `class` (varchar, indexed)

**Combat/Gameplay** (high-frequency updates):
- `hp_current` (int, indexed)
- `hp_max` (int)
- `armor_class` (int)
- `experience_points` (int, indexed)

**Position Tracking** (ECS-managed):
- `position_q` (int) - Hex axial Q coordinate
- `position_r` (int) - Hex axial R coordinate
- `last_room_id` (varchar) - Most recent room location

**Note**: Position hot columns are managed by the ECS (Entity Component System) PositionComponent, 
not directly exposed in CharacterState TypeScript interface. See `js/ecs/components/PositionComponent.js`.

### Why Hot Columns?
Hot columns enable:
1. **Fast queries**: Filter characters without parsing JSON (`WHERE level >= 5`)
2. **Efficient indexing**: B-tree indexes on frequently-searched fields
3. **Join performance**: Reference character metadata from other tables

### Synchronization
When character state is saved:
1. PHP extracts `basicInfo` fields from the incoming camelCase payload
2. Updates both hot columns AND `character_data` JSON
3. Ensures consistency between denormalized columns and JSON

## Field Name Mapping

| TypeScript (API)          | JSON Schema (Storage)     | Hot Column (DB)   | Notes |
|---------------------------|---------------------------|-------------------|-------|
| `characterId`             | N/A                       | `id`              | Primary key |
| `basicInfo.name`          | `name`                    | `name`            | Hot column (indexed) |
| `basicInfo.level`         | `level`                   | `level`           | Hot column (indexed) |
| `basicInfo.experiencePoints` | `experience_points`    | `experience_points` | Hot column (indexed) |
| `basicInfo.ancestry`      | `ancestry`                | `ancestry`        | Hot column (indexed) |
| `basicInfo.class`         | `class`                   | `class`           | Hot column (indexed) |
| `abilities.strength`      | `abilities.str`           | N/A               | Full vs abbreviated |
| `abilities.dexterity`     | `abilities.dex`           | N/A               | Full vs abbreviated |
| `abilities.constitution`  | `abilities.con`           | N/A               | Full vs abbreviated |
| `abilities.intelligence`  | `abilities.int`           | N/A               | Full vs abbreviated |
| `abilities.wisdom`        | `abilities.wis`           | N/A               | Full vs abbreviated |
| `abilities.charisma`      | `abilities.cha`           | N/A               | Full vs abbreviated |
| `resources.hitPoints.current` | `hit_points.current`  | `hp_current`      | Hot column (indexed) |
| `resources.hitPoints.max` | `hit_points.max`          | `hp_max`          | Hot column |
| `defenses.armorClass.base` | `armor_class`            | `armor_class`     | Hot column |
| N/A (ECS PositionComponent) | `placement.hex.q`       | `position_q`      | Hot column (ECS-managed) |
| N/A (ECS PositionComponent) | `placement.hex.r`       | `position_r`      | Hot column (ECS-managed) |
| N/A (ECS metadata)        | `state_data.location.roomId` | `last_room_id` | Hot column (ECS-managed) |

**Note on Abilities**: The TypeScript interface uses full property names (strength, dexterity, etc.)
for better developer ergonomics, while the JSON schema uses traditional D&D abbreviations (str, dex, etc.).
The PHP service translates between these formats. In practice, the character_data JSON currently stores
the full names to match the TypeScript format, with the JSON schema accepting abbreviated input during
character creation.

**Note on Position Fields**: The position_q, position_r, and last_room_id hot columns are managed by 
the ECS (Entity Component System) through PositionComponent and are not directly exposed in the 
CharacterState TypeScript interface. These fields track runtime gameplay position and are used for 
spatial queries and entity management.

## Unified vs Hot-Column Structures

### Unified JSON Storage
The `character_data` JSON column stores the **complete** character sheet using snake_case:
```json
{
  "name": "Gandalf",
  "level": 5,
  "experience_points": 5000,
  "ancestry": "Human",
  "class": "Wizard",
  "hit_points": {
    "current": 45,
    "max": 50,
    "temp": 0
  }
}
```

### Hot Column Extraction
The database also maintains these fields as **separate columns**:
```sql
INSERT INTO dc_campaign_characters (
  name,           -- Hot column
  level,          -- Hot column
  ancestry,       -- Hot column
  class,          -- Hot column
  hp_current,     -- Hot column
  hp_max,         -- Hot column
  character_data  -- Full JSON payload
) VALUES (?, ?, ?, ?, ?, ?, ?);
```

### TypeScript Runtime Format
The client receives camelCase:
```typescript
{
  basicInfo: {
    name: "Gandalf",
    level: 5,
    experiencePoints: 5000,
    ancestry: "Human",
    class: "Wizard"
  },
  resources: {
    hitPoints: {
      current: 45,
      max: 50,
      temporary: 0
    }
  }
}
```

## Schema Conformance Guidelines

### For TypeScript Development
1. **Use camelCase**: All TypeScript interfaces use camelCase
2. **Trust the API**: PHP service provides correct camelCase format
3. **No manual conversion**: Never convert between snake_case and camelCase in TypeScript
4. **Type safety**: Rely on TypeScript interfaces for compile-time checks

### For PHP Development
1. **Convert on read**: Database snake_case → API camelCase
2. **Convert on write**: API camelCase → Database snake_case
3. **Sync hot columns**: Always update hot columns when persisting JSON
4. **Handle both formats**: Support legacy snake_case in JSON for backwards compatibility

### For Database Operations
1. **Index hot columns**: Ensure `name`, `level`, `ancestry`, `class` have indexes
2. **Denormalize consistently**: Hot columns must match JSON data
3. **Use hot columns for queries**: Filter/sort using columns, not JSON paths
4. **Preserve JSON**: Keep full JSON for comprehensive state

## Design Intent

The three-layer architecture serves different purposes:

1. **TypeScript** (Design layer): Defines the **developer-friendly** API contract
2. **JSON Schema** (Storage layer): Validates **persistent** data structure
3. **Database** (Performance layer): Optimizes **query** performance with hot columns

This separation allows:
- Frontend developers to work with idiomatic JavaScript (camelCase)
- Database operations to remain efficient (indexed hot columns)
- Data validation to enforce storage constraints (JSON schema)
- Future schema evolution without breaking API contracts

## References

- Design Document: `docs/dungeoncrawler/issues/issue-4-enhanced-character-sheet-design.md`
- TypeScript Types: `js/types/character-state.types.ts`
- JSON Schema: `config/schemas/character.schema.json`
- PHP Service: `src/Service/CharacterStateService.php`
- Database Schema: `dungeoncrawler_content.install`
- ECS Architecture: `js/ecs/index.js` (for position_q, position_r, last_room_id hot columns)
- Position Component: `js/ecs/components/PositionComponent.js`
