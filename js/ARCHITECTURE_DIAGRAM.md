# CharacterState Schema Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CLIENT-SIDE (Browser)                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │  character-state-service.ts                                  │   │
│  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   │   │
│  │  CharacterStateService                                       │   │
│  │                                                               │   │
│  │  - getState() → CharacterState                               │   │
│  │  - updateHitPoints(delta)                                    │   │
│  │  - addCondition(condition)                                   │   │
│  │  - castSpell(spellId, level)                                 │   │
│  └────────────────────┬────────────────────────────────────────┘   │
│                       │                                              │
│                       │ Uses TypeScript Interface                   │
│                       ▼                                              │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │  character-state.types.ts                                    │   │
│  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   │   │
│  │  interface CharacterState {                                  │   │
│  │    characterId: string                                       │   │
│  │    basicInfo: {                      ┌──────────────────┐    │   │
│  │      name: string                    │  camelCase       │    │   │
│  │      level: number                   │  Nested objects  │    │   │
│  │      experiencePoints: number        │  Full names      │    │   │
│  │    }                                 └──────────────────┘    │   │
│  │    abilities: {                                              │   │
│  │      strength: number                                        │   │
│  │      dexterity: number                                       │   │
│  │    }                                                          │   │
│  │    resources: {                                              │   │
│  │      hitPoints: {                                            │   │
│  │        current: number                                       │   │
│  │        max: number                                           │   │
│  │      }                                                        │   │
│  │    }                                                          │   │
│  │  }                                                            │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                       │
└───────────────────────────────┬───────────────────────────────────────┘
                                │
                                │ HTTP API (JSON)
                                │ GET /api/character/{id}/state
                                │ POST /api/character/{id}/update
                                │
┌───────────────────────────────▼───────────────────────────────────────┐
│                        SERVER-SIDE (PHP/Drupal)                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │  CharacterStateService.php                                   │   │
│  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   │   │
│  │  🔄 TRANSLATION LAYER                                        │   │
│  │                                                               │   │
│  │  public function getState($character_id) {                   │   │
│  │    // Read from database                                     │   │
│  │    $record = db->select('dc_campaign_characters')            │   │
│  │      ->fields(['id', 'name', 'level',                        │   │
│  │               'hp_current', 'hp_max',                        │   │
│  │               'character_data'])                             │   │
│  │      ->condition('id', $character_id)                        │   │
│  │      ->execute()->fetchObject();                             │   │
│  │                                                               │   │
│  │    $data = json_decode($record->character_data);             │   │
│  │                                                               │   │
│  │    // ⬇️ Translate: snake_case → camelCase                   │   │
│  │    return [                                                   │   │
│  │      'characterId' => $record->id,                           │   │
│  │      'basicInfo' => [                                        │   │
│  │        'name' => $record->name,        // Hot column         │   │
│  │        'level' => $record->level,      // Hot column         │   │
│  │        'experiencePoints' =>                                 │   │
│  │          $data['experience_points'],   // JSON               │   │
│  │      ],                                                       │   │
│  │      'abilities' => [                                        │   │
│  │        'strength' => $data['abilities']['str'],              │   │
│  │        'dexterity' => $data['abilities']['dex'],             │   │
│  │      ],                                                       │   │
│  │      'resources' => [                                        │   │
│  │        'hitPoints' => [                                      │   │
│  │          'current' => $record->hp_current,  // Hot column    │   │
│  │          'max' => $record->hp_max,          // Hot column    │   │
│  │        ]                                                      │   │
│  │      ]                                                        │   │
│  │    ];                                                         │   │
│  │  }                                                            │   │
│  │                                                               │   │
│  │  public function setState($id, array $state) {               │   │
│  │    // ⬆️ Translate: camelCase → snake_case                   │   │
│  │    // ⬆️ Update hot columns + JSON payload                   │   │
│  │  }                                                            │   │
│  └────────────────────┬────────────────────────────────────────┘   │
│                       │                                              │
│                       │ Reads/Writes                                 │
│                       ▼                                              │
└───────────────────────────────────────────────────────────────────────┘
                        │
┌───────────────────────▼───────────────────────────────────────────┐
│                      DATABASE (MySQL)                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  dc_campaign_characters                                  │   │
│  │  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   │   │
│  │                                                           │   │
│  │  🔥 HOT COLUMNS (Indexed for Fast Queries)               │   │
│  │  ┌──────────────────────────────────────────────────┐   │   │
│  │  │ id           INT PRIMARY KEY                       │   │   │
│  │  │ name         VARCHAR(255)     ← 🔥 Indexed         │   │   │
│  │  │ level        INT              ← 🔥 Indexed         │   │   │
│  │  │ ancestry     VARCHAR(64)      ← 🔥 Indexed         │   │   │
│  │  │ class        VARCHAR(64)      ← 🔥 Indexed         │   │   │
│  │  │ hp_current   INT              ← 🔥 Gameplay        │   │   │
│  │  │ hp_max       INT              ← 🔥 Gameplay        │   │   │
│  │  └──────────────────────────────────────────────────┘   │   │
│  │                                                           │   │
│  │  📦 JSON COLUMNS (Complete State)                         │   │
│  │  ┌──────────────────────────────────────────────────┐   │   │
│  │  │ character_data  TEXT (JSON)                        │   │   │
│  │  │ {                                                  │   │   │
│  │  │   "name": "Gandalf",              ┌─────────────┐ │   │   │
│  │  │   "level": 5,                     │ snake_case  │ │   │   │
│  │  │   "experience_points": 5000,      │ Flatter     │ │   │   │
│  │  │   "ancestry": "Human",            │ Abbreviated │ │   │   │
│  │  │   "class": "Wizard",              └─────────────┘ │   │   │
│  │  │   "abilities": {                                  │   │   │
│  │  │     "str": 10,                                    │   │   │
│  │  │     "dex": 14,                                    │   │   │
│  │  │     "con": 12,                                    │   │   │
│  │  │     "int": 18,                                    │   │   │
│  │  │     "wis": 13,                                    │   │   │
│  │  │     "cha": 10                                     │   │   │
│  │  │   },                                               │   │   │
│  │  │   "hit_points": {                                  │   │   │
│  │  │     "current": 45,  ← Synced with hp_current      │   │   │
│  │  │     "max": 50,      ← Synced with hp_max          │   │   │
│  │  │     "temp": 0                                      │   │   │
│  │  │   }                                                │   │   │
│  │  │ }                                                  │   │   │
│  │  │                                                    │   │   │
│  │  │ state_data  TEXT (JSON)                            │   │   │
│  │  │   Campaign-specific runtime state                 │   │   │
│  │  └──────────────────────────────────────────────────┘   │   │
│  │                                                           │   │
│  │  📋 VALIDATION                                            │   │
│  │  ┌──────────────────────────────────────────────────┐   │   │
│  │  │ character.schema.json validates character_data   │   │   │
│  │  │ - Ensures data integrity                          │   │   │
│  │  │ - Enforces field types                            │   │   │
│  │  │ - Required fields check                           │   │   │
│  │  └──────────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

KEY BENEFITS OF THREE-LAYER ARCHITECTURE:

🎯 TypeScript Layer (Client)
   ✅ Developer-friendly camelCase
   ✅ Type safety and autocomplete
   ✅ Nested structures for organization
   ✅ Full property names for clarity

🔄 PHP Service Layer (Translation)
   ✅ Transparent schema conversion
   ✅ Hot column synchronization
   ✅ Optimistic locking support
   ✅ Version control

⚡ Database Layer (Storage)
   ✅ Fast queries with hot columns
   ✅ Efficient indexes on common filters
   ✅ Complete state in JSON
   ✅ Schema validation

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

QUERY PERFORMANCE EXAMPLES:

Without hot columns:
  SELECT * FROM dc_campaign_characters
  WHERE JSON_EXTRACT(character_data, '$.level') >= 5;
  ❌ Slow: Must parse JSON for every row

With hot columns:
  SELECT * FROM dc_campaign_characters
  WHERE level >= 5;
  ✅ Fast: Uses index on level column

Complex query:
  SELECT name, class, level
  FROM dc_campaign_characters
  WHERE ancestry = 'Elf'
    AND level >= 5
    AND hp_current < hp_max * 0.5
  ORDER BY level DESC;
  ✅ Fast: All fields indexed or hot columns

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

## Field Naming Translation Examples

### Example 1: Basic Character Info

```typescript
// TypeScript (Client-side)
interface CharacterState {
  characterId: string;
  basicInfo: {
    name: string;
    level: number;
    experiencePoints: number;
  }
}
```

```json
// JSON Storage (Database character_data column)
{
  "name": "Gandalf",
  "level": 5,
  "experience_points": 5000
}
```

```sql
-- Database Hot Columns
CREATE TABLE dc_campaign_characters (
  id INT,
  name VARCHAR(255),      -- Hot column from basicInfo.name
  level INT,              -- Hot column from basicInfo.level
  character_data TEXT     -- Full JSON with experience_points
);
```

### Example 2: Abilities Translation

```typescript
// TypeScript (Client-side)
abilities: {
  strength: 18,
  dexterity: 14,
  constitution: 16,
  intelligence: 10,
  wisdom: 12,
  charisma: 8
}
```

```json
// JSON Storage (Database character_data column)
{
  "abilities": {
    "str": 18,
    "dex": 14,
    "con": 16,
    "int": 10,
    "wis": 12,
    "cha": 8
  }
}
```

### Example 3: Hit Points Synchronization

```typescript
// TypeScript (Client-side)
resources: {
  hitPoints: {
    current: 45,
    max: 50,
    temporary: 5
  }
}
```

```json
// JSON Storage (Database character_data column)
{
  "hit_points": {
    "current": 45,
    "max": 50,
    "temp": 5
  }
}
```

```sql
-- Database Columns (Hot + JSON)
hp_current = 45      -- Hot column for queries
hp_max = 50          -- Hot column for queries
character_data = '{"hit_points": {"current": 45, "max": 50, "temp": 5}}'
```

## Data Flow Diagrams

### READ Flow (GET /api/character/{id}/state)

```
Client                  PHP Service              Database
  │                         │                       │
  │  GET /api/character/1/state                     │
  ├────────────────────────>│                       │
  │                         │                       │
  │                         │  SELECT id, name, level, hp_current,
  │                         │         hp_max, character_data
  │                         │  FROM dc_campaign_characters
  │                         │  WHERE id = 1
  │                         ├──────────────────────>│
  │                         │                       │
  │                         │  ← {id: 1, name: "Gandalf", level: 5,
  │                         │     hp_current: 45, hp_max: 50,
  │                         │     character_data: '{"experience_points": 5000,
  │                         │                       "abilities": {"str": 18}}'}
  │                         │<──────────────────────┤
  │                         │                       │
  │                         │  🔄 TRANSLATE         │
  │                         │  snake_case → camelCase
  │                         │  Merge hot columns + JSON
  │                         │                       │
  │  ← 200 OK               │                       │
  │  {                      │                       │
  │    characterId: "1",    │                       │
  │    basicInfo: {         │                       │
  │      name: "Gandalf",   │  ← From hot column   │
  │      level: 5,          │  ← From hot column   │
  │      experiencePoints: 5000  ← From JSON       │
  │    },                   │                       │
  │    abilities: {         │                       │
  │      strength: 18       │  ← Translated from "str"
  │    },                   │                       │
  │    resources: {         │                       │
  │      hitPoints: {       │                       │
  │        current: 45,     │  ← From hot column   │
  │        max: 50          │  ← From hot column   │
  │      }                  │                       │
  │    }                    │                       │
  │  }                      │                       │
  │<────────────────────────┤                       │
  │                         │                       │
```

### WRITE Flow (POST /api/character/{id}/update)

```
Client                  PHP Service              Database
  │                         │                       │
  │  POST /api/character/1/update                  │
  │  {                      │                       │
  │    basicInfo: {         │                       │
  │      experiencePoints: 6000                    │
  │    },                   │                       │
  │    resources: {         │                       │
  │      hitPoints: {       │                       │
  │        current: 40      │                       │
  │      }                  │                       │
  │    }                    │                       │
  │  }                      │                       │
  ├────────────────────────>│                       │
  │                         │                       │
  │                         │  🔄 TRANSLATE         │
  │                         │  camelCase → snake_case
  │                         │  Extract hot column values
  │                         │                       │
  │                         │  UPDATE dc_campaign_characters
  │                         │  SET hp_current = 40,  -- Hot column
  │                         │      character_data = '{"experience_points": 6000,
  │                         │                          "hit_points": {
  │                         │                            "current": 40, ...}}'
  │                         │  WHERE id = 1
  │                         ├──────────────────────>│
  │                         │                       │
  │                         │  ✅ Updated            │
  │                         │<──────────────────────┤
  │                         │                       │
  │  ← 200 OK               │                       │
  │  { success: true }      │                       │
  │<────────────────────────┤                       │
  │                         │                       │
```

## Summary

This three-layer architecture provides:

1. **Developer Experience**: TypeScript developers work with idiomatic camelCase
2. **Performance**: Hot columns enable fast database queries
3. **Flexibility**: Complete state preserved in JSON
4. **Maintainability**: Translation logic centralized in PHP service
5. **Type Safety**: TypeScript interfaces catch errors at compile-time
6. **Backwards Compatibility**: Can evolve schema without breaking API

All layers work together seamlessly with the PHP service handling translation transparently.
