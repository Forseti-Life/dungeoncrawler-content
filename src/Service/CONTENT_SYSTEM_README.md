# Game Content System - Implementation Stubs

This directory contains stubbed service implementations for the Game Content System as designed in:
`docs/dungeoncrawler/issues/issue-3-game-content-system-design.md`

## Services

### ContentRegistry.php
Service for loading and managing game content from JSON schemas.

**Responsibilities:**
- Import content from JSON files
- Validate content against schemas
- Update content in database
- Manage content versions

**Key Methods:**
- `importContentFromJson()` - Load content from JSON files into database
- `getContent()` - Retrieve content by type and ID
- `validateContent()` - Validate content against JSON schemas
- `updateContent()` - Update existing content

**Status:** Stub implementation only. All methods return default/empty values.

### ContentQuery.php
Service for querying and filtering game content.

**Responsibilities:**
- Query creatures, items, traps by various filters
- Roll loot tables
- Build encounters from templates
- Provide random content selection

**Key Methods:**
- `queryCreatures()` - Query creatures with filters (level, tags, rarity)
- `queryItems()` - Query items with filters
- `rollLootTable()` - Roll a loot table for items
- `buildEncounterFromTemplate()` - Generate encounter from template

**Status:** Stub implementation only. All methods return empty arrays.

### ContentGenerator.php
Service for generating game content procedurally.

**Responsibilities:**
- Generate room content (creatures, items, traps)
- Generate encounters based on party level and threat
- Generate treasure hoards
- Add AI personality to creatures

**Key Methods:**
- `generateRoomContent()` - Generate content for a dungeon room
- `generateEncounter()` - Generate combat encounter
- `generateTreasureHoard()` - Generate treasure by level and type
- `generateCreaturePersonality()` - Add AI behavior to creatures

**Status:** Stub implementation only. All methods return empty/default structures.

## Database Tables

The following tables are defined in `dungeoncrawler_content.install`:

### dungeoncrawler_content_registry
Stores all game content (creatures, items, traps, hazards).

**Key Fields:**
- `content_type` - Type: creature, item, trap, hazard
- `content_id` - Unique identifier (e.g., goblin_warrior)
- `name` - Display name
- `level` - Challenge level
- `rarity` - common, uncommon, rare, unique
- `tags` - JSON array of tags
- `schema_data` - Full content data as JSON

### dungeoncrawler_content_loot_tables
Loot tables for random item generation.

**Key Fields:**
- `table_id` - Table identifier
- `entries` - JSON array of loot entries with weights
- `level_range` - Appropriate level range

### dungeoncrawler_content_encounter_templates
Pre-designed encounter templates.

**Key Fields:**
- `template_id` - Template identifier
- `level` - Average party level
- `xp_budget` - Total XP for encounter
- `threat_level` - trivial, low, moderate, severe, extreme
- `creature_slots` - JSON array of creature requirements

## Service Registration

Services are registered in `dungeoncrawler_content.services.yml`:

```yaml
dungeoncrawler_content.content_registry:
  class: Drupal\dungeoncrawler_content\Service\ContentRegistry
  arguments: ['@database', '@logger.factory']

dungeoncrawler_content.content_query:
  class: Drupal\dungeoncrawler_content\Service\ContentQuery
  arguments: ['@database']

dungeoncrawler_content.content_generator:
  class: Drupal\dungeoncrawler_content\Service\ContentGenerator
  arguments: ['@dungeoncrawler_content.content_query']
```

## Usage Example

```php
// Get the content query service
$content_query = \Drupal::service('dungeoncrawler_content.content_query');

// Query level 1-3 goblinoid creatures
$creatures = $content_query->queryCreatures([
  'level_min' => 1,
  'level_max' => 3,
  'tags_include' => ['goblinoid'],
], 5);

// Generate an encounter
$generator = \Drupal::service('dungeoncrawler_content.content_generator');
$encounter = $generator->generateEncounter(
  $party_level = 2,
  $party_size = 4,
  $threat_level = 'moderate',
  $theme = 'goblin_warrens'
);

// Roll a loot table
$loot = $content_query->rollLootTable('goblin_common');
```

## Next Steps

1. **Implement ContentRegistry methods:**
   - JSON file parsing and importing
   - Schema validation
   - Database operations

2. **Implement ContentQuery methods:**
   - Build complex database queries
   - Implement loot table rolling algorithm
   - Implement encounter building logic

3. **Implement ContentGenerator methods:**
   - XP budget calculations
   - Room content generation
   - Treasure hoard generation

4. **Create Content Files:**
   - Add JSON files for creatures, items, traps
   - Create loot table definitions
   - Create encounter templates

5. **Create Admin Interface:**
   - Content browser UI
   - Content editor with validation
   - Loot table testing tools

## References

- **Design Document:** `docs/dungeoncrawler/issues/issue-3-game-content-system-design.md`
- **Database Schema:** Lines 166-250 of design document
- **Service Layer:** Lines 253-446 of design document
- **Generation Algorithms:** Lines 834-1079 of design document

## Installation

To install the tables, run:

```bash
drush updb
```

This will execute update hook 10002 to create the game content system tables.
