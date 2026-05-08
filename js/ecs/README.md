# Entity Component System (ECS) - Dungeon Crawler

This directory contains the Entity Component System (ECS) implementation for the Dungeon Crawler game, providing a flexible architecture for managing game objects (entities) with data components and logic systems.

## Overview

The ECS architecture separates game objects into three main concepts:

- **Entities**: Containers with unique IDs that hold components
- **Components**: Pure data structures (no logic)
- **Systems**: Logic that operates on entities with specific components

## Schema Conformance (DCC-0238)

As of the DCC-0238 review/refactor, the ECS implementation now fully supports bidirectional conversion with the `entity_instance.schema.json` format used for database persistence and API communication.

### Dual Format Support

The Entity class now supports **two formats**:

1. **ECS Format** (default, backward compatible)
   - Numeric entity IDs
   - Component-based data storage
   - Used for runtime game logic

2. **entity_instance Format** (schema-conformant)
   - UUID-based entity_instance_id
   - Structured state object (active, destroyed, hit_points, etc.)
   - entity_ref links to content registry
   - placement with room_id and hex coordinates
   - Used for database persistence and API communication

## Quick Start

### Basic ECS Usage (Backward Compatible)

```javascript
import { Entity, EntityManager, Component } from './ecs/index.js';

// Create entity manager
const manager = new EntityManager();

// Create entity with numeric ID
const entity = manager.createEntity();

// Add components
entity.addComponent('HealthComponent', {
  currentHp: 100,
  maxHp: 100
});

entity.addComponent('PositionComponent', {
  q: 5,
  r: 3,
  room_id: 'room-123'
});

// Query entities
const entitiesWithHealth = manager.getEntitiesWith('HealthComponent');
```

### Using entity_instance.schema.json Format

```javascript
import { Entity, EntityInstanceMapper } from './ecs/index.js';

// Create entity with schema properties
const entity = new Entity(1, {
  entity_instance_id: '550e8400-e29b-41d4-a716-446655440000',
  entity_type: 'creature',
  entity_ref: {
    content_type: 'creature',
    content_id: 'goblin_warrior_001',
    version: '1.0.0'
  },
  placement: {
    room_id: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    hex: { q: 2, r: -1 },
    spawn_type: 'respawning'
  }
});

// Add components
entity.addComponent('HealthComponent', {
  currentHp: 12,
  maxHp: 16
});

// Serialize to entity_instance format
const instanceData = entity.toJSON('entity_instance');
// Result conforms to entity_instance.schema.json

// Validate against schema
const validation = EntityInstanceMapper.validate(instanceData);
if (!validation.valid) {
  console.error('Validation errors:', validation.errors);
}
```

### Converting Between Formats

```javascript
import { Entity, EntityInstanceMapper } from './ecs/index.js';

// Load entity_instance data from API/database
const instanceData = {
  entity_instance_id: 'uuid-123',
  entity_type: 'creature',
  entity_ref: {
    content_type: 'creature',
    content_id: 'goblin_001'
  },
  placement: {
    room_id: 'room-123',
    hex: { q: 2, r: 3 }
  },
  state: {
    active: true,
    hit_points: { current: 10, max: 15 },
    inventory: []
  }
};

// Convert to Entity (automatically detects format)
const entity = Entity.fromJSON(instanceData);

// Entity now has components:
// - HealthComponent with currentHp/maxHp from state.hit_points
// - PositionComponent with q/r from placement.hex
// - InventoryComponent with items from state.inventory
// - StateComponent with destroyed/disabled/hidden flags

// Later, convert back to entity_instance format
const backToInstance = entity.toJSON('entity_instance');
```

### Batch Operations

```javascript
import { EntityManager, EntityInstanceMapper } from './ecs/index.js';

// Load multiple entities from API
const instanceArray = [
  { entity_instance_id: 'uuid-1', /* ... */ },
  { entity_instance_id: 'uuid-2', /* ... */ },
  { entity_instance_id: 'uuid-3', /* ... */ }
];

// Convert all to Entity objects
const entities = EntityInstanceMapper.fromEntityInstanceArray(instanceArray);

// Add to entity manager
const manager = new EntityManager();
entities.forEach(entity => {
  manager.entities.set(entity.id, entity);
});

// Later, serialize all for API/database
const instancesForSave = manager.toJSON('entity_instance');
// Returns array of entity_instance objects
```

## Architecture Details

### Entity Class

**Location**: `Entity.js`

The Entity class represents a game object with a unique ID and attached components.

**Constructor Signature**:
```javascript
new Entity(id, options = {})
```

**Parameters**:
- `id` (number): Positive integer for ECS compatibility
- `options.entity_instance_id` (string): UUID for schema conformance
- `options.entity_type` (string): 'creature', 'item', or 'obstacle'
- `options.entity_ref` (object): Link to content registry
- `options.placement` (object): Room and hex coordinates

**Methods**:
- `addComponent(name, data)`: Add component to entity
- `getComponent(name)`: Get component by name
- `hasComponent(name)`: Check if component exists
- `removeComponent(name)`: Remove component
- `toJSON(format)`: Serialize ('ecs' or 'entity_instance')
- `static fromJSON(data, classes)`: Deserialize (auto-detects format)

### EntityManager Class

**Location**: `EntityManager.js`

Manages all entities and provides query functionality.

**Methods**:
- `createEntity()`: Create new entity with auto-incremented ID
- `getEntity(id)`: Get entity by ID
- `removeEntity(id)`: Remove entity
- `getAllEntities()`: Get all active entities
- `getEntitiesWith(...components)`: Query by components (cached)
- `toJSON(format)`: Serialize all entities
- `fromJSON(data, classes)`: Load entities (supports both formats)

### EntityInstanceMapper Utility

**Location**: `EntityInstanceMapper.js`

Utility class for format conversion and validation.

**Static Methods**:
- `toEntityInstance(entity, overrides)`: Convert Entity to entity_instance
- `fromEntityInstance(instanceData, classes)`: Convert entity_instance to Entity
- `toEntityInstanceArray(entities)`: Batch convert to entity_instance
- `fromEntityInstanceArray(instances, classes)`: Batch convert to Entity
- `validate(instanceData)`: Validate against schema
- `createTestInstance(overrides)`: Generate test data

### Component Base Class

**Location**: `Component.js`

Base class for all components. Components are pure data containers.

**Methods**:
- `toJSON()`: Serialize component
- `static fromJSON(data)`: Deserialize component
- `clone()`: Deep clone component
- `validate()`: Validate component data (override in subclasses)

### System Base Class

**Location**: `System.js`

Base class for systems that process entities with specific components.

**Methods**:
- `update(deltaTime)`: Called each frame
- `init()`: Initialize system
- `destroy()`: Cleanup system
- `isEnabled()`: Check if system is active

## Format Detection

The `Entity.fromJSON()` method automatically detects the format:

- **entity_instance format**: Has `entity_instance_id`, `entity_type`, and `entity_ref`
- **ECS format**: Has numeric `id` field

This allows seamless loading from both database (entity_instance) and ECS save files.

## Component to State Mapping

When converting to entity_instance format, components are mapped to state properties:

| Component | entity_instance.state Property |
|-----------|-------------------------------|
| HealthComponent | state.hit_points |
| InventoryComponent | state.inventory |
| StateComponent | state.destroyed, state.disabled, state.hidden, state.collected |
| MetadataComponent | state.metadata |
| PositionComponent | placement.hex (q, r) |

## Validation

Use `EntityInstanceMapper.validate()` to check conformance with entity_instance.schema.json:

```javascript
const validation = EntityInstanceMapper.validate(instanceData);

if (validation.valid) {
  // Data is valid
} else {
  // Check errors
  validation.errors.forEach(error => console.error(error));
}
```

**Validates**:
- Required fields (entity_instance_id, entity_type, entity_ref, placement, state, schema_version)
- entity_type enum ('creature', 'item', 'obstacle')
- entity_ref structure (content_type, content_id)
- placement structure (room_id, hex with q/r integers)
- state.active boolean
- schema_version semver format

## Testing

Generate valid test instances for development:

```javascript
const testInstance = EntityInstanceMapper.createTestInstance({
  entity_type: 'item',
  entity_ref: {
    content_type: 'item',
    content_id: 'healing_potion'
  },
  placement: {
    hex: { q: 5, r: 3 }
  },
  state: {
    hit_points: { current: 1, max: 1 }
  }
});
```

## Backward Compatibility

All existing ECS code continues to work without modification:

- Default `toJSON()` returns ECS format
- Constructor without options works as before
- EntityManager serialization defaults to ECS format
- Component-based workflow unchanged

## Database Integration

When persisting to database tables like `combat_participants`:

```javascript
// Load entity_instance from database
const entity = Entity.fromJSON(dbRow.entity_data);

// Work with entity using components
const health = entity.getComponent('HealthComponent');
health.currentHp -= damage;

// Save back to database
const updatedData = entity.toJSON('entity_instance');
await saveToDatabase(entity.entity_instance_id, updatedData);
```

## Performance Considerations

- **UUID to ID Hashing**: Converting UUID to numeric ID uses simple hash (may have collisions in large datasets)
- **Query Caching**: EntityManager caches component queries for performance
- **Format Detection**: Minimal overhead (checks 3 property names)
- **JSON Serialization**: Uses deep cloning for safety (can be slow for large component trees)

## Future Improvements

Potential enhancements identified in DCC-0238 review:

1. **Database Schema**: Change `combat_participants.entity_ref` from VARCHAR to JSON
2. **Persistent ID Mapping**: Store UUID→ID mapping to prevent hash collisions
3. **Partial Updates**: Support updating only changed state properties
4. **Schema Versioning**: Handle migration between schema versions
5. **Type Safety**: Add TypeScript definitions for better IDE support

## Related Files

- **Schema**: `config/schemas/entity_instance.schema.json`
- **Database**: `dungeoncrawler_content.install` (table definitions)
- **Tests**: `tests/src/Functional/EntityLifecycleTest.php`
- **Usage**: `js/hexmap.js` (primary consumer)

## References

- Entity Component System pattern: https://en.wikipedia.org/wiki/Entity_component_system
- entity_instance.schema.json specification: `/config/schemas/README.md`
- DCC-0238 Issue: Review/refactor Entity.js for schema conformance
