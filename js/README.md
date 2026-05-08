# JavaScript Files - Character Creation

## Overview

JavaScript files supporting character creation and gameplay in the Dungeon Crawler module.

## Files

### character-creation-schema.js

**Status**: Currently NOT IN USE (see below)

Schema-driven character creation form builder that dynamically generates form fields based on JSON schema loaded from `drupalSettings.characterCreation`.

**Why it's not active:**
- The file expects `drupalSettings.characterCreation` to be populated with schema data
- `CharacterCreationStepForm.php` currently builds forms directly in PHP
- No PHP code populates `drupalSettings.characterCreation` with schema data

**To activate:**
1. Update `CharacterCreationStepForm::buildForm()` to call `SchemaLoader::loadStepSchema()`
2. Attach schema data to `drupalSettings.characterCreation` before rendering
3. Ensure step schema files use patterns compatible with `extractSchemaValue()` helper

**Schema Conformance:**

The module uses a hybrid data model for performance:

- **Database hot columns** (high-frequency reads/writes):
  - `hp_current`, `hp_max` - Hit points
  - `armor_class` - AC calculation
  - `experience_points` - XP tracking
  - `position_q`, `position_r` - Hex coordinates
  - `last_room_id` - Location tracking

- **JSON payloads** (flexible/nested data):
  - `character_data` - Full character sheet (character.schema.json structure)
  - `state_data` - Campaign runtime state

- **Schema structures**:
  - `character.schema.json` uses nested `hit_points.max`, `hit_points.current`
  - `dc_campaign_characters` table uses flat `hp_max`, `hp_current` columns
  - `CharacterManager::extractHotColumnsFromData()` converts nested → flat
  - `CharacterManager::resolveHotColumnsForRecord()` prefers DB columns over JSON

- **Step schemas** (`character_options_step*.json`):
  - Use `"default"` and `"enum"` patterns instead of `"const"`
  - Compatible with `extractSchemaValue()` helper in character-creation-schema.js

**References:**
- `dungeoncrawler_content.install` - Table schema (lines 1225-1450)
- `config/schemas/character.schema.json` - Master character schema
- `config/schemas/character_options_step*.json` - Step-specific field schemas
- `src/Service/SchemaLoader.php` - Schema loading service
- `src/Service/CharacterManager.php` - Hot-column extraction (lines 722-757)
- `src/Form/CharacterCreationStepForm.php` - Current PHP form builder

### character-step-1.js through character-step-8.js

Step-specific JavaScript for character creation wizard. Each file handles interactions for its corresponding step.

**Improvements (DCC-0055 - Step 1):**
- Configuration constants for magic values
- JSDoc documentation
- Helper functions (isValidName, updateSubmitButton, handleAjaxError)
- Defensive null checks
- Improved error handling
- Context-aware DOM selection

### character-sheet.js

JavaScript for interactive character sheet display and management.

### game-cards.js

Card-based UI component interactions for game content display.

## Library Dependencies

All character step JavaScript files depend on `character-step-base` library which provides:
- Shared CSS (`character-steps.css`)
- jQuery, Drupal, once.js

See `dungeoncrawler_content.libraries.yml` for complete library definitions.

## Testing

JavaScript files should be tested with:
1. Browser console for errors
2. Manual interaction testing in character creation workflow
3. Schema validation against `character_options_step*.json` files

## Related Documentation

- `/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/README.md` - Module overview
- `/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/config/schemas/` - JSON schemas
- `DCC-0042` - Libraries architecture refactor
- `DCC-0055` - character-step-1.js improvements
- `DCC-0225` - character-creation-schema.js review/refactor
