# Template Import Examples

This directory stores file-based template imports for `/dungeoncrawler/objects`.

## Structure

Organize examples by **destination table**:

**Current table directories:**
- `dungeoncrawler_content_campaigns/`
- `dungeoncrawler_content_characters/`
- `dungeoncrawler_content_rooms/`
- `dungeoncrawler_content_dungeons/`
- `dungeoncrawler_content_encounter_instances/`
- `dungeoncrawler_content_room_states/`
- `dungeoncrawler_content_item_instances/`
- `dungeoncrawler_content_log/`
- `dungeoncrawler_content_registry/`
- `dungeoncrawler_content_loot_tables/`
- `dungeoncrawler_content_encounter_templates/`

Each JSON file may contain:

1. A single object row
2. An array of row objects
3. An object with a `rows` array

## Import Workflow

Use the **Import templates** button on `/dungeoncrawler/objects`.

Import loads and upserts rows from this directory into matching template tables.

Rows are merged using each table's unique keys (or primary key fallback).

## Library Baseline (2026-02-18)

Each table directory includes default JSON files with template rows to provide a starter library for generation, testing, and encounter design.

### Comprehensive PF2E Item Library

**As of February 18, 2026**, this library includes a **complete extraction of all Pathfinder 2E equipment items** from 6 official source books:

**Source Coverage:**
- Core Rulebook (4th Printing): 279 items
- Guns & Gears: 57 items
- Secrets of Magic: 55 items
- Advanced Player's Guide: 35 items
- Gods & Magic: 3 items
- Gamemastery Guide: 2 items

**Total: 431 items**

**Item Breakdown:**
- Weapons: 93
- Armor: 51
- Magic Items: 44
- Adventuring Gear: 243

**Key Features:**
- Full source book attribution for every item
- Price information extracted from source text (when available)
- Item type classification (weapon/armor/magic_item/adventuring_gear)
- Reference traceability with line numbers and extraction methods
- Ready for character creation, shopping, and loot generation

**Files:**
- `dungeoncrawler_content_registry/default_registry_examples.json` - 431 item definitions

### Creatures

The registry examples hold no creatures. A creature is a canonical actor
definition of class `creature`, saved through
`CanonicalDefinitionService::saveDefinition('actor', ...)`; there is no
creature content type to import.

### Comprehensive PF2E Spell Library

**As of February 19, 2026**, this library includes **all spells from 3 Pathfinder 2E spellcasting books**:

**Source Coverage:**
- Core Rulebook (4th Printing): 786 spell entries
- Secrets of Magic: 518 spell entries
- Advanced Player's Guide: 139 spell entries

**Total: 728 unique spells** (merged from 1,443 raw extractions)

**Spell Breakdown:**
- **By Level:**
  - Cantrips (Level 0): 61
  - Level 1: 117
  - Level 2: 114
  - Level 3: 82
  - Level 4: 93
  - Level 5: 86
  - Level 6: 49
  - Level 7: 43
  - Level 8: 34
  - Level 9: 26
  - Level 10: 23

- **By Tradition:**
  - Arcane: 388 spells
  - Primal: 398 spells
  - Occult: 357 spells
  - Divine: 253 spells

- **By School:**
  - Evocation: 141 spells
  - Transmutation: 108 spells
  - Conjuration: 91 spells
  - Necromancy: 89 spells
  - Abjuration: 80 spells
  - Others: 219 spells

**Key Features:**
- Spell names extracted from structured spell list sections
- Spell level (0-10) and school classification
- Tradition associations (spells can belong to multiple traditions)
- Rarity and heightening information
- Source book attribution with line-level traceability
- Ready for spellcasting character creation, spell selection, and magic item generation

**Extraction Method:**
Unlike items and creatures which required OCR pattern matching, spells were extracted from structured spell list sections in the source books (e.g., "Arcane Spell List", "Divine 2nd-Level Spells"). This provided higher quality extraction with proper spell names like "Magic Missile", "Fireball", "Detect Magic" rather than game mechanics text.

**Files:**
- `dungeoncrawler_content_registry/default_registry_examples.json` - 728 spell definitions (appended to items)
- **Total Registry Entries: 1,167** (435 items + 728 spells + 2 locations + 1 hazard + 1 encounter)

### Production Deployment

Templates are automatically imported to production via **update hook 10012** in `dungeoncrawler_content.install`. When code is deployed:

1. GitHub Actions pushes new code to production
2. `drush updatedb` runs automatically
3. Update hook 10012 imports all templates
4. Production database has complete PF2E item library + creature library

The import is idempotent - running multiple times is safe.

### Other Template Tables

All other template tables include **10 baseline rows** each:
- Campaigns, Characters, Dungeons, Rooms, Encounters
- Loot Tables, Room States, Logs, Item Instances

## Pathfinder Reference Alignment

These templates are Pathfinder 2e-inspired starter references for internal library seeding, aligned to content categories and encounter pacing in:

- Core Rulebook (encounter budgeting concepts and action economy patterns)
- Bestiary volumes (creature role inspiration and level progression)
- Gamemastery Guide (hazards, dungeon pacing, and encounter composition)
- Secrets of Magic / Guns & Gears (theme flavor for arcane and construct encounters)

The files intentionally store concise, system-friendly metadata (IDs, tags, structured payloads) rather than verbatim rules text.
