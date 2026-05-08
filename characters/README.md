# Character JSON Files

## Overview

This directory contains NPC character files in JSON format for the Dungeon Crawler module. These files define pre-created characters that can be used as NPCs, companions, or example characters.

## File Format

Character JSON files should conform to the character schema defined in `../config/schemas/character.schema.json`. 

### Required Fields (per schema)

- `step` (integer 1-8): Character creation progress (8 = complete)
- `name` (string): Character's name
- `level` (integer 1-20): Character level
- `ancestry` (string): PF2e ancestry (e.g., "Goblin", "Human", "Elf")
- `class` (string): PF2e class (e.g., "Rogue", "Fighter", "Wizard")
- `abilities` (object): Ability scores using shorthand keys:
  - `str`, `dex`, `con`, `int`, `wis`, `cha` (integers 1-30)

### Strongly Recommended Fields

- `schema_version` (string): Schema version for migration compatibility (e.g., "1.0.0"). While optional in schema, this field is highly recommended for all new character files to support future migrations.
- `concept` (string): Brief character concept/hook
- `heritage` (string): Ancestry-specific heritage
- `background` (string): Background ID (lowercase-with-hyphens)
- `background_boosts` (array): Ability boosts from background (e.g., `["dex", "con"]`)
- `subclass` (string): Class-specific subclass
- `alignment` (string): Two-letter alignment code (e.g., "CN", "LG", "N")
- `deity` (string): Worshipped deity (if any)
- `size` (string): Character size (Tiny, Small, Medium, Large)
- `speed` (integer): Base movement speed in feet
- `languages` (array): Languages known
- `skills` (object): Skill proficiencies with lowercase proficiency levels
  - Keys: skill names (lowercase, use `lore_` prefix for lore skills)
  - Values: `"untrained"`, `"trained"`, `"expert"`, `"master"`, or `"legendary"`
- `equipment` (array): Inventory items following schema format
- `gold` (number): Gold pieces (can include decimals for silver/copper conversion)
- `feats` (array): Feats with `feat_id`, `name`, `level_gained`, `feat_type`
- `hero_points` (integer 0-3): Current hero points
- `appearance` (string): Physical description (max 1000 characters)
- `personality` (string): Personality traits (max 1000 characters)
- `backstory` (string): Character backstory (max 5000 characters)
- `age` (string or integer): Character's age
- `gender` (string): Character's gender identity

### Extended NPC Data

For NPC characters, additional data can be stored in a `_npc_extended` object (prefixed with underscore to indicate it's not part of the core schema). This allows rich NPC data without cluttering the primary character structure.

**Purpose**: The `_npc_extended` object is for Game Master reference and storytelling content. It is not validated by the character schema and will not be used for mechanical calculations. This separation keeps the core character data clean while preserving rich narrative details.

**Recommended Structure**:

```json
{
  "name": "Example Character",
  ...schema fields...
  "_npc_extended": {
    "role": "Companion / Guide / Merchant / Antagonist / etc",
    "disposition": "Friendly / Neutral / Hostile / Varies",
    "encounter_behavior": "Description of how NPC approaches players",
    "combat_behavior": "Description of NPC tactics in combat",
    "knowledge": {
      "topic1": "What the NPC knows about topic1",
      "topic2": "What the NPC knows about topic2"
    },
    "relationships": {
      "character_name": "Relationship description"
    },
    "personality_details": {
      "traits": ["trait1", "trait2"],
      "goals": {
        "primary": "Primary goal",
        "secondary": "Secondary goal",
        "tertiary": "Optional tertiary goal"
      },
      "catchphrases": ["phrase1", "phrase2"]
    },
    "equipment_details": {
      "worn": {
        "armor": { "name": "...", "description": "..." },
        "other": ["item descriptions"]
      },
      "stowed": [
        { "name": "...", "description": "..." }
      ]
    },
    "feat_descriptions": {
      "feat-id": "Detailed description with flavor text"
    },
    "attack_descriptions": {
      "weapon-name": "Flavor text about the weapon"
    }
  }
}
```

**Guidelines for _npc_extended**:
- Use underscore prefix (`_npc_extended`) to signal non-schema data
- Include only for NPCs, not for player characters
- Focus on GM-facing information: roleplay hooks, tactics, knowledge
- Keep mechanical data (stats, abilities) in the core schema fields
- See `gribbles-rindsworth.json` for a complete working example

## Naming Conventions

### File Names
- Use lowercase with hyphens: `character-name.json`
- Example: `gribbles-rindsworth.json`

### Field Keys
- Use lowercase with underscores for core schema fields: `background_boosts`, `hero_points`
- Use lowercase with hyphens for IDs: `item_id`, `feat_id` values like `"trap-finder"`
- Skill names use underscore: `lore_cheese`, `lore_underworld`

### Ability References
- Always use 3-letter lowercase abbreviations: `str`, `dex`, `con`, `int`, `wis`, `cha`
- Never use full names like "Strength" or "Dexterity"

### Proficiency Levels
- Use lowercase: `"untrained"`, `"trained"`, `"expert"`, `"master"`, `"legendary"`

### Alignment
- Use 2-letter codes: `"LG"`, `"N"`, `"CE"`, `"CN"`, etc.
- Never use full names like "Chaotic Neutral"

## Calculated vs Stored Values

### DO Store
- Base ability scores
- Character level
- Equipment owned
- Skills trained
- Feats acquired

### DO NOT Store (calculate on demand)
- Ability modifiers (derive from scores)
- AC (calculate from armor, dex, proficiency)
- Saving throw modifiers
- Skill modifiers
- Attack bonuses
- Spell DC

These calculated values should be computed by the CharacterCalculator service when needed, not hard-coded in the JSON.

## HP Calculation

Maximum HP is calculated as:
```
HP = Ancestry_HP + (Class_HP_per_level × Level) + (Con_Modifier × Level)
```

Example for level 3 Goblin Rogue with Con 14 (+2):
```
HP = 10 + (8 × 3) + (2 × 3) = 10 + 24 + 6 = 40
```

## Currency Conversion

Store currency as single `gold` field with decimal precision:
```
gold = gp + (sp / 10) + (cp / 100)
```

Example: 2 gp, 14 sp, 37 cp = 2.51 gold

## Validation

Validate character JSON files against the schema:

```bash
# Using a JSON schema validator (if available)
ajv validate -s ../config/schemas/character.schema.json -d character-name.json

# Basic JSON syntax check
jq empty character-name.json
```

## Examples

See `gribbles-rindsworth.json` for a complete example of a well-structured NPC character file following all conventions.

## Migration Notes

### Changes from Previous Format (2026-02-17)

**gribbles-rindsworth.json** was refactored to align with character.schema.json:

1. **Removed wrapper**: Eliminated `pf2e_version` and `character` wrapper object
2. **Added required fields**: Added `step: 8` to mark as complete
3. **Fixed HP calculation**: Corrected from 38 to 40 (proper PF2e formula)
4. **Standardized ability format**: Changed from nested objects with modifiers to flat `abilities` object with scores only
5. **Unified feat structure**: Merged `ancestry_feat`, `class_feats`, `skill_feats` into single `feats` array with `feat_type`
6. **Simplified skills**: Changed from objects with modifiers to simple proficiency level strings
7. **Equipment normalization**: Converted from worn/held/stowed structure to flat array with flags
8. **Currency consolidation**: Changed from separate gp/sp/cp to single `gold` decimal
9. **Alignment code**: Changed from "Chaotic Neutral" to "CN"
10. **Ability references**: Changed from "Dexterity" to "dex" throughout
11. **Background format**: Changed from nested object to simple string ID
12. **Added concept field**: Extracted character concept for quick reference
13. **Moved NPC data**: Relocated NPC-specific data to `_npc_extended` object
14. **Removed calculated values**: Removed AC, saving throws, perception, skill modifiers, attack bonuses (to be calculated on demand)

All flavor text, descriptions, and personality details were preserved in the `_npc_extended` object to maintain the character's rich storytelling while keeping the core structure schema-compliant.
