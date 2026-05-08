# Item Content Directory

This directory contains JSON files defining game items (weapons, armor, consumables, magic items) for the Dungeon Crawler module.

## Schema Compliance

All item files in this directory follow the official schema specification:
- **Schema**: `../../config/schemas/item.schema.json`
- **Version**: 1.0.0
- **Standard**: JSON Schema Draft 07

## File Format

Each item file must include:

### Required Fields
- `schema_version`: "1.0.0" (for migration tracking)
- `item_id`: UUID string (unique identifier)
- `name`: Display name
- `item_type`: One of: weapon, armor, shield, consumable, potion, scroll, wand, talisman, worn_item, held_item, material, adventuring_gear, relic, artifact
- `level`: Integer 0-25 (item level)
- `rarity`: One of: common, uncommon, rare, unique

### Common Optional Fields
- `traits`: Array of trait strings (e.g., ["magical", "healing"])
- `description`: Full item description
- `price`: Object with cp, sp, gp, pp fields (all integers, default 0)
- `bulk`: String pattern (number, "L" for light, or "-" for negligible)
- `hands`: "0", "1", "1+", or "2"
- `created_at`: ISO 8601 timestamp
- `updated_at`: ISO 8601 timestamp

### Type-Specific Fields

#### Consumables (Potions, Elixirs, etc.)
```json
"consumable_stats": {
  "consumable_type": "potion",
  "activate": {
    "actions": "1",
    "components": ["interact"]
  },
  "effect": "Description of effect",
  "duration": "instantaneous"
}
```

#### Weapons
```json
"weapon_stats": {
  "category": "martial",
  "group": "sword",
  "damage": {
    "dice_count": 1,
    "die_size": "d8",
    "damage_type": "slashing"
  }
}
```

#### Armor
```json
"armor_stats": {
  "category": "light",
  "ac_bonus": 2,
  "dex_cap": 5,
  "check_penalty": 0,
  "speed_penalty": 0
}
```

## Migration Notes

### Legacy Format (Pre-1.0.0)
Older item files used different field names:
- `content_id` → `item_id` (string → UUID)
- `type` → `item_type`
- `item_category` → `item_type` (more specific)
- `tags` → `traits`
- `price.gold` → `price.gp`
- `bulk: 0.1` → `bulk: "L"` (numeric → string)
- `effect` (custom structure) → `consumable_stats` (PF2e standard)

### ContentRegistry Service Compatibility
The `ContentRegistry` service (`src/Service/ContentRegistry.php`) expects legacy field names. When items are imported:
1. The service reads `schema_data` field from JSON
2. Legacy field mapping should be handled in the import logic
3. Database stores both the original UUID `item_id` and generates a `content_id` for backward compatibility

**Action Required**: Update `ContentRegistry::validateItem()` to support both formats during transition.

## Validation

Validate item JSON against schema:
```bash
# Using Node.js ajv-cli
ajv validate -s ../../config/schemas/item.schema.json -d healing_potion_minor.json

# Using PHP (via SchemaLoader service)
drush php-eval "
  \$loader = \Drupal::service('dungeoncrawler_content.schema_loader');
  \$data = json_decode(file_get_contents('healing_potion_minor.json'), TRUE);
  // Validation method to be implemented
"
```

## Examples

### Consumable (Potion)
See: `healing_potion_minor.json`

### Weapon
See: `longsword.json`

## Best Practices

1. **Use UUID for item_id**: Generate with `uuid` command or online UUID generator
2. **Follow PF2e Standards**: Use official Pathfinder 2E terminology and mechanics
3. **Include Timestamps**: Add `created_at` and `updated_at` for tracking
4. **Validate Before Commit**: Ensure JSON is valid and schema-compliant
5. **Document AI Generation**: Use `ai_generation` field if item is AI-generated

## References

- [Item Schema Documentation](../../config/schemas/README.md#item-schema)
- [Pathfinder 2E Item Rules](https://2e.aonprd.com/Equipment.aspx)
- [JSON Schema Standard](https://json-schema.org/draft-07/json-schema-release-notes.html)
