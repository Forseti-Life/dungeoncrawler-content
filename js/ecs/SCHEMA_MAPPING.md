# Entity.js Schema Mapping Documentation

**Last Updated**: 2026-02-18  
**Issue**: DCC-0238

## Overview

This document describes how `Entity.js` maps to database schemas and JSON structures used in the dungeon crawler system.

## Database Schema Alignment

### dc_campaign_characters Table

Entity.js now supports extraction of **hot columns** for database query optimization:

| Hot Column | Source | Description |
|------------|--------|-------------|
| `hp_current` | `StatsComponent.currentHp` | Current hit points |
| `hp_max` | `StatsComponent.maxHp` | Maximum hit points |
| `armor_class` | `StatsComponent.ac` | Armor class |
| `experience_points` | `StatsComponent.experiencePoints` | Experience points |
| `position_q` | `PositionComponent.q` or `placement.q` | Hex Q coordinate |
| `position_r` | `PositionComponent.r` or `placement.r` | Hex R coordinate |
| `last_room_id` | `placement.roomId` | Last known room ID |

**Hot Column Extraction**:
- Hot columns are extracted during `toJSON()` serialization
- Values from components take precedence over placement metadata
- Hot columns are included in `data.hotColumns` object
- During `fromJSON()`, hot columns are NOT restored (components are source of truth)

### Entity Types

Entity.js now validates entity types against the database schema using `DatabaseEntityType`:

```javascript
export const DatabaseEntityType = {
  PLAYER_CHARACTER: 'pc',    // Player character
  NPC: 'npc',                // Non-player character
  OBSTACLE: 'obstacle',      // Physical obstacle
  TRAP: 'trap',              // Trap
  HAZARD: 'hazard'           // Environmental hazard
};

// Legacy alias
export const EntityType = DatabaseEntityType;
```

**Note**: This differs from `IdentityComponent.EntityType` which uses more granular types (`player_character`, `creature`, `item`, `treasure`). `DatabaseEntityType` aligns specifically with the `dc_campaign_characters.type` column constraints.

**Usage**:
```javascript
import { Entity, DatabaseEntityType } from './Entity.js';
const entity = new Entity(1, { entityType: DatabaseEntityType.NPC });
```

**Validation**:
- Invalid entity types throw an error during construction
- Entity type is optional (defaults to `null` for generic entities)

## JSON Serialization Format

### Basic ECS Format (Backward Compatible)

```json
{
  "id": 1,
  "active": true,
  "components": {
    "StatsComponent": {
      "type": "StatsComponent",
      "abilities": { "strength": 10, "dexterity": 12, ... },
      "maxHp": 20,
      "currentHp": 15,
      "ac": 16,
      ...
    },
    "PositionComponent": {
      "type": "PositionComponent",
      "q": 5,
      "r": 3
    }
  }
}
```

### Extended Format (Schema Conformance)

```json
{
  "id": 1,
  "active": true,
  "entityType": "npc",
  "instanceId": "goblin-scout-1",
  "placement": {
    "roomId": "room-1",
    "q": 5,
    "r": 3
  },
  "components": { ... },
  "hotColumns": {
    "hp_current": 15,
    "hp_max": 20,
    "armor_class": 16,
    "position_q": 5,
    "position_r": 3,
    "last_room_id": "room-1"
  }
}
```

## API Payload Mapping

### Entity Spawn Endpoint

API expects (from API_DOCUMENTATION.md):
```json
{
  "type": "npc",
  "instanceId": "goblin-scout-1",
  "characterId": 123,
  "locationType": "room",
  "locationRef": "room-1",
  "stateData": {
    "hexId": "hex-5",
    "hp": 8,
    "maxHp": 8,
    "name": "Goblin Warrior"
  }
}
```

**Mapping to Entity.js**:
- `type` → `entityType` option
- `instanceId` → `instanceId` option
- `locationRef` → `placement.roomId`
- `stateData.hexId` → can be parsed to `placement.q/r`
- `stateData.hp` → `StatsComponent.currentHp`
- `stateData.maxHp` → `StatsComponent.maxHp`

**Example Construction**:
```javascript
import { Entity, EntityType } from './Entity.js';
import { StatsComponent } from './components/StatsComponent.js';
import { PositionComponent } from './components/PositionComponent.js';

// Create from API payload
const entity = new Entity(entityManager.nextEntityId, {
  entityType: payload.type,
  instanceId: payload.instanceId,
  placement: {
    roomId: payload.locationRef,
    q: parseHexId(payload.stateData.hexId).q,
    r: parseHexId(payload.stateData.hexId).r
  }
});

// Add components
entity.addComponent('StatsComponent', new StatsComponent({
  currentHp: payload.stateData.hp,
  maxHp: payload.stateData.maxHp
}));

entity.addComponent('PositionComponent', new PositionComponent(
  parseHexId(payload.stateData.hexId).q,
  parseHexId(payload.stateData.hexId).r
));
```

## entity_instance.schema.json Partial Compliance

Entity.js now supports a **subset** of entity_instance.schema.json fields:

| Schema Field | Entity.js Support | Notes |
|--------------|-------------------|-------|
| `entity_instance_id` | ✗ | Use `instanceId` metadata |
| `entity_type` | ✓ | Via `entityType` metadata (limited enum) |
| `entity_ref` | ✗ | Not implemented |
| `placement.room_id` | ✓ | Via `placement.roomId` |
| `placement.hex.q` | ✓ | Via `placement.q` or `PositionComponent.q` |
| `placement.hex.r` | ✓ | Via `placement.r` or `PositionComponent.r` |
| `placement.spawn_type` | ✗ | Not implemented |
| `state.active` | ✓ | Via `active` property |
| `state.hit_points` | ~ | Via `StatsComponent.currentHp/maxHp` |
| `state.inventory` | ✗ | Not implemented |
| `state.metadata` | ~ | Via component data |
| `schema_version` | ✗ | Not implemented |
| `created_at` | ✗ | Not implemented |
| `updated_at` | ✗ | Not implemented |

**Legend**:
- ✓ = Fully supported
- ~ = Partially supported / different structure
- ✗ = Not implemented

## Backward Compatibility

All changes are **backward compatible**:

1. **Constructor**: Optional `options` parameter defaults to `{}`
2. **toJSON()**: Additional fields don't break existing consumers
3. **fromJSON()**: Ignores unknown fields
4. **EntityManager**: No changes required (uses default constructor)

**Existing code continues to work**:
```javascript
// Old style - still works
const entity = new Entity(1);
entity.addComponent('StatsComponent', stats);
const json = entity.toJSON(); // Works, adds hotColumns automatically

// New style - with metadata
const entity = new Entity(1, { 
  entityType: EntityType.NPC,
  instanceId: 'goblin-1' 
});
```

## Future Work (Out of Scope)

The following are **not implemented** and would require API changes:

1. **Full entity_instance.schema.json compliance**:
   - `entity_ref` (content_type, content_id, version)
   - `schema_version` tracking
   - `created_at`/`updated_at` ISO timestamps
   - `state.inventory` array
   - `state.destroyed`, `state.disabled`, `state.hidden`, `state.collected` flags
   - `placement.spawn_type` enum

2. **Component-to-state mapping**:
   - Structured `state.hit_points` object separate from components
   - Automatic component → state object conversion
   - State validation against schema

3. **Migration support**:
   - Version-aware deserialization
   - Schema upgrade/downgrade logic
   - Data migration for breaking changes

4. **Type safety**:
   - TypeScript definitions
   - Runtime schema validation (JSON Schema)
   - Type guards for entity types

## Testing

See `/tmp/test-entity.js` for comprehensive tests covering:
- Backward compatibility
- Entity type validation
- Metadata serialization
- Hot column extraction
- Round-trip serialization

**Run tests**:
```bash
node /tmp/test-entity.js
```

All tests pass (10/10).

## References

- **Database Schema**: `dungeoncrawler_content.install` (dc_campaign_characters table)
- **API Documentation**: `API_DOCUMENTATION.md` (Entity Lifecycle Endpoints)
- **JSON Schema**: `config/schemas/entity_instance.schema.json`
- **ECS Architecture**: `HEXMAP_ARCHITECTURE.md` (Entity Lifecycle & Runtime Model)
