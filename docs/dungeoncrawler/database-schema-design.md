# Pathfinder 2E Database Schema Design

## Verification Notes (2026-02-18)

- This document combines implemented module schema notes with conceptual SQL design examples.
- The SQL snippets below (for example `users`, `campaigns`, `characters`) are **illustrative architecture patterns**, not literal Drupal table definitions used by `dungeoncrawler_content`.
- Authoritative implemented schema lives in:
    - `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.install`
    - `dungeoncrawler_content_update_10001()` through `dungeoncrawler_content_update_10004()`
- Campaign/content/runtime separation described in this document is implemented by the module tables created in update hook `10004`.

## Design Philosophy: Hybrid Approach

For a Pathfinder 2E character management system with exponential growth potential, a **hybrid approach** combining relational tables with JSON storage provides the optimal balance of:

- **Performance**: Fast queries for common operations
- **Flexibility**: Easy addition of new game content
- **Data Integrity**: Enforcement of game rules
- **Scalability**: Handles growing player base and content
- **Maintainability**: Clear structure for developers

## Core Design Principles

### Drupal DungeonCrawler: Library vs Campaign vs Runtime
- **Library (authoritative content)**: Shared tables that ship with the module (`dungeoncrawler_content_registry`, `dungeoncrawler_content_loot_tables`, `dungeoncrawler_content_encounter_templates`) remain immutable and versionable.
- **Campaign copies (per-campaign edits)**: New campaign-scoped mirrors let each campaign fork library assets without mutating the base: `dc_campaign_content_registry`, `dc_campaign_loot_tables`, `dc_campaign_encounter_templates`, `dc_campaign_rooms`, `dc_campaign_dungeons`. Each row tracks `campaign_id` and optional `source_*` back to the library for provenance.
- **Runtime state (play session data)**: Active play is kept separate so resets/rollbacks do not affect definitions: `dc_campaign_encounter_instances`, `dc_campaign_room_states`, `dc_campaign_item_instances`, `dc_campaign_log`. Instances point to campaign-scoped definitions, not the library, to keep snapshots stable.
- **Upgrade path**: Update hook `dungeoncrawler_content_update_10004()` creates the campaign and runtime tables alongside existing 10001–10003 content installs.
- **Service entrypoint**: `dungeoncrawler_content.campaign_content` copies library rows into campaign tables on-demand (registry content, loot tables, encounter templates) and reads campaign-scoped JSON safely, so controllers should call it instead of touching library tables directly.

### When to Use Relational Tables
✅ **Use relational tables for:**
- Data that is frequently queried
- Data with clear, stable structure
- Data requiring JOINs across entities
- Data needing referential integrity
- Aggregations and analytics
- Search and filtering operations

### When to Use JSON
✅ **Use JSON storage for:**
- Highly variable nested structures
- Data rarely queried directly
- Frequently changing schemas
- Class-specific features that vary widely
- Temporary states and effects
- Configuration and settings

### When to Use Hybrid
✅ **Use hybrid (relational + JSON) for:**
- Core relational structure with flexible metadata
- Indexable primary attributes with detailed JSON payloads
- Versioned data where structure changes over time

---

## Database Schema: Relational Core

### 1. Users & Authentication

```sql
-- User accounts
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_email (email),
    INDEX idx_username (username)
);

-- User roles and permissions
CREATE TABLE user_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('player', 'gm', 'admin') NOT NULL,
    campaign_id BIGINT UNSIGNED NULL, -- NULL = global role
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_campaign_role (user_id, campaign_id, role)
);
```

### 2. Campaigns & Sessions

```sql
-- Campaigns (game instances)
CREATE TABLE campaigns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    gm_user_id BIGINT UNSIGNED NOT NULL,
    setting TEXT, -- setting description
    house_rules JSON, -- custom rules
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (gm_user_id) REFERENCES users(id),
    INDEX idx_gm (gm_user_id),
    INDEX idx_active (is_active)
);

-- Gaming sessions
CREATE TABLE sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    session_number INT UNSIGNED NOT NULL,
    session_date DATE NOT NULL,
    duration_minutes INT UNSIGNED,
    summary TEXT,
    notes TEXT,
    xp_awarded INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    UNIQUE KEY unique_campaign_session (campaign_id, session_number),
    INDEX idx_campaign_date (campaign_id, session_date)
);
```

### 3. Characters (Core Relational Data)

```sql
-- Character core attributes
CREATE TABLE characters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NULL, -- NULL = not in campaign yet
    
    -- Basic Info
    name VARCHAR(100) NOT NULL,
    level TINYINT UNSIGNED DEFAULT 1,
    experience_points INT UNSIGNED DEFAULT 0,
    
    -- Ancestry & Background & Class
    ancestry_id INT UNSIGNED NOT NULL,
    heritage_id INT UNSIGNED NULL,
    background_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    
    -- Ability Scores (current values)
    ability_str TINYINT UNSIGNED DEFAULT 10,
    ability_dex TINYINT UNSIGNED DEFAULT 10,
    ability_con TINYINT UNSIGNED DEFAULT 10,
    ability_int TINYINT UNSIGNED DEFAULT 10,
    ability_wis TINYINT UNSIGNED DEFAULT 10,
    ability_cha TINYINT UNSIGNED DEFAULT 10,
    
    -- Hit Points
    max_hp INT UNSIGNED NOT NULL,
    current_hp INT UNSIGNED NOT NULL,
    temp_hp INT UNSIGNED DEFAULT 0,
    
    -- Defenses
    armor_class TINYINT UNSIGNED NOT NULL,
    
    -- Meta
    alignment VARCHAR(20),
    deity VARCHAR(100),
    age TINYINT UNSIGNED,
    appearance TEXT,
    personality TEXT,
    
    -- Character state
    is_active BOOLEAN DEFAULT TRUE,
    is_dead BOOLEAN DEFAULT FALSE,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (ancestry_id) REFERENCES ancestries(id),
    FOREIGN KEY (heritage_id) REFERENCES heritages(id),
    FOREIGN KEY (background_id) REFERENCES backgrounds(id),
    FOREIGN KEY (class_id) REFERENCES classes(id),
    
    INDEX idx_user (user_id),
    INDEX idx_campaign (campaign_id),
    INDEX idx_level (level),
    INDEX idx_class (class_id)
);

-- Character ability score history (for level tracking)
CREATE TABLE character_ability_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    level_gained TINYINT UNSIGNED NOT NULL,
    ability_name ENUM('str', 'dex', 'con', 'int', 'wis', 'cha') NOT NULL,
    boost_value TINYINT DEFAULT 2, -- typically +2, could be +1 or flaw -2
    source VARCHAR(100), -- 'ancestry', 'background', 'class', 'level5', 'level10', etc.
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    INDEX idx_character_level (character_id, level_gained)
);
```

### 4. Game Content: Reference Tables

```sql
-- Ancestries
CREATE TABLE ancestries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    hp_bonus TINYINT UNSIGNED NOT NULL,
    size ENUM('Tiny', 'Small', 'Medium', 'Large', 'Huge', 'Gargantuan') NOT NULL,
    speed TINYINT UNSIGNED NOT NULL,
    ability_boosts JSON NOT NULL, -- ["str", "dex", "free"] format
    ability_flaws JSON, -- ["int"] format
    traits JSON, -- ["humanoid", "dwarf"]
    description TEXT,
    special_features JSON, -- darkvision, etc.
    source VARCHAR(50) DEFAULT 'Core Rulebook',
    INDEX idx_name (name)
);

-- Heritages (subancestries)
CREATE TABLE heritages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ancestry_id INT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    special_features JSON,
    source VARCHAR(50) DEFAULT 'Core Rulebook',
    FOREIGN KEY (ancestry_id) REFERENCES ancestries(id) ON DELETE CASCADE,
    INDEX idx_ancestry (ancestry_id)
);

-- Backgrounds
CREATE TABLE backgrounds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    ability_boosts JSON NOT NULL, -- boosts granted
    skill_trained VARCHAR(50), -- skill granted training in
    lore_skill VARCHAR(100), -- specific Lore
    feat_granted INT UNSIGNED, -- skill feat ID
    description TEXT,
    source VARCHAR(50) DEFAULT 'Core Rulebook',
    INDEX idx_name (name)
);

-- Classes
CREATE TABLE classes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    key_ability JSON NOT NULL, -- ["str", "dex"] - options
    hp_per_level TINYINT UNSIGNED NOT NULL,
    perception_rank ENUM('untrained', 'trained', 'expert', 'master', 'legendary') DEFAULT 'trained',
    
    -- Initial proficiencies
    fortitude_rank ENUM('untrained', 'trained', 'expert', 'master', 'legendary') NOT NULL,
    reflex_rank ENUM('untrained', 'trained', 'expert', 'master', 'legendary') NOT NULL,
    will_rank ENUM('untrained', 'trained', 'expert', 'master', 'legendary') NOT NULL,
    
    -- Skills
    skills_granted TINYINT UNSIGNED NOT NULL, -- number of trained skills
    
    -- Detailed progression stored as JSON
    advancement_table JSON NOT NULL, -- level-by-level features
    
    description TEXT,
    source VARCHAR(50) DEFAULT 'Core Rulebook',
    INDEX idx_name (name)
);
```

### 5. Proficiencies

```sql
-- Character proficiencies (skills, weapons, armor, etc.)
CREATE TABLE character_proficiencies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    proficiency_type ENUM('skill', 'weapon', 'armor', 'save', 'perception', 'spell_attack', 'spell_dc') NOT NULL,
    proficiency_name VARCHAR(100) NOT NULL, -- 'acrobatics', 'longsword', 'heavy_armor', etc.
    proficiency_rank ENUM('untrained', 'trained', 'expert', 'master', 'legendary') NOT NULL,
    
    -- When/how gained
    gained_at_level TINYINT UNSIGNED NOT NULL,
    source VARCHAR(100), -- 'class', 'background', 'feat:Power Attack', etc.
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_char_prof (character_id, proficiency_type, proficiency_name),
    INDEX idx_character (character_id),
    INDEX idx_type (proficiency_type)
);
```

### 6. Feats

```sql
-- Feat definitions (reference data)
CREATE TABLE feats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    feat_type ENUM('ancestry', 'class', 'general', 'skill') NOT NULL,
    level TINYINT UNSIGNED NOT NULL,
    
    -- Prerequisites
    prerequisites JSON, -- {"level": 3, "skills": ["acrobatics:trained"], "feats": [42, 18]}
    
    traits JSON, -- ["general", "skill"]
    description TEXT NOT NULL,
    special TEXT, -- special rules
    
    -- For filtering
    class_specific INT UNSIGNED NULL, -- NULL = any class, or class_id
    ancestry_specific INT UNSIGNED NULL,
    
    source VARCHAR(50) DEFAULT 'Core Rulebook',
    
    FOREIGN KEY (class_specific) REFERENCES classes(id),
    FOREIGN KEY (ancestry_specific) REFERENCES ancestries(id),
    
    INDEX idx_type_level (feat_type, level),
    INDEX idx_name (name)
);

-- Character feats (what feats each character has)
CREATE TABLE character_feats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    feat_id INT UNSIGNED NOT NULL,
    gained_at_level TINYINT UNSIGNED NOT NULL,
    feat_slot_type ENUM('ancestry', 'class', 'general', 'skill', 'bonus') NOT NULL,
    
    -- Feat choices (some feats have choices)
    feat_choices JSON, -- e.g., skill focus choice, weapon choice, etc.
    
    notes TEXT,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (feat_id) REFERENCES feats(id),
    
    INDEX idx_character (character_id),
    INDEX idx_feat (feat_id)
);
```

### 7. Spells & Spellcasting

```sql
-- Spell definitions (reference data)
CREATE TABLE spells (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    level TINYINT UNSIGNED NOT NULL, -- 0 = cantrip
    
    -- Traditions that can cast it
    traditions JSON NOT NULL, -- ["arcane", "divine", "occult", "primal"]
    
    -- Casting
    actions VARCHAR(20) NOT NULL, -- '1', '2', '3', 'reaction', 'free'
    components JSON, -- ["material", "somatic", "verbal"]
    
    -- Range & Area
    range VARCHAR(50), -- 'touch', '30 feet', 'unlimited'
    area VARCHAR(100), -- '20-foot burst', '15-foot cone'
    targets VARCHAR(100),
    
    -- Duration & Effects
    duration VARCHAR(100),
    saving_throw VARCHAR(50), -- 'basic Reflex', 'Will', etc.
    
    -- Description
    description TEXT NOT NULL,
    heightened JSON, -- heightened effects by level
    
    traits JSON, -- spell traits
    source VARCHAR(50) DEFAULT 'Core Rulebook',
    
    INDEX idx_name (name),
    INDEX idx_level (level),
    INDEX idx_traditions (traditions(255))
);

-- Character spell slots
CREATE TABLE character_spell_slots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    spell_level TINYINT UNSIGNED NOT NULL,
    total_slots TINYINT UNSIGNED NOT NULL,
    used_slots TINYINT UNSIGNED DEFAULT 0,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_char_level (character_id, spell_level),
    INDEX idx_character (character_id)
);

-- Character known/prepared spells
CREATE TABLE character_spells (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    spell_id INT UNSIGNED NOT NULL,
    spell_level TINYINT UNSIGNED NOT NULL, -- level at which they know it
    
    is_prepared BOOLEAN DEFAULT FALSE, -- for prepared casters
    is_signature BOOLEAN DEFAULT FALSE, -- for spontaneous casters
    is_innate BOOLEAN DEFAULT FALSE,
    
    -- For prepared casters: which slot is this prepared in
    prepared_slot_id BIGINT UNSIGNED NULL,
    
    source VARCHAR(100), -- 'class', 'feat', 'item'
    learned_at_level TINYINT UNSIGNED,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (spell_id) REFERENCES spells(id),
    
    INDEX idx_character (character_id),
    INDEX idx_spell (spell_id),
    INDEX idx_prepared (character_id, is_prepared)
);
```

### 8. Equipment & Items

```sql
-- Item definitions (reference data)
CREATE TABLE items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    item_type ENUM('weapon', 'armor', 'shield', 'consumable', 'magic', 'adventuring_gear', 'treasure') NOT NULL,
    level TINYINT UNSIGNED DEFAULT 0,
    price_in_cp INT UNSIGNED DEFAULT 0, -- price in copper pieces
    bulk DECIMAL(3,1) DEFAULT 0.0, -- L = 0.1
    
    -- Weapon/Armor specific
    weapon_stats JSON, -- damage, traits, group, range
    armor_stats JSON, -- AC bonus, Dex cap, check penalty, speed penalty
    
    traits JSON,
    description TEXT,
    special TEXT,
    
    rarity ENUM('common', 'uncommon', 'rare', 'unique') DEFAULT 'common',
    source VARCHAR(50) DEFAULT 'Core Rulebook',
    
    INDEX idx_name (name),
    INDEX idx_type (item_type),
    INDEX idx_rarity (rarity)
);

-- Character inventory
CREATE TABLE character_inventory (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED DEFAULT 1,
    
    is_equipped BOOLEAN DEFAULT FALSE,
    is_invested BOOLEAN DEFAULT FALSE, -- for magic items
    
    -- Equipment slots ('worn_armor', 'main_hand', 'off_hand', 'worn_shield', etc.)
    equipped_slot VARCHAR(50) NULL,
    
    -- Item-specific state
    current_charges INT DEFAULT NULL, -- for charged items
    item_state JSON, -- flexible storage for item-specific data
    
    notes TEXT,
    acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id),
    
    INDEX idx_character (character_id),
    INDEX idx_equipped (character_id, is_equipped)
);
```

### 9. Conditions & Effects (Active State)

```sql
-- Active conditions on characters
CREATE TABLE character_conditions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    condition_name VARCHAR(50) NOT NULL, -- 'frightened', 'prone', 'dying', etc.
    condition_value TINYINT DEFAULT NULL, -- for valued conditions (frightened 2)
    
    source VARCHAR(100), -- 'spell:Fear', 'creature:Goblin Warrior', etc.
    duration_type ENUM('rounds', 'minutes', 'hours', 'unlimited', 'until_saved') NOT NULL,
    duration_remaining INT UNSIGNED NULL,
    
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    INDEX idx_character (character_id),
    INDEX idx_active (character_id, expires_at)
);

-- Active buffs/debuffs with specific modifiers
CREATE TABLE character_effects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    effect_name VARCHAR(100) NOT NULL,
    effect_type ENUM('buff', 'debuff', 'neutral') NOT NULL,
    
    -- What it modifies
    modifiers JSON NOT NULL, -- [{"type": "status", "stat": "attack", "value": 2}]
    
    source VARCHAR(100), -- 'spell:Bless', 'item:Magic Sword+1'
    duration_rounds INT UNSIGNED NULL,
    
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    INDEX idx_character (character_id),
    INDEX idx_active (character_id, expires_at)
);
```

### 10. Combat Encounters

```sql
-- Encounter tracking
CREATE TABLE encounters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NULL,
    encounter_name VARCHAR(255),
    difficulty ENUM('trivial', 'low', 'moderate', 'severe', 'extreme') NOT NULL,
    
    status ENUM('planned', 'active', 'completed') DEFAULT 'planned',
    
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    
    notes TEXT,
    
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL,
    
    INDEX idx_campaign (campaign_id),
    INDEX idx_status (status)
);

-- Initiative tracking
CREATE TABLE encounter_initiative (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    encounter_id BIGINT UNSIGNED NOT NULL,
    
    -- Either character OR monster
    character_id BIGINT UNSIGNED NULL,
    creature_name VARCHAR(100) NULL, -- for non-PC creatures
    creature_type VARCHAR(50) NULL, -- 'Goblin Warrior', etc.
    
    initiative_roll TINYINT UNSIGNED NOT NULL,
    initiative_modifier TINYINT NOT NULL,
    initiative_total TINYINT NOT NULL,
    
    -- Current turn state
    is_current_turn BOOLEAN DEFAULT FALSE,
    actions_remaining TINYINT UNSIGNED DEFAULT 3,
    has_reaction BOOLEAN DEFAULT TRUE,
    
    -- HP tracking for enemies
    current_hp INT NULL,
    max_hp INT NULL,
    
    -- Status
    is_defeated BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (encounter_id) REFERENCES encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    
    INDEX idx_encounter (encounter_id),
    INDEX idx_initiative (encounter_id, initiative_total DESC)
);
```

---

## JSON Storage Patterns

### Character Extended Data (JSON Column)

```sql
-- Add to characters table
ALTER TABLE characters ADD COLUMN extended_data JSON;

-- Index specific JSON paths for common queries
CREATE INDEX idx_char_json_class_features ON characters(
    (CAST(extended_data->'$.class_features' AS CHAR(255)) ARRAY)
);
```

**Example JSON structure:**

```json
{
  "class_features": [
    {
      "name": "Rage",
      "level_gained": 1,
      "uses_per_day": 4,
      "current_uses": 2,
      "description": "..."
    }
  ],
  "languages": ["Common", "Dwarven", "Orcish"],
  "senses": {
    "darkvision": 60,
    "low_light_vision": false
  },
  "movement": {
    "land": 25,
    "climb": 0,
    "swim": 0,
    "fly": 0
  },
  "size_category": "Medium",
  "creature_type": "Humanoid",
  "notes": {
    "backstory": "...",
    "goals": "...",
    "relationships": []
  },
  "calculated_stats": {
    "carry_capacity": 10,
    "current_bulk": 5.5,
    "fortitude_total": 8,
    "reflex_total": 6,
    "will_total": 4
  }
}
```

---

## Indexing Strategy

### Critical Indexes for Performance

```sql
-- Character lookups
CREATE INDEX idx_char_user_campaign ON characters(user_id, campaign_id);
CREATE INDEX idx_char_active ON characters(campaign_id, is_active) WHERE is_active = TRUE;

-- Proficiency lookups
CREATE INDEX idx_prof_lookup ON character_proficiencies(character_id, proficiency_type, proficiency_name);

-- Spell lookups
CREATE INDEX idx_spell_tradition_level ON spells(level, (CAST(traditions AS CHAR(255))));

-- Inventory by character
CREATE INDEX idx_inventory_equipped ON character_inventory(character_id, is_equipped);

-- Active conditions
CREATE INDEX idx_conditions_active ON character_conditions(character_id) 
    WHERE expires_at IS NULL OR expires_at > NOW();

-- Full-text search
CREATE FULLTEXT INDEX ft_spell_description ON spells(name, description);
CREATE FULLTEXT INDEX ft_feat_description ON feats(name, description);
CREATE FULLTEXT INDEX ft_item_name ON items(name, description);
```

---

## Data Access Patterns

### Common Query Examples

#### 1. Load Full Character Sheet

```sql
-- Main character data
SELECT c.*, 
       a.name as ancestry_name,
       b.name as background_name,
       cl.name as class_name
FROM characters c
JOIN ancestries a ON c.ancestry_id = a.id
JOIN backgrounds b ON c.background_id = b.id
JOIN classes cl ON c.class_id = cl.id
WHERE c.id = ?;

-- Proficiencies
SELECT * FROM character_proficiencies 
WHERE character_id = ? 
ORDER BY proficiency_type, proficiency_name;

-- Feats
SELECT cf.*, f.name, f.description 
FROM character_feats cf
JOIN feats f ON cf.feat_id = f.id
WHERE cf.character_id = ?
ORDER BY cf.gained_at_level;

-- Inventory
SELECT ci.*, i.name, i.item_type, i.bulk
FROM character_inventory ci
JOIN items i ON ci.item_id = i.id
WHERE ci.character_id = ?;

-- Active conditions
SELECT * FROM character_conditions
WHERE character_id = ?
  AND (expires_at IS NULL OR expires_at > NOW());
```

#### 2. Calculate Character Stats

```sql
-- Calculate proficiency bonus
SELECT proficiency_rank,
       CASE proficiency_rank
           WHEN 'untrained' THEN 0 + ?level
           WHEN 'trained' THEN 2 + ?level
           WHEN 'expert' THEN 4 + ?level
           WHEN 'master' THEN 6 + ?level
           WHEN 'legendary' THEN 8 + ?level
       END as proficiency_bonus
FROM character_proficiencies
WHERE character_id = ? AND proficiency_name = ?;
```

#### 3. Search Available Feats for Character

```sql
SELECT f.*
FROM feats f
WHERE f.level <= ?character_level
  AND f.feat_type = ?feat_type
  AND (f.class_specific IS NULL OR f.class_specific = ?character_class)
  AND f.id NOT IN (
      SELECT feat_id FROM character_feats WHERE character_id = ?
  )
  -- Check prerequisites (application logic needed for complex checks)
ORDER BY f.level, f.name;
```

#### 4. Get Available Spells for Character

```sql
SELECT s.*
FROM spells s
WHERE JSON_CONTAINS(s.traditions, JSON_QUOTE(?tradition))
  AND s.level <= ?max_spell_level
  AND s.id NOT IN (
      SELECT spell_id FROM character_spells WHERE character_id = ?
  )
ORDER BY s.level, s.name;
```

---

## Scalability Considerations

### Horizontal Scaling

**Shard by Campaign:**
```
- Campaign 1-1000: Shard 1
- Campaign 1001-2000: Shard 2
```

**Read Replicas:**
- Master: Writes
- Replica 1: Character sheet reads
- Replica 2: Reference data reads (spells, feats, items)

### Caching Strategy

**Redis Cache Layers:**

```
# Character sheet cache (10 min TTL)
character:{id}:sheet

# Reference data (1 hour TTL)  
spells:tradition:{tradition}:level:{level}
feats:type:{type}:level:{level}

# Active encounters (realtime)
encounter:{id}:initiative
encounter:{id}:current_turn
```

### Archival Strategy

```sql
-- Archive completed campaigns
CREATE TABLE campaigns_archive (
    /* same structure as campaigns */
) ENGINE=ARCHIVE;

-- Move old data after campaign completion
INSERT INTO campaigns_archive SELECT * FROM campaigns WHERE id = ?;
```

---

## Version Control & Migrations

### Data Versioning

```sql
-- Track schema versions
CREATE TABLE schema_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(20) NOT NULL,
    description TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_version (version)
);

-- Track game content versions (books/errata)
CREATE TABLE content_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('spell', 'feat', 'item', 'ancestry', 'class') NOT NULL,
    content_id INT UNSIGNED NOT NULL,
    version VARCHAR(20) NOT NULL,
    changes JSON,
    effective_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Summary: Recommended Approach

### Relational Tables (70% of data)
- ✅ **User management**: Users, roles, authentication
- ✅ **Campaign management**: Campaigns, sessions
- ✅ **Character core**: Basic stats, abilities, HP, level
- ✅ **Reference data**: Ancestries, classes, backgrounds, spells, feats, items
- ✅ **Proficiencies**: Skills, weapons, armor, saves
- ✅ **Relationships**: Character feats, character spells, inventory
- ✅ **Combat state**: Encounters, initiative, conditions

### JSON Storage (30% of data)
- ✅ **Class features**: Variable by class, level-dependent details
- ✅ **Feat choices**: When feats have selectable options
- ✅ **Item state**: Charges, attunement, configuration
- ✅ **Calculated stats**: Derived values that change frequently
- ✅ **Metadata**: Notes, backstory, relationships
- ✅ **House rules**: Campaign-specific modifications

### Key Benefits of This Approach

1. **Query Performance**: Fast access to common data via indexes
2. **Data Integrity**: Foreign keys enforce rules and relationships
3. **Flexibility**: JSON handles variable/evolving structures
4. **Analytics**: Easy to aggregate and report on relational data
5. **Maintenance**: Clear structure makes debugging easier
6. **Scalability**: Can shard, archive, and cache effectively
7. **Future-Proof**: Add new game content without schema changes

This hybrid design provides the optimal balance for an exponentially growing Pathfinder 2E system.
