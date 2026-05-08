# JSON Schema Directory

This directory contains JSON Schema definitions for all data structures used in the Dungeon Crawler Content module for Pathfinder 2E.

## Table of Contents

- [Purpose](#purpose)
- [Quick Reference](#quick-reference)
- [Schema Categories](#schema-categories)
  - [Character Schemas](#character-schemas)
  - [Dungeon Schemas](#dungeon-schemas)
- [Schema Standards](#schema-standards)
- [Usage](#usage)
- [Maintenance](#maintenance)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [References](#references)
- [Contributing](#contributing)

## Purpose

JSON Schemas serve multiple purposes:
- **Documentation**: Clear, machine-readable specifications of data structures
- **Validation**: Ensures data integrity when creating/updating records
- **Type Safety**: Provides contract for frontend-backend communication
- **IDE Support**: Enables autocomplete and validation in editors
- **Testing**: Facilitates automated testing of data structures

## Data Model Alignment

These schemas participate in the module's canonical data model taxonomy:

- **Template Objects**: Reusable definitions that seed runtime content.
- **Active Campaign Objects**: Runtime, campaign-scoped state and instances.
- **Fact Objects**: Durable reference/source records used by both templates and campaigns.

This schema directory primarily defines JSON payload contracts used in **Fact** records (for example `dc_campaign_characters.character_data` where `campaign_id = 0`) and **Active Campaign** records (for example `dc_campaigns.campaign_data`), while template rows are imported via table-organized files under `config/examples/templates/`.

## Quick Reference

| Schema File | Purpose | Versioned | Lines | Primary Use |
|-------------|---------|-----------|-------|-------------|
| `character.schema.json` | Complete PF2e character | ✓ | 564 | `dc_campaign_characters.character_data` |
| `character_options_step[1-8].json` | Character creation wizard | Partial | 298-525 | Character creation UI |
| `campaign.schema.json` | Campaign state & progress | ✓ | 137 | `dc_campaigns.campaign_data` |
| `creature.schema.json` | Monsters, NPCs, beasts | ✓ | 1101 | Entity spawning |
| `dungeon_level.schema.json` | Complete dungeon floor | ✓ | 329 | Level generation |
| `encounter.schema.json` | Combat & initiative | ✓ | 568 | Combat engine |
| `entity_instance.schema.json` | Placed entities (runtime) | ✓ | 289 | Runtime entity management |
| `hazard.schema.json` | Environmental hazards | ✓ | 476 | PF2e hazards |
| `hexmap.schema.json` | Hex-based dungeon map | ✓ | 247 | Map structure |
| `item.schema.json` | Equipment & loot | ✓ | 441 | Inventory system |
| `obstacle.schema.json` | Map obstacles | ✓ | 231 | Traversal blockers |
| `obstacle_object_catalog.schema.json` | Reusable obstacle definitions | ✓ | 224 | Obstacle templates |
| `party.schema.json` | Adventuring party | ✓ | 441 | Party management |
| `room.schema.json` | Individual dungeon rooms | ✓ | 471 | Room generation |
| `trap.schema.json` | Mechanical & magical traps | ✓ | 440 | Trap mechanics |

## Schema Categories

### Character Schemas

#### `character.schema.json`
Complete Pathfinder 2E character data structure stored in the `dc_campaign_characters` table's `character_data` JSON column.

**Defines:**
- Character attributes (name, level, abilities)
- Ancestry, heritage, background, class
- Equipment, spells, feats
- Conditions and game state
- Backstory and appearance

#### `character_options_step1.json` - `character_options_step8.json`
Character creation wizard options and validation rules for each of the 8 steps:

1. **Step 1**: Name & Concept
2. **Step 2**: Ancestry & Heritage (v1.0.0)
3. **Step 3**: Background (v1.0.0)
4. **Step 4**: Class (v1.0.0)
5. **Step 5**: Ability Scores
6. **Step 6**: Alignment & Deity (v1.0.0)
7. **Step 7**: Equipment
8. **Step 8**: Finishing Touches (v1.0.0)

**Versioning Status**: Steps 2, 3, 4, 6, and 8 have schema versioning (v1.0.0). Steps 1, 5, and 7 are UI-only schemas with lower versioning priority.

**Defines:**
- Available options at each step
- Field types and validation rules
- Help text and examples
- Navigation rules
- Error messages

### Dungeon Schemas

#### `campaign.schema.json`
Campaign state payload stored in `dc_campaigns.campaign_data`.

**Defines:**
- Campaign ownership (created_by user ID)
- Campaign status (started flag, progress events)
- Current location (active_hex in axial coordinates)
- Timestamps (created_at, updated_at)
- Custom metadata storage

**Validation:**
- Runtime validation via `SchemaLoader::validateCampaignData()`
- schema_version: Semantic versioning pattern (e.g., "1.0.0")
- active_hex: Axial coordinate format (e.g., "q0r0", "q2r-1")
- progress events: Required type (string) and timestamp (unix epoch)

**Common Progress Event Types:**
- `quest_started`, `quest_completed`
- `location_discovered`
- `combat_won`, `combat_fled`
- `item_acquired`
- `level_up`, `character_death`
- `milestone_reached`

#### `creature.schema.json`
Monsters, NPCs, and beasts with PF2e stats and AI personality.

**Defines:**
- Base creature stats (level, size, rarity, perception)
- PF2e attributes (abilities, AC, saves, HP)
- Movement speeds (land, fly, swim, climb, burrow)
- Skills with modifiers
- Attacks and special abilities
- Spells and spellcasting
- AI behavior (personality, tactics, patrol routes)
- Loot tables and XP rewards

**Key Features:**
- Comprehensive validation with 50+ targeted constraints
- Strict validation with `additionalProperties: false` throughout
- Support for unique items in arrays (traits, immunities, senses, languages)
- String length validation to prevent empty values
- PF2e-aligned numeric ranges (levels -1 to 30, DCs 0-50)
- Backward compatible with existing creature data

#### `dungeon_level.schema.json`
Entire dungeon floor with hexmap, rooms, and encounters.

**Defines:**
- Level metadata (depth, theme, difficulty)
- Hexmap structure with terrain and elevation
- Rooms and their connections
- Entities (creatures, items, obstacles, traps, hazards)
- Active encounters and combat state
- Stairways to other levels
- Environmental effects
- Generation statistics

**Key Features:**
- Schema versioning for migration compatibility
- Comprehensive validation with 16+ constraint improvements
- Strict validation with `additionalProperties: false` throughout
- Unique items enforcement on key arrays
- String length bounds (name: 200, flavor_text: 2000)
- Array size limits (rooms: 100, entities: 500, etc.)
- PF2e-aligned numeric ranges (levels, DCs, creature counts)
- Timestamp tracking (created_at, updated_at)

Canonical runtime placement in this schema is `entities[]` via `entity_instance.schema.json`.

#### `entity_instance.schema.json`
**Primary Runtime Entity Representation**: Unified placed-entity runtime instance (`creature`, `item`, `obstacle`) with placement and mutable state.

**Purpose**: This is the canonical data structure for all placed entities in dungeon levels at runtime, referenced by `dungeon_level.schema.json` in the `entities[]` array. Provides consistent interface for entity lifecycle management (spawn, move, despawn).

**Key Features**:
- **Unified Entity Model**: Single schema handles creatures, items, and obstacles
- **Content Reference System**: `entity_ref` links to base definitions (creature.schema.json, item.schema.json, etc.)
- **Runtime State Tracking**: Mutable `state` object tracks lifecycle, combat, and gameplay changes
- **Hex Placement**: Axial coordinate system for precise dungeon map positioning
- **Inventory Support**: Creatures can carry items via inventory references
- **Version Pinning**: Optional version field for deterministic replay

**State Properties**:
- `active`: Currently active in game world
- `destroyed`: Permanently destroyed (killed, consumed, demolished)
- `disabled`: Temporarily disabled (disarmed trap, deactivated hazard)
- `hidden`: Hidden from view (stealthy creature, concealed trap)
- `collected`: Item collected by party (primarily for items)
- `hit_points`: Combat HP tracking (primarily for creatures)
- `inventory`: Carried items (primarily for creatures)
- `metadata`: Extensible storage for entity-specific data

**Examples**: Includes comprehensive examples for creature, item, and obstacle instances with realistic runtime state.

#### `encounter.schema.json`
Combat encounters with creatures, initiative, and tactical state.

**Defines:**
- Encounter metadata (type, status, threat level)
- XP budget thresholds (trivial=40, low=60, moderate=80, severe=120, extreme=160)
- Combatant tracking (initiative, HP, position, conditions)
- Combat state (round, active combatant, action log)
- Terrain effects and hazards
- Rewards (XP, currency, items)
- AI-generated narrative elements

**Key Features:**
- Schema versioning (v1.0.0) for migration compatibility
- Extracted reusable definitions: `hex_position`, `condition`, `roll_result`, `damage_result`
- Campaign ID field for database implementation support
- Enhanced validation constraints and examples

**Note:** Runtime data is stored in relational tables (`combat_encounters`, `combat_participants`, `combat_conditions`, `combat_actions`) while this schema serves as documentation and validation specification.


#### `hazard.schema.json`
PF2e-compatible environmental hazards (simple and complex). Unlike traps, hazards are often ongoing and visible.

**Defines:**
- Simple hazards: One-time dangers (falling rocks, collapsing floors)
- Complex hazards: Ongoing threats that act in initiative order
- Physical stats: AC, hardness, HP, saves, immunities, resistances, weaknesses
- Detection and disabling: Stealth DC, structured disable skill DCs
- State tracking: is_active, is_detected, is_triggered, is_disabled, is_destroyed
- Hex placement: Coordinates for map-based hazards
- Rarity classification: common, uncommon, rare, unique

**Key Features:**
- Schema versioning for migration compatibility
- Structured effect object (attack rolls, damage dice, saving throws, conditions, area of effect)
- Structured disable object with named PF2e skills (Thievery, Athletics, Arcana, Religion, Crafting)
- Supports both string and structured object format for reset mechanics
- Optional initiative_modifier and routine for complex hazards
- Full PF2e save support (Fortitude, Reflex, Will)
- Strict validation with `additionalProperties: false`
- Comprehensive constraints aligned with PF2e rules (levels -1 to 25, DCs 0-50)
- String length bounds (name, description, trigger, etc.) to prevent storage/rendering issues
- Timestamp tracking (created_at, updated_at)


#### `hexmap.schema.json`
Hex-based dungeon map with fog of war and terrain using axial coordinates (q, r) for flat-top hex positioning.

**Defines:**
- Hex grid configuration (orientation, size, origin)
- Depth tiers (shallow_halls → the_abyss)
- Individual hex properties (terrain, elevation, visibility)
- Fog of war state (explored, visible, hidden)
- Connections between hexes (doors, passages)
- PF2e compatibility (5ft hexes = 1 PF2e square)

**Key Features:**
- Schema versioning (v1.0.0)
- Strict validation with additionalProperties: false
- Default flat-top orientation for dungeon crawls
- Supports dynamic terrain and elevation rules

#### `item.schema.json`
Equipment and magic items (loot/treasure is represented as items).

**Defines:**
- Item metadata (name, level, rarity, price)
- Item categories (weapon, armor, consumable, treasure, etc.)
- Weapon statistics (damage, traits, range)
- Armor statistics (AC bonus, dexterity cap, check penalty)
- Consumable properties (uses, activation)
- Magic properties (potency, striking, property runes)
- Inventory management (bulk, quantity, identified state)

**Key Features:**
- Schema versioning for migration compatibility
- Comprehensive validation with 35+ numeric constraints
- Pattern validation for dice formulas and bulk values
- Strict validation with `additionalProperties: false` throughout
- Required fields for nested objects (weapon damage, shield stats, etc.)
- Unique items enforcement on key arrays
- Timestamp tracking (created_at, updated_at)


#### `obstacle.schema.json`
Unified traversal/combat obstacles (non-container blockers/modifiers). PF2e-compatible obstacles that affect movement, provide cover, or deal damage.

**Defines:**
- Obstacle metadata (name, level, type, rarity, traits)
- Movement rules (passable, cost multiplier, skill checks)
- Combat effects (cover, damage on enter, saves)
- Runtime state (active, disabled, destroyed)
- Optional source reference to underlying trap/hazard
- XP rewards for overcoming obstacles
- Hex placement for map positioning

**Key Features:**
- Schema versioning for migration compatibility
- Full PF2e integration with level-based DCs (1-50)
- Movement cost multipliers for difficult terrain
- Combat mechanics (cover bonuses, damage, saves)
- Flexible skill check requirements (Athletics, Acrobatics, etc.)
- State tracking for runtime obstacle management
- Links to underlying trap/hazard definitions
- Strict validation with `additionalProperties: false` throughout
- Simplified damage pattern matching standard PF2e dice notation (XdY+Z)

#### `obstacle_object_catalog.schema.json`
Reusable obstacle object definitions (label, movable, stackable, movement flags) used by placed obstacle instances.

**Defines:**
- Object metadata (object_id, description)
- Movement configuration (movable, stackable, cost_multiplier)
- PF2e properties (size, weight/bulk)
- Interaction mechanics (opening, closing, skill DCs)
- Visual rendering metadata (sprite_id, color, rotation)
- Tagging system for categorization

**Key Features:**
- Schema versioning (required field) for migration compatibility
- Reusable `$defs` section with `movement_config` definition
- Optional enrichment fields for detailed obstacle definitions
- String validation (minLength: 1) to prevent empty values
- Numeric bounds (cost_multiplier max: 999)
- Unique items enforcement on tags array
- Full backward compatibility with existing data


#### `party.schema.json`
Adventuring party with shared resources and exploration state.

**Defines:**
- Party metadata (name, owner, timestamps)
- Party members with PF2e conditions, spell slots, hero points, exploration activities
- Shared inventory and currency (cp, sp, gp, pp)
- Exploration state (mode, lighting, movement speed, rest tracking)
- Fog of war tracking (revealed hexes/rooms/connections, player notes)
- Encounter history log
- Cumulative dungeon statistics

**Key Features:**
- Schema versioning (v1.0.0) for migration compatibility
- Reusable definitions: `hex_position`, `condition`, `currency`
- Strict validation with `additionalProperties: false`
- Unique items enforcement on 4 arrays (watch_order, revealed_hexes, revealed_rooms, revealed_connections)
- String length constraints (minLength, maxLength)
- Strict pattern validation for spell_slots_remaining (ranks 0-10)
- Comprehensive examples for shared_inventory, encounter_log, fog_of_war notes
- Validates successfully with test data

#### `room.schema.json`
Individual dungeon rooms that occupy one or more hexes. AI-generated on first entry and permanent thereafter.

**Recently improved (2026-02-18, DCC-0027):**
- Fixed duplicate minLength/maxLength constraint bug on name field
- Added maxItems constraints to all 12 arrays (100% coverage)
- Added maxLength to theme_tags array items
- Improved validation consistency with peer schemas (hazard, dungeon_level, trap)
- All changes backward compatible with existing data

**Previously improved (2026-02-17):**
- Added schema versioning for migration compatibility
- Added timestamp tracking (created_at, updated_at)
- Added uniqueItems constraints to 11 arrays for data integrity
- Added minLength validation to prevent empty strings (10+ fields)
- Added maxLength constraints to name fields for UI compatibility
- Extracted reusable hex_coordinate definition to avoid duplication
- Enhanced property descriptions for clarity
- Added comprehensive room example with realistic data
- Improved consistency with trap.schema.json, hazard.schema.json, and obstacle.schema.json

**Defines:**
- Room metadata (UUID, name, AI descriptions, GM notes)
- Multi-hex occupation with per-hex terrain overrides
- Lighting conditions (bright, dim, darkness, magical darkness)
- Room state (explored, active, cleared)
- Environmental effects (temperature, hazards, magic auras)
- Connections to other rooms via doors/passages
- Contained entities (creatures, items, obstacles, traps)
- Terrain types (stone, dirt, water, etc.)

**Key Features:**
- Schema versioning for migration compatibility
- Unique items enforcement on 11 arrays for data integrity
- String validation (minLength) to prevent empty strings
- String length bounds (maxLength) for UI compatibility
- Reusable hex_coordinate definition to avoid duplication
- AI-generated narrative descriptions for read-aloud text
- Per-hex elevation tracking for PF2e rules
- Support for furniture and hex-specific objects
- Hidden vs visible room features
- Persistent state after first exploration
- Timestamp tracking (created_at, updated_at)

#### `trap.schema.json`
PF2e-compatible traps and snares (simple and complex). Traps are hidden threats that trigger when activated.

**Recently improved (2026-02-17):**
- Added `definitions` section with reusable hex_coordinate component
- Referenced hex_coordinate definition in hexes_affected array for consistency
- Added string validation (minLength: 1) to traits array items to prevent empty strings
- Added maximum value constraints to resistances/weaknesses (max: 30) aligned with hazard.schema
- Enhanced damage_type description to clarify support for multiple damage types
- Added comprehensive examples section with simple and complex trap patterns
- Improved consistency with hazard.schema.json structure and validation patterns

**Further improved (2026-02-18, DCC-0028):**
- Added 8 `maxLength` constraints to string fields for data integrity (name: 200, description: 2000, trigger: 1000, disable.custom: 500, conditions_applied items: 100, effect.description: 2000, immunities items: 50, reset.conditions: 500)
- Added `maxItems: 10` to traits array to prevent unreasonably large trait lists
- Added `maximum: 10080` (1 week) to reset_time_minutes for realistic reset timeframes
- Enhanced documentation for reset_time_minutes to clarify maximum constraint
- All changes maintain backward compatibility; existing valid data remains valid
- Total: 10 validation constraints added for consistency with hazard.schema.json
- See: `REVIEW_SUMMARY_DCC-0028.md`

**Defines:**
- Simple traps: One-time dangers (dart trap, pit trap)
- Complex traps: Ongoing threats that act in initiative order
- Physical stats: AC, hardness, HP, immunities, resistances, weaknesses
- Detection and disabling: Stealth DC, multiple skill disable DCs
- Trigger/effect system: Attack rolls, saving throws, damage, conditions
- Area of effect: Single hex, burst, emanation, cone, line
- Reset mechanics: Automatic or manual with timing
- State tracking: Runtime flags for detection, disabling, triggering, destruction

**Key Features:**
- Reusable definitions section (hex_coordinate) for schema consistency
- Supports both string and structured object format for reset mechanics
- Multiple skill options for disabling (Thievery, Athletics, Arcana, Religion, Crafting)
- Pattern validation for damage dice notation
- Full PF2e trait system support
- Strict validation with `additionalProperties: false`
- String validation (minLength: 1) on traits array to prevent empty strings
- Maximum value constraints aligned with hazard.schema (resistances/weaknesses max: 30)
- Enhanced damage_type field to support multiple damage types (e.g., "piercing, poison")
- Bounded string fields prevent database overflow and UI rendering issues

## Schema Standards

All schemas follow these conventions:

### Base Properties
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "$id": "https://dungeoncrawler.life/schemas/[schema-name].json",
  "title": "Human-readable title",
  "description": "Detailed description of purpose",
  "type": "object"
}
```

### Pathfinder 2E Alignment
- All schemas use official PF2e terminology
- Ability scores: `str`, `dex`, `con`, `int`, `wis`, `cha`
- Proficiency ranks: `untrained`, `trained`, `expert`, `master`, `legendary`
- Standard PF2e levels: 1-20 for characters, -1 to 25 for creatures

### Validation
- Use `enum` for fixed options
- Set `minimum`/`maximum` for numeric ranges
- Use `format` for dates, UUIDs, etc.
- Include descriptive error messages

### Documentation
- Every property has a `description`
- Complex structures include `examples`
- Default values are specified where appropriate

## Usage

### PHP Integration (Drupal)

#### Using SchemaLoader Service

The module provides a `SchemaLoader` service for loading and validating schemas:

```php
// Inject the service
$schemaLoader = \Drupal::service('dungeoncrawler_content.schema_loader');

// Load specific schemas
$characterSchema = $schemaLoader->loadCharacterSchema();
$campaignSchema = $schemaLoader->loadCampaignSchema();
$stepSchema = $schemaLoader->loadStepSchema(1); // Character creation step 1

// Validate campaign data
$validationResult = $schemaLoader->validateCampaignData($campaignData);
if ($validationResult['valid']) {
  // Data is valid
} else {
  // Handle validation errors
  foreach ($validationResult['errors'] as $error) {
    // Process error
  }
}
```

#### Manual Validation with justinrainbow/json-schema

```php
use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

// Load schema and data
$validator = new Validator();
$data = json_decode($character->character_data);
$schemaPath = __DIR__ . '/schemas/character.schema.json';
$schema = json_decode(file_get_contents($schemaPath));

// Validate with coercion for type flexibility
$validator->validate($data, $schema, Constraint::CHECK_MODE_COERCE_TYPES);

if ($validator->isValid()) {
  // Data is valid
  \Drupal::logger('dungeoncrawler_content')->info('Character data validated successfully');
} else {
  // Handle validation errors
  foreach ($validator->getErrors() as $error) {
    // Process error: $error['property'], $error['message']
    \Drupal::logger('dungeoncrawler_content')->error(
      'Validation error in @property: @message',
      ['@property' => $error['property'], '@message' => $error['message']]
    );
  }
}
```

### Validation in JavaScript
```javascript
import Ajv from 'ajv';
import addFormats from 'ajv-formats';
import characterSchema from './schemas/character.schema.json';

// Initialize Ajv with format support
const ajv = new Ajv({ allErrors: true });
addFormats(ajv);

const validate = ajv.compile(characterSchema);

if (validate(characterData)) {
  // Data is valid
  console.log('Character data is valid');
} else {
  // Log validation errors with details
  console.error('Validation failed:', validate.errors);
  validate.errors.forEach(error => {
    console.error(`  ${error.instancePath}: ${error.message}`);
  });
}
```

### VS Code Integration
Add to `.vscode/settings.json` in your workspace:
```json
{
  "json.schemas": [
    {
      "fileMatch": ["**/character_data/*.json"],
      "url": "./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/schemas/character.schema.json"
    },
    {
      "fileMatch": ["**/campaign_data/*.json"],
      "url": "./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/schemas/campaign.schema.json"
    },
    {
      "fileMatch": ["**/creatures/*.json"],
      "url": "./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/schemas/creature.schema.json"
    }
  ]
}
```

This enables IDE autocomplete, validation, and inline documentation for JSON data files.

## Maintenance

### Directory Cleanup Note

This directory previously contained historical completion/summary markdown files (e.g., `DCC-XXXX_COMPLETION.md`, `REVIEW_SUMMARY_DCC-XXXX.md`). These legacy work-tracking documents have been removed in accordance with the repository's Work Tracking and Status Policy (see `.github/instructions/instructions.md`). Per policy, implementation status should be tracked in GitHub Issues, not in separate markdown files. This directory now focuses solely on JSON schema files and this README documentation.

**Status**: Legacy files removed as of 2026-02-18.

### Schema Versioning Status

All production schemas now have `schema_version` field (migration-ready):
- ✓ `campaign.schema.json`
- ✓ `character.schema.json`
- ✓ `creature.schema.json`
- ✓ `dungeon_level.schema.json`
- ✓ `encounter.schema.json`
- ✓ `entity_instance.schema.json`
- ✓ `hazard.schema.json`
- ✓ `hexmap.schema.json`
- ✓ `item.schema.json`
- ✓ `obstacle.schema.json`
- ✓ `obstacle_object_catalog.schema.json`
- ✓ `party.schema.json`
- ✓ `room.schema.json`
- ✓ `trap.schema.json`

Character creation step schemas (partial versioning):
- ✓ `character_options_step2.json` (v1.0.0)
- ✓ `character_options_step3.json` (v1.0.0)
- ✓ `character_options_step4.json` (v1.0.0)
- ✓ `character_options_step6.json` (v1.0.0)
- ✓ `character_options_step8.json` (v1.0.0)
- Pending: Steps 1, 5, 7 (UI-only schemas - lower priority)

### Adding New Properties
1. Update the appropriate schema file
2. Add description and validation rules
3. Include default value if applicable
4. Test with sample data
5. Update this README if adding new schema category
6. Update the Quick Reference table with accurate line counts

### Breaking Changes
When making breaking changes:
1. Increment the schema version (following semantic versioning)
2. Document migration path in schema and README
3. Update SchemaLoader service if needed
4. Support both old and new formats during transition period
5. Update all references in code
6. Test with existing data to ensure backward compatibility

### Schema Versioning
Consider versioning schemas when:
- Removing required fields
- Changing data types
- Renaming properties
- Changing validation rules significantly

Example:
```json
{
  "schema_version": "2.0.0",
  "deprecated_fields": ["old_field_name"],
  "migration_guide": "..."
}
```

## Testing

### Validating Schema Files

Use online validators to ensure schemas are valid JSON Schema Draft 07:
- [JSON Schema Validator](https://www.jsonschemavalidator.net/)
- [JSONLint](https://jsonlint.com/) for JSON syntax validation

### Testing with Sample Data

```bash
# Using ajv-cli (install globally: npm install -g ajv-cli)
ajv validate -s character.schema.json -d sample_character.json

# Using PHP from Drupal module
drush php-eval "
  \$loader = \Drupal::service('dungeoncrawler_content.schema_loader');
  \$result = \$loader->validateCampaignData(\$sampleData);
  print_r(\$result);
"
```

### PHPUnit Tests

Schema validation is tested in the module's unit tests:
- `tests/src/Unit/Service/SchemaLoaderTest.php` (if exists)
- Character creation controllers validate against step schemas
- Campaign forms validate against campaign schema

## Troubleshooting

### Common Issues

**Issue: "Schema file not found"**
- **Cause**: Incorrect schema path or missing file
- **Solution**: Check that schema file exists in `config/schemas/` directory
- **Check logs**: `drush watchdog:show --type=dungeoncrawler_content`

**Issue: "Invalid JSON in schema"**
- **Cause**: Syntax error in schema file
- **Solution**: Use JSONLint to validate JSON syntax
- **Common errors**: Missing commas, unclosed brackets, trailing commas

**Issue: "Data validation fails unexpectedly"**
- **Cause**: Schema rules too strict or data format mismatch
- **Solution**: Check validation error messages for specific property failures
- **Debug**: Use `json_encode()` to see actual data structure

**Issue: "Schema version mismatch"**
- **Cause**: Data created with older schema version
- **Solution**: Implement migration logic or support multiple versions
- **Example**: Check `schema_version` field and apply appropriate validation

### Debugging Validation Errors

```php
// Get detailed validation errors
$validator = new \JsonSchema\Validator();
$validator->validate($data, $schema);

if (!$validator->isValid()) {
  foreach ($validator->getErrors() as $error) {
    echo sprintf("[%s] %s\n", $error['property'], $error['message']);
    // Example output: [campaign_data.active_hex] Does not match pattern: ^q-?\d+r-?\d+$
  }
}
```

## References

- [JSON Schema Specification](https://json-schema.org/)
- [JSON Schema Draft 07 Specification](https://json-schema.org/draft-07/json-schema-release-notes.html)
- [Pathfinder 2E Rules (Archives of Nethys)](https://2e.aonprd.com/)
- [Drupal Field Types](https://www.drupal.org/docs/drupal-apis/entity-api/fieldtypes-fieldwidgets-and-fieldformatters)
- [justinrainbow/json-schema (PHP)](https://github.com/justinrainbow/json-schema)
- [Ajv JSON Schema Validator (JavaScript)](https://ajv.js.org/)
- [Module Documentation](../README.md)
- [SchemaLoader Service](../../src/Service/SchemaLoader.php)

## Contributing

When contributing new schemas:
1. Follow existing naming conventions
2. Include comprehensive descriptions
3. Add validation rules appropriate for PF2e rules
4. Test with real data
5. Update this README
6. Add examples where helpful
