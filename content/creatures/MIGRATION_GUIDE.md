# Creature Content Migration Guide

## Overview

This guide documents the migration from the legacy creature format to the new schema-compliant format (v1.0.0) as defined in `config/schemas/creature.schema.json`.

## Schema Changes

### Top-Level Fields

| Old Field | New Field | Notes |
|-----------|-----------|-------|
| `content_id` | `creature_id` | Now requires UUID format |
| `type` | `creature_type` | Now uses enum values (e.g., "humanoid" instead of "creature") |
| `tags` | `traits` | Renamed to match PF2e terminology |
| `size` | `size` | Now lowercase (e.g., "small" instead of "Small") |
| N/A | `schema_version` | New field, should be "1.0.0" |
| N/A | `hex_footprint` | New field for map placement |
| `version` | `schema_version` | Renamed for clarity |

### Nested Structure Changes

#### Abilities → pf2e_stats.ability_scores

**Old Format:**
```json
"abilities": {
  "STR": 10,
  "DEX": 16,
  "CON": 12,
  "INT": 8,
  "WIS": 10,
  "CHA": 8
}
```

**New Format:**
```json
"pf2e_stats": {
  "ability_scores": {
    "strength": {
      "score": 10,
      "modifier": 0
    },
    "dexterity": {
      "score": 16,
      "modifier": 3
    },
    // ... etc
  }
}
```

**Note:** Modifiers are calculated as `(score - 10) / 2` (integer division).

#### Stats → pf2e_stats (multiple fields)

**Old Format:**
```json
"stats": {
  "ac": 17,
  "hp": 16,
  "fortitude": 6,
  "reflex": 9,
  "will": 4
}
```

**New Format:**
```json
"pf2e_stats": {
  "hp": {
    "max": 16,
    "current": 16,
    "temporary": 0,
    "hardness": 0,
    "immunities": [],
    "resistances": [],
    "weaknesses": []
  },
  "ac": 17,
  "saves": {
    "fortitude": {
      "modifier": 6
    },
    "reflex": {
      "modifier": 9
    },
    "will": {
      "modifier": 4
    }
  }
}
```

#### Perception

**Old Format:**
```json
"perception": {
  "modifier": 5,
  "senses": ["darkvision 60ft"]
}
```

**New Format:** (moved into pf2e_stats)
```json
"pf2e_stats": {
  "perception": {
    "modifier": 5,
    "senses": ["darkvision 60ft"]
  }
}
```

#### Speed

**Old Format:**
```json
"speed": {
  "land": 25
}
```

**New Format:** (moved into pf2e_stats, with all movement types)
```json
"pf2e_stats": {
  "speed": {
    "land": 25,
    "fly": null,
    "swim": null,
    "climb": null,
    "burrow": null
  }
}
```

#### Attacks

**Old Format:**
```json
"attacks": [
  {
    "name": "Dogslasher",
    "type": "melee",
    "bonus": 8,
    "damage": "1d6+2 slashing",
    "traits": ["agile", "backstabber", "finesse"]
  }
]
```

**New Format:** (moved into pf2e_stats, damage split)
```json
"pf2e_stats": {
  "attacks": [
    {
      "name": "Dogslasher",
      "type": "melee",
      "attack_bonus": 8,
      "damage": "1d6+2",
      "damage_type": "slashing",
      "traits": ["agile", "backstabber", "finesse"],
      "reach_ft": 5
    }
  ]
}
```

#### AI Behavior → AI Personality

**Old Format:**
```json
"ai_behavior": {
  "aggression": 0.7,
  "tactics": "skirmisher",
  "preferred_range": "melee",
  "retreat_threshold": 0.3,
  "priority_targets": ["spellcaster", "healer"]
}
```

**New Format:**
```json
"ai_personality": {
  "disposition": "hostile",
  "personality_traits": [
    "Aggressive and territorial",
    "Cowardly when outnumbered",
    "Values shiny objects"
  ],
  "goals": {
    "primary": "Defend territory and collect treasure"
  },
  "fears": ["Dogs", "Horses", "Fire"],
  "desires": ["Shiny objects", "Easy prey", "Recognition from tribe"],
  "speech_patterns": {
    "dialect": "Broken Common with Goblin interjections",
    "catchphrases": ["Longshanks!", "Shinies!"],
    "formality": "crude"
  },
  "combat_personality": {
    "aggression": "aggressive",
    "tactics": "basic",
    "morale": "cautious",
    "flee_threshold_hp_percent": 30,
    "preferred_targets": ["spellcaster", "healer"],
    "preferred_range": "melee"
  },
  "memory": [],
  "relationships": {}
}
```

### New Required Fields

#### Lifecycle

All creatures now require lifecycle management:

```json
"lifecycle": {
  "spawn_type": "respawning",
  "is_alive": true,
  "death_timestamp": null,
  "killed_by_party": null,
  "respawn_cooldown_hours": 24.0,
  "next_respawn_at": null,
  "home_room_id": "",
  "current_room_id": "",
  "patrol_route": [],
  "wander_radius_rooms": 2
}
```

**Spawn types:**
- `permanent`: Dies forever when killed
- `respawning`: Returns after cooldown
- `wandering`: Roams between rooms
- `boss`: Unique encounter
- `quest`: Quest-specific creature
- `summoned`: Temporarily summoned

#### Loot Table

```json
"loot_table": {
  "guaranteed": [],
  "random": [
    {
      "item": {
        "name": "Rusty Dogslasher",
        "quantity": 1,
        "rarity": "common"
      },
      "drop_chance": 0.8
    }
  ],
  "currency": {
    "cp_range": [1, 12],
    "sp_range": [0, 2],
    "gp_range": [0, 0]
  }
}
```

#### Timestamps

```json
"created_at": "2026-02-17T14:35:38.040863+00:00",
"updated_at": "2026-02-17T14:35:38.040876+00:00"
```

Use ISO 8601 format with timezone (UTC recommended).

## Migration Checklist

When migrating a creature file:

- [ ] Add `schema_version: "1.0.0"`
- [ ] Generate UUID for `creature_id` (keep `content_id` for backward compatibility)
- [ ] Change `type` to `creature_type` with proper enum value
- [ ] Rename `tags` to `traits`
- [ ] Normalize `size` to lowercase
- [ ] Add `hex_footprint` (1 for tiny/small/medium, 3-4 for large, etc.)
- [ ] Restructure `abilities` → `pf2e_stats.ability_scores` with score+modifier objects
- [ ] Calculate modifiers: `(score - 10) / 2`
- [ ] Restructure `stats` → multiple `pf2e_stats` fields (hp, ac, saves)
- [ ] Move `perception` into `pf2e_stats`
- [ ] Move and expand `speed` into `pf2e_stats` with all movement types
- [ ] Move `languages` into `pf2e_stats`
- [ ] Move and update `attacks` into `pf2e_stats` (split damage/damage_type, add reach_ft)
- [ ] Add `pf2e_stats.skills` with modifiers and proficiencies
- [ ] Expand `ai_behavior` → full `ai_personality` structure
- [ ] Add `lifecycle` management
- [ ] Add `loot_table` with currency and items
- [ ] Add `created_at` and `updated_at` timestamps
- [ ] Validate against `config/schemas/creature.schema.json`

## Validation

After migration, validate the file:

```bash
python3 << 'EOF'
import json
from jsonschema import validate, Draft7Validator

# Load creature and schema
with open('content/creatures/your_creature.json', 'r') as f:
    creature = json.load(f)
with open('config/schemas/creature.schema.json', 'r') as f:
    schema = json.load(f)

# Validate
validator = Draft7Validator(schema)
errors = list(validator.iter_errors(creature))

if errors:
    print("❌ Validation errors:")
    for error in errors:
        print(f"  - {error.message}")
else:
    print("✅ Validation passed!")
EOF
```

## Backward Compatibility

The ContentRegistry service has been updated to support both old and new schemas:

- Accepts either `content_id` or `creature_id`
- Accepts either `type` or `creature_type`
- Accepts either `tags` or `traits`
- Accepts either `version` or `schema_version`
- Validates either `abilities` or `pf2e_stats.ability_scores`
- Validates either `stats` or `pf2e_stats`

This allows for gradual migration of creature files without breaking existing functionality.

## Example: Goblin Warrior Migration

See `goblin_warrior.json` for a complete example of a migrated creature file.

**Key improvements:**
- Full schema compliance
- Enhanced AI personality system
- Lifecycle management for respawning
- Comprehensive loot table
- PF2e-accurate stat structure
- Timestamp tracking

## References

- Schema: `config/schemas/creature.schema.json`
- Schema README: `config/schemas/README.md`
- ContentRegistry: `src/Service/ContentRegistry.php`
- Example: `content/creatures/goblin_warrior.json`
