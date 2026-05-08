# Trap Content Migration Notes

## Schema Compliance Update (2026-02-17)

### Overview
The trap content files have been updated to comply with the `trap.schema.json` specification defined in `config/schemas/`. This ensures consistency, validation support, and proper integration with the game engine.

### Key Schema Changes

#### 1. Identifier Field: `content_id` → `trap_id`

**Change**: Human-readable identifiers replaced with UUIDs
- **Before**: `"content_id": "arrow_trap_simple"`
- **After**: `"trap_id": "61d7eb57-29cf-4483-aa4b-46e681f46827"`

**Rationale**: 
- The schema requires `trap_id` with UUID format for unique identification
- UUIDs prevent naming conflicts in multi-author content
- Supports proper relational data structures in the game engine

**Migration Path**:
- These content files are templates, not runtime instances
- No existing references found in codebase (verified 2026-02-17)
- If references are added in future, use trap loading service with ID mapping

#### 2. Trigger Structure: Object → String

**Change**: Structured trigger data simplified to description string
- **Before**: `{"type": "pressure_plate", "description": "A creature steps on..."}`
- **After**: `"A creature steps on the pressure plate."`

**Rationale**:
- Schema defines trigger as string type for narrative flexibility
- Machine-readable trigger types not currently used by game engine
- Keeps trap definitions focused on gameplay description

**Design Note**:
- If trigger type classification becomes needed (e.g., for immunity checks), consider:
  - Adding a separate `trigger_type` enum field to schema
  - Using standardized phrases in trigger strings
  - Implementing trigger parsing service

#### 3. Disable Structure: Flat → Skill-Specific DCs

**Change**: Single DC with skills list → Object with skill-specific DCs
- **Before**: `"disable_dc": 15, "disable_skills": ["Thievery"]`
- **After**: `"disable": {"thievery_dc": 15}`

**Rationale**:
- Pathfinder 2E traps can have multiple disable methods with different DCs
- Schema structure supports Athletics (breaking), Thievery (disabling), Arcana (dispelling), etc.
- Omit fields with no value (don't use null) for readability

#### 4. Effect Structure: Enhanced Detail

**Change**: Added damage_type, area, description fields
- Removed: `"type": "attack"`, `"target": "triggering creature"`, `"save": null`
- Added: `"damage_type": "piercing"`, `"area": {"type": "single_hex"}`, `"description": "..."`

**Rationale**:
- Damage type required for resistance/immunity calculations
- Area definition supports traps affecting multiple hexes
- Description provides narrative context for effects
- Omit null save fields when trap uses attack roll instead

#### 5. Reset Structure: Standardized Format

**Change**: Custom fields → Schema-compliant boolean + minutes
- **Before**: `{"type": "manual", "time": "10 minutes"}`
- **After**: `{"automatic": false, "reset_time_minutes": 10}`

**Rationale**:
- Boolean `automatic` flag clearer than type strings
- Numeric minutes enables time-based game mechanics
- Consistent with hazard.schema.json reset structure

### Removed Fields

The following fields were removed as they're not in the schema:
- `rarity`: Not applicable to traps (use level for challenge rating)
- `tags`: Redundant with type field and schema traits system
- `source`: Metadata not needed in content files
- `version`: Content versioning handled at schema level

### Added Fields (Optional but Recommended)

For complete trap functionality, these optional fields were added:

1. **Physical Properties**: `hardness`, `hp`, `broken_threshold`
   - Enables physical destruction of trap mechanisms
   - Values based on material and complexity

2. **State Flags**: `is_detected`, `is_disabled`, `is_triggered`
   - Runtime state tracking for trap instances
   - Default to false in template files

3. **Game Balance**: `xp_reward`
   - XP awarded for overcoming the trap
   - Calculated from trap level per PF2e rules

### Best Practices for Trap Content

1. **Use Schema Validation**: Validate all trap JSON files against `trap.schema.json`
2. **Omit Empty/Null Fields**: Only include fields with meaningful values
3. **Follow PF2e Rules**: Trap stats should match Pathfinder 2E guidelines
4. **Provide Clear Descriptions**: Trigger and effect descriptions guide GM/AI narration
5. **Set Appropriate XP**: Use PF2e XP tables for trap level

### Related Documentation

- Schema Definition: `config/schemas/trap.schema.json`
- Schema README: `config/schemas/README.md` (lines 45, 248-268)
- Hazard Schema: `config/schemas/hazard.schema.json` (similar structure)

### Questions or Issues

If the schema design doesn't support a specific trap mechanic:
1. Document the use case
2. Propose schema enhancement
3. Update schema version appropriately
4. Migrate existing content files
