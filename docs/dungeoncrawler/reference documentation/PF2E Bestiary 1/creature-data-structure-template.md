# PF2E Bestiary Creature Data Structure Template

## Overview
This document defines the data structure for extracting creature information from Pathfinder 2E Bestiary books for database seeding. The structure follows the official stat block format as defined in the Bestiary introduction.

## Official Stat Block Structure

Per the Pathfinder 2E Bestiary rules, creature stat blocks follow this order:

1. **Name and Level** - CREATURE NAME / CREATURE LEVEL
2. **Traits Line** - [Rarity] Alignment Size [Other Traits]
3. **Perception** - Modifier followed by special senses
4. **Languages** - Languages known and communication abilities
5. **Skills** - Trained or better skills with modifiers
6. **Ability Modifiers** - Str, Dex, Con, Int, Wis, Cha
7. **Items** - Significant gear carried
8. **Interaction Abilities** - Special perception/interaction abilities
9. **AC and Saves** - Armor Class and saving throws with bonuses
10. **HP/Immunities/Weaknesses/Resistances** - Defensive statistics
11. **Automatic Abilities** - Auras and passive defense abilities
12. **Reactive Abilities** - Free actions/reactions (triggered off-turn)
13. **Speed** - Movement speeds and special movement
14. **Strikes** - Melee and Ranged attacks
15. **Spells** - Prepared, Spontaneous, Innate, Focus, and Rituals
16. **Offensive/Proactive Abilities** - Actions and abilities used on creature's turn

## Complete Ankhrav Example (Creature Level 3)

### Creature Family Block
**Family Name:** Ankhrav
**Family Description:** Ankhravs are immense, burrowing, and insectile predators, considered by inhabitants of the rural areas of the world to be an all-too-common plague.

### Variant 1: Standard Ankhrav

#### Flavor Text
These horse-sized, burrowing monsters generally avoid heavily settled areas like cities, but ankhravs' predilection for livestock and humanoid flesh ensures that the creatures do not remain in the deep wilderness for long. Desperate farmers whose fields become infested by ankhravs often have little recourse but to seek the aid of adventurers.

#### Sidebar (Optional)
**Title:** ANKHRAV BURROWS
**Content:** As if the appearance of a hungry ankhrav in a stretch of farmland isn't bad enough, it almost always indicates the proximity of an ankhrav hive nearby. A disturbing number of ankhravs can infest a lair. However, adventurers brave enough to crawl through the tangled burrows are often rewarded with large amounts of treasure as ankhravs have a habit of dragging their victims back to the deepest corners of their den to feast, usually discarding the remains with most of the gear intact.

#### Stat Block

| Field | Value |
|-------|-------|
| **Creature Name** | ANKHRAV |
| **Level** | CREATURE 3 |
| **Rarity** | (not listed = common) |
| **Alignment** | N |
| **Size** | LARGE |
| **Traits** | ANIMAL |

**Perception:** +7
- Special Senses: darkvision, tremorsense (imprecise) 60 feet

**Languages:** (none)

**Skills:**
- Acrobatics +6
- Athletics +11
- Stealth +8

**Ability Scores:**
- Str +4
- Dex +1
- Con +3
- Int –4
- Wis +0
- Cha –2

**Items:** (none)

**Interaction Abilities:** (none for this creature)

**Armor Class:** 20

**Saving Throws:**
- Fort +12
- Ref +8
- Will +7

**Hit Points:** 40

**Immunities:** (none)
**Weaknesses:** (none)
**Resistances:** (none)

**Automatic Abilities:** (none for this creature)

**Reactive Abilities:** (none for this creature)

**Speed:**
- Ground: 25 feet
- Burrow: 20 feet

**Strikes:**
1. **Melee [one-action] mandibles +13**
   - Traits: acid
   - Damage: 1d8+4 piercing plus 1d6 acid

2. **Ranged [one-action] acid spit +10**
   - Traits: acid, range 30 feet
   - Damage: 3d6 acid

**Offensive/Proactive Abilities:**

1. **Armor-Rending Bite [two-actions]**
   - Description: The ankhrav makes a mandibles Strike; if the Strike hits, the target's armor takes the damage and the acid damage bypasses the armor's Hardness.

2. **Spray Acid [two-actions]**
   - Traits: acid
   - Frequency: once per hour
   - Effect: The ankhrav spews acid in a 30-foot cone, dealing 3d6 acid damage and 1d6 persistent acid damage (DC 20 basic Reflex save).

---

### Variant 2: Hive Mother

#### Flavor Text
Ankhrav hive mothers are fearsome predators that one can easily distinguish from the typical ankhrav not only by their greater size, but the presence of a large pair of razor-sharp, mantis-like arms.

#### Stat Block

| Field | Value |
|-------|-------|
| **Creature Name** | HIVE MOTHER |
| **Rarity** | UNCOMMON |
| **Level** | CREATURE 8 |
| **Alignment** | N |
| **Size** | HUGE |
| **Traits** | ANIMAL |

**Perception:** +16
- Special Senses: darkvision, tremorsense (imprecise) 90 feet

**Languages:** (none)

**Skills:**
- Acrobatics +13
- Athletics +20
- Stealth +11
- Survival +16

**Ability Scores:**
- Str +6
- Dex +1
- Con +4
- Int –4
- Wis +2
- Cha –2

**Items:** (none)

**Armor Class:** 29

**Saving Throws:**
- Fort +18
- Ref +15
- Will +14

**Hit Points:** 120

**Immunities:** (none)
**Weaknesses:** (none)
**Resistances:** (none)

**Automatic Abilities:** (none for this creature)

**Reactive Abilities:**
- **Attack of Opportunity [reaction]**

**Speed:**
- Ground: 25 feet
- Burrow: 20 feet

**Strikes:**
1. **Melee [one-action] mandibles +20**
   - Traits: acid
   - Damage: 2d8+6 piercing plus 2d6 acid

2. **Ranged [one-action] acid spit +17**
   - Traits: acid, range 30 feet
   - Damage: 5d6 acid

**Offensive/Proactive Abilities:**

1. **Armor-Rending Bite [two-actions]**
   - Description: The hive mother makes a mandibles Strike; if the Strike hits, the target's armor takes the damage and the acid damage bypasses the armor's Hardness.

2. **Frenzy Pheromone [two-actions]**
   - Effect: The hive mother unleashes a pheromone that causes all other ankhravs within a 100-foot emanation to become quickened 1 until the start of the hive mother's next turn, and they can use the extra action only for Burrow, Stride, or Strike actions. The hive mother can't unleash the pheromone again for 1d4 rounds.

3. **Spray Acid [two-actions]**
   - Traits: acid
   - Effect: The hive mother spews acid in a 60-foot cone, dealing 8d6 acid damage and 1d6 persistent acid damage (DC 26 basic Reflex save). It can't spew acid again for 1d4 rounds.

---

## Database Schema Mapping

Based on the `game_monsters` table from PR-02-combat-encounter-implementation.md:

```sql
CREATE TABLE game_monsters (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  creature_family VARCHAR(255),        -- e.g., "Ankhrav"
  variant_name VARCHAR(255),            -- e.g., "Hive Mother"
  level INT NOT NULL,
  rarity VARCHAR(50) DEFAULT 'common',  -- common, uncommon, rare, unique
  alignment VARCHAR(10),                -- N, LN, LE, NE, CE, CG, NG, LG, CN
  size VARCHAR(50),                     -- Tiny, Small, Medium, Large, Huge, Gargantuan
  traits TEXT[],                        -- Array of traits (Animal, Beast, etc.)
  
  -- Perception & Senses
  perception_bonus INT,
  special_senses TEXT[],                -- darkvision, tremorsense, etc.
  
  -- Languages
  languages TEXT[],                     -- Empty array if no languages
  
  -- Interaction Abilities
  interaction_abilities JSONB,          -- Special perception/interaction abilities
  
  -- Skills (JSON object with skill names and bonuses)
  skills JSONB,                         -- {"Acrobatics": 6, "Athletics": 11, "Stealth": 8}
  
  -- Ability Scores
  strength INT,
  dexterity INT,
  constitution INT,
  intelligence INT,
  wisdom INT,
  charisma INT,
  
  -- Items
  items TEXT[],
  
  -- Defenses
  armor_class INT NOT NULL,
  fortitude_save INT,
  reflex_save INT,
  will_save INT,
  hit_points INT NOT NULL,
  immunities TEXT[],
  weaknesses JSONB,                     -- {"fire": 5, "cold": 10}
  resistances JSONB,                    -- {"acid": 10, "electricity": 5}
  
  -- Automatic Abilities (Auras, passive defenses)
  automatic_abilities JSONB,            -- Array of automatic ability definitions
  
  -- Reactive Abilities (Reactions, off-turn free actions)
  reactive_abilities JSONB,             -- Array of reaction definitions
  
  -- Movement
  speeds JSONB,                         -- {"ground": 25, "burrow": 20, "fly": 40}
  
  -- Attacks (array of attack objects)
  strikes JSONB,                        -- Array of strike definitions
  
  -- Spells (if applicable)
  prepared_spells JSONB,                -- Prepared spells with tradition and DC
  spontaneous_spells JSONB,             -- Spontaneous spells with tradition and DC
  innate_spells JSONB,                  -- Innate spells (includes at-will and constant)
  focus_spells JSONB,                   -- Focus spells with pool size and DC
  rituals JSONB,                        -- Rituals the creature can perform
  
  -- Offensive/Proactive Abilities (actions used on creature's turn)
  offensive_abilities JSONB,            -- Array of offensive ability definitions
  
  -- Flavor & Lore
  description TEXT,                     -- Main flavor text
  family_description TEXT,              -- Family-level description
  sidebar_title VARCHAR(255),
  sidebar_content TEXT,
  
  -- Metadata
  source_book VARCHAR(255),             -- "Bestiary 1"
  page_reference VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### JSON Field Examples

#### strikes JSONB Format:
```json
[
  {
    "name": "mandibles",
    "action_cost": 1,
    "type": "melee",
    "attack_bonus": 13,
    "traits": ["acid"],
    "damage": "1d8+4 piercing plus 1d6 acid"
  },
  {
    "name": "acid spit",
    "action_cost": 1,
    "type": "ranged",
    "attack_bonus": 10,
    "traits": ["acid", "range 30 feet"],
    "damage": "3d6 acid"
  }
]
```

#### offensive_abilities JSONB Format:
```json
[
  {
    "name": "Armor-Rending Bite",
    "action_cost": 2,
    "traits": [],
    "description": "The ankhrav makes a mandibles Strike; if the Strike hits, the target's armor takes the damage and the acid damage bypasses the armor's Hardness."
  },
  {
    "name": "Spray Acid",
    "action_cost": 2,
    "traits": ["acid"],
    "frequency": "once per hour",
    "effect": "The ankhrav spews acid in a 30-foot cone, dealing 3d6 acid damage and 1d6 persistent acid damage (DC 20 basic Reflex save)."
  }
]
```

#### automatic_abilities JSONB Format:
```json
[
  {
    "name": "Aura of Courage",
    "range": "30 feet",
    "traits": ["aura", "emotion", "mental"],
    "effect": "Allies within the aura gain a +1 status bonus to saving throws against fear effects."
  }
]
```

#### reactive_abilities JSONB Format:
```json
[
  {
    "name": "Attack of Opportunity",
    "type": "reaction",
    "trigger": "A creature within your reach uses a manipulate action or a move action, makes a ranged attack, or leaves a square during a move action it's using.",
    "effect": "You lash out at a foe that leaves an opening. Make a melee Strike against the triggering creature."
  },
  {
    "name": "Shield Block",
    "type": "reaction",
    "trigger": "While you have your shield raised, you would take damage from a physical attack.",
    "effect": "You snap your shield into place to deflect a blow. Your shield prevents you from taking an amount of damage up to the shield's Hardness."
  }
]
```

#### innate_spells JSONB Format:
```json
{
  "tradition": "divine",
  "dc": 27,
  "attack": 17,
  "spells": {
    "5": [{"name": "telekinetic haul", "uses": null}],
    "4": [
      {"name": "dispel magic", "uses": null},
      {"name": "divine wrath", "uses": null, "notes": "lawful"},
      {"name": "lightning bolt", "uses": 3}
    ],
    "1": [{"name": "true strike", "uses": "at-will"}]
  },
  "constant": {
    "8": [{"name": "true seeing"}],
    "4": [{"name": "freedom of movement"}]
  },
  "cantrips": {
    "4": [{"name": "telekinetic projectile"}]
  }
}
```

#### prepared_spells / spontaneous_spells JSONB Format:
```json
{
  "tradition": "arcane",
  "type": "prepared",
  "dc": 28,
  "attack": 20,
  "spells": {
    "5": [{"name": "cone of cold"}, {"name": "wall of ice"}],
    "4": [{"name": "dimension door"}, {"name": "fly"}, {"name": "solid fog"}]
  },
  "cantrips": {
    "5": [{"name": "detect magic"}, {"name": "mage hand"}, {"name": "ray of frost"}]
  }
}
```

#### focus_spells JSONB Format:
```json
{
  "dc": 31,
  "focus_points": 3,
  "spells": [
    {"name": "ki strike", "level": 5},
    {"name": "wholeness of body", "level": 5}
  ]
}
```

#### rituals JSONB Format:
```json
[
  {"name": "geas", "dc": 32, "level": 6},
  {"name": "planar binding", "dc": 32, "level": 6}
]
```

---

## Data Extraction Pattern Recognition

### Text Patterns to Parse:

1. **Creature Family Header:** 
   - Pattern: Single word or short phrase as section header
   - Followed by descriptive paragraph

2. **Creature Variant Header:**
   - Pattern: Single word or short phrase (subheading)
   - Followed by flavor text paragraph

3. **Stat Block Header:**
   - Pattern: ALL CAPS name
   - Followed by traits line (RARITY, ALIGNMENT, SIZE, etc.)
   - Then "CREATURE [level]"
   - Then trait list

4. **Stat Lines:**
   - Pattern: "**Field Name** value" or "Field Name +X"
   - Skills: "Skills Name +X, Name +X, Name +X"
   - Abilities: "Str +X, Dex +X, Con +X, Int +X, Wis +X, Cha +X"

5. **Strikes:**
   - Pattern: "**Melee/Ranged [icon] name +X** (traits), Damage dice+mod type"

6. **Special Abilities:**
   - Pattern: "**Ability Name [icon]** (traits) Description"
   - May include Frequency, Trigger, Effect, or Requirements

---

## Extraction Process Workflow

1. **Identify Creature Family Block**
   - Extract family name and description
   
2. **For Each Variant in Family:**
   - Extract variant name
   - Extract flavor text
   - Extract sidebar (if present)
   
3. **Parse Stat Block (in official order):**
   - Extract basic info (name, rarity, alignment, size, level, traits)
   - Extract perception and senses
   - Extract languages (or note if none)
   - Parse skills line into key-value pairs
   - Parse ability scores
   - Extract items
   - Extract interaction abilities (if present)
   - Extract defenses (AC, saves, HP)
   - Extract immunities/weaknesses/resistances
   - Extract automatic abilities (auras, passive defenses)
   - Extract reactive abilities (reactions, off-turn free actions)
   - Parse speeds into movement types
   
4. **Parse Combat Stats:**
   - Extract all strikes (melee and ranged)
   - Parse attack bonuses, traits, and damage formulas
   
5. **Parse Spellcasting (if present):**
   - Identify spell type (prepared, spontaneous, innate, focus, ritual)
   - Extract tradition, DC, and attack modifier
   - Parse spell lists by level
   - Identify at-will and constant spells for innate spells
   - Extract focus point pool for focus spells
   
6. **Parse Offensive/Proactive Abilities:**
   - Extract name, action cost, traits, and description
   - Identify frequency, requirements, trigger, effect keywords
   - These are abilities used on the creature's turn

6. **Generate Database Insert:**
   - Map all extracted data to schema fields
   - Format JSON fields appropriately
   - Include source book and metadata

---

## Next Steps

1. Create Python/PHP script to parse creature entries
2. Implement regex patterns for each field type
3. Handle special cases (spellcasting, complex abilities)
4. Validate extracted data against schema
5. Generate SQL insert statements or JSON files for import
6. Process all creatures from Bestiary 1 (est. 400+ creatures)
7. Repeat for Bestiaries 2 and 3

