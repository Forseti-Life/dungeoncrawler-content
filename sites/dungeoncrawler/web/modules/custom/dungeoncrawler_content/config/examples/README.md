# Example Configuration Files

This directory contains example configuration files demonstrating the JSON schema structures used in the Dungeon Crawler Content module.

## Files

### templates/ (table-organized imports)
File-based template rows organized by destination table for object manager imports.

**Path:** `config/examples/templates/`

**Workflow:** Use the **Import templates** button on `/dungeoncrawler/objects` to import/update template rows.

**Current table directories:**
- `dungeoncrawler_content_registry/`
- `dungeoncrawler_content_loot_tables/`
- `dungeoncrawler_content_encounter_templates/`

**Format:** Each JSON file can contain a single row object, a list of row objects, or `{ "rows": [...] }`.

### tavern-obstacle-objects.json ✨ Enhanced
Obstacle object catalog defining reusable obstacle types (furniture, fixtures, etc.) with movement properties. These definitions are referenced by entity instances in dungeon levels.

**Schema:** `obstacle_object_catalog.schema.json`

**Usage:** Loaded by `HexMapController` to provide object definitions for obstacle entities.

**Quality Status:** ✅ Fully enhanced with optional enrichment fields (DCC-0005, 2026-02-17)

**Key Properties:**
- `movable`: Whether the object can be pushed/moved by players ✅ **Currently Used**
- `stackable`: Whether multiple instances can occupy the same hex ✅ **Currently Used**
- `movement.passable`: Whether entities can share the hex with the object ✅ **Currently Used**
- `movement.blocks_movement`: Whether pathfinding treats the hex as blocked ⏳ **Reserved for Future**
- `movement.cost_multiplier`: Movement cost when entering the hex (1 = normal, higher = slower) ⏳ **Reserved for Future**

**Optional Enrichment Fields (All Included):**
- `size`: PF2e size categories (small/medium/large) for spatial awareness ⭐ **Enhanced**
- `weight`: PF2e Bulk values for portability/strength checks ⭐ **Enhanced**
- `interaction`: Mechanics for doors and movable objects (can_open, athletics_dc) ⭐ **Enhanced**
- `visual`: Rendering metadata (sprite_id, color, rotation) ⭐ **Enhanced**

**Implementation Status:**
The current rendering code (`hexmap.js`) only uses `passable`, `movable`, and `stackable` to determine behavior. The `blocks_movement` and `cost_multiplier` properties are schema-required and reserved for future pathfinding AI implementation. Optional enrichment fields (size, weight, interaction, visual) are included for future feature compatibility and enhanced editor support.

**Cost Multiplier Guide:**
- `1.0`: Normal speed (open doors, passable obstacles)
- `1.5`: Slight impediment (stool stacks, light debris)
- `2.0-3.0`: Moderate impediment (movable tables, crates)
- `999`: Effectively impassable (fixed bar counters, walls)

**Enhancement Details (DCC-0005):**
This file has been enhanced with optional enrichment fields following the pattern demonstrated in `enhanced-obstacle-objects.json`. All 10 obstacle definitions now include:
- Proper PF2e size categories and Bulk values
- Interaction mechanics with Athletics DCs for movable objects
- Visual rendering hints with sprite IDs and colors
- Enhanced descriptions emphasizing combat implications (cover, hazards)
- "cover" tags added to tactically relevant objects

**Note:** All enhancements are backward compatible. Optional fields are additive only and do not affect existing functionality.

### tavern-entrance-dungeon.json
Complete dungeon level example featuring a tavern entrance area with bar, furniture, and transition to a dungeon room.

**Schema:** `dungeon_level.schema.json`

**Usage:** Demo dungeon level loaded by `HexMapController` when no campaign data is available.

### level-1-goblin-warrens.json ⭐ Best Practice Example
Multi-room dungeon level example with goblin-themed rooms, creatures, and environmental features. This file has undergone comprehensive three-phase refactoring and serves as a best-practice reference for creating dungeon levels.

**Schema:** `dungeon_level.schema.json`

**Quality Status:** ✅ Fully validated and production-ready (98/100 quality score)

**Features:**
- Complete PF2e-compliant creature stat blocks (7 creatures)
- Full item definitions with proper loot tables (13 items)
- Environmental hazards and traps with XP rewards
- Standardized field ordering and data types
- Consistent boolean state fields across all challenges
- Boss encounter with social interaction options
- Themed rooms with interconnected narrative
- Clean separation of game content and developer notes

**Documentation:** See `REVIEW_SUMMARY_DCC-0003.md` (Phase 1), `REFACTORING_SUMMARY_DCC-0003_PHASE2.md` (Phase 2), and `DCC-0003_PHASE3_COMPLETION_SUMMARY.md` (Phase 3) for detailed refactoring history and quality metrics.

### goblin-warren-tileset.json
Structured tileset definition for a base goblin warren dungeon. Intended as a generator ingest template for terrain, props, and effects. Schema pending.

## Schema Relationships

```
dungeon_level.schema.json
  ├── rooms[] (room.schema.json)
  │   ├── creatures[] (references creature definitions)
  │   ├── items[] (references item definitions)
  │   └── obstacles[] (references object catalog)
  └── entities[] (entity_instance.schema.json)
      └── entity_ref.content_id → obstacle_object_catalog.objects[].object_id
```

## Validation

All JSON files in this directory should validate against their respective schemas. The `$schema` property at the top of each file indicates which schema to use for validation.

## Quality Standards

The example files in this directory maintain high quality standards:

- **Schema Compliance**: All files must validate against their declared schemas
- **Reference Integrity**: All creature, item, and obstacle references must resolve
- **Data Consistency**: Use standardized field ordering and data types
- **Complete Definitions**: Include all required fields and proper XP rewards
- **Documentation**: Complex files should include refactoring notes

For detailed quality guidelines, see `level-1-goblin-warrens.json` as a reference implementation.

## Adding New Examples

1. Ensure your JSON validates against the appropriate schema
2. Include the `$schema` reference at the top of the file
3. Add descriptive documentation in this README
4. Follow existing naming conventions (lowercase with hyphens)
5. Verify all references resolve (creatures, items, obstacles)
6. Use consistent field ordering (see `level-1-goblin-warrens.json`)
7. Include XP rewards for all challenges (creatures, traps, hazards)

## See Also

- `/config/schemas/README.md` - Complete schema documentation
- `API_DOCUMENTATION.md` - Runtime API documentation
- `ARCHITECTURE.md` - System architecture overview
