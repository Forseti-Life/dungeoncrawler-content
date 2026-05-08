# Entity.js Refactoring Summary - DCC-0238

**Date**: 2026-02-18  
**Issue**: DCC-0238 - Review/refactor: js/ecs/Entity.js  
**Status**: Complete

## Problem Statement

Entity.js was reviewed for schema conformance against:
1. Database table `dc_campaign_characters` (hot columns for query optimization)
2. JSON schema `entity_instance.schema.json` (entity structure specification)
3. API documentation (entity lifecycle endpoints)

## Critical Issues Identified

1. **Hot Columns Not Exposed**: Database defines 7 hot columns for fast queries but Entity.js didn't expose them
2. **Type Field Missing**: No entity type field; database uses pc|npc|trap|hazard|obstacle
3. **Placement Not Modeled**: No support for placement metadata (room_id, hex coordinates)
4. **Metadata Gaps**: Missing instanceId, placement, and other schema fields
5. **Serialization Incompatibility**: toJSON/fromJSON couldn't roundtrip entity_instance format

## Solution (Minimal Changes)

### 1. Extended Constructor
Added optional `options` parameter (backward compatible):
```javascript
new Entity(id, {
  entityType: DatabaseEntityType.NPC,  // Optional: pc|npc|obstacle|trap|hazard
  instanceId: 'goblin-1',              // Optional: runtime instance ID
  placement: {                          // Optional: placement metadata
    roomId: 'room-1',
    q: 5,
    r: 3
  }
})
```

### 2. Hot Column Extraction
Enhanced `toJSON()` to extract hot columns from components:
```javascript
{
  "id": 1,
  "active": true,
  "components": { ... },
  "hotColumns": {
    "hp_current": 15,        // From StatsComponent.currentHp
    "hp_max": 20,            // From StatsComponent.maxHp
    "armor_class": 16,       // From StatsComponent.ac
    "experience_points": 0,  // From StatsComponent.experiencePoints
    "position_q": 5,         // From PositionComponent.q or placement.q
    "position_r": 3,         // From PositionComponent.r or placement.r
    "last_room_id": "room-1" // From placement.roomId
  }
}
```

**Strategy**:
- Component values take precedence over placement metadata
- Hot columns are derived data (not restored during fromJSON)
- Components remain the source of truth

### 3. Entity Type Validation
Added `DatabaseEntityType` enum and validation:
```javascript
export const DatabaseEntityType = {
  PLAYER_CHARACTER: 'pc',
  NPC: 'npc',
  OBSTACLE: 'obstacle',
  TRAP: 'trap',
  HAZARD: 'hazard'
};

// Legacy alias
export const EntityType = DatabaseEntityType;
```

**Validation**:
- Constructor throws error for invalid types
- Aligns with `dc_campaign_characters.type` column constraints
- Distinguished from `IdentityComponent.EntityType` (more granular)

### 4. Metadata Serialization
Enhanced `toJSON()` and `fromJSON()` to preserve metadata:
- `entityType`, `instanceId`, `placement` are now serialized
- Round-trip serialization maintains metadata
- Backward compatible (ignores unknown fields)

## Files Changed

1. **Entity.js** (142 → 291 lines, +149 lines)
   - Added optional constructor parameters
   - Implemented hot column extraction
   - Enhanced serialization/deserialization
   - Added type validation

2. **index.js** (+1 export)
   - Exported `DatabaseEntityType` and `EntityType` (alias)

3. **SCHEMA_MAPPING.md** (new, 309 lines)
   - Complete documentation of schema alignment
   - Hot column mapping
   - API payload examples
   - Backward compatibility guarantees
   - Future work scope

## Testing

Created comprehensive test suite (`/tmp/test-entity.js`):
- 10 tests covering all new functionality
- All tests pass (10/10)
- Tests verify:
  - Backward compatibility (old code still works)
  - Type validation (invalid types throw errors)
  - Metadata serialization (round-trip works)
  - Hot column extraction (from components and placement)
  - Component precedence (over placement)

## Backward Compatibility

✅ **100% Backward Compatible**
- Old code: `new Entity(1)` still works
- Existing serialization: Still produces valid output
- EntityManager: No changes required
- All existing functionality preserved

## Performance Impact

**Positive**:
- Hot columns enable faster database queries (indexed columns)
- No performance impact on existing code (optional features)

**Neutral**:
- Hot column extraction adds minimal CPU cost during toJSON()
- Small memory increase for metadata fields (optional, typically null)

## Schema Conformance Status

| Requirement | Status | Notes |
|-------------|--------|-------|
| Hot columns | ✅ Complete | All 7 hot columns extracted |
| Entity type | ✅ Complete | Validation and enum support |
| Placement | ✅ Complete | Metadata and hot column extraction |
| instanceId | ✅ Complete | Metadata field |
| Round-trip serialization | ✅ Complete | Metadata preserved |
| entity_instance partial | ✅ Complete | Core fields supported |
| Schema version | ⚠️ Deferred | Out of scope (future) |
| Inventory/state flags | ⚠️ Deferred | Out of scope (future) |
| Full entity_instance | ⚠️ Partial | Would require API changes |

## Future Work (Out of Scope)

Not implemented (would require breaking changes or API modifications):

1. **Full entity_instance.schema.json compliance**:
   - `entity_ref` structure (content_type, content_id, version)
   - `schema_version` tracking and migration
   - ISO 8601 timestamps (created_at, updated_at)
   - Structured `state` object separate from components
   - State flags (destroyed, disabled, hidden, collected)
   - Inventory array

2. **Advanced features**:
   - TypeScript definitions
   - JSON Schema validation
   - Component-to-state automatic mapping
   - Schema migration tools

## Recommendations

1. **Use DatabaseEntityType**: When creating entities with types, use the exported enum
2. **Include Placement**: For campaign entities, include placement metadata for hot columns
3. **Review Hot Columns**: When using entities with database persistence, check hotColumns in toJSON()
4. **Read SCHEMA_MAPPING.md**: For detailed mapping between Entity.js and database/API formats

## References

- **Source File**: `js/ecs/Entity.js`
- **Documentation**: `js/ecs/SCHEMA_MAPPING.md`
- **Database Schema**: `dungeoncrawler_content.install` (dc_campaign_characters)
- **API Docs**: `API_DOCUMENTATION.md` (Entity Lifecycle Endpoints)
- **Tests**: `/tmp/test-entity.js` (10 tests, all passing)

## Conclusion

Entity.js now bridges the gap between ECS component architecture and database schema requirements while maintaining full backward compatibility. The refactoring enables:

1. ✅ Database query optimization via hot columns
2. ✅ Schema alignment with dc_campaign_characters table
3. ✅ Type safety with validation
4. ✅ Metadata support for entity_instance schema
5. ✅ Comprehensive documentation

All existing code continues to work without modification.
