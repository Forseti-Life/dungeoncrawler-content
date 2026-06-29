# PR-01: Character Creation System Implementation

## Verification Notes (2026-02-18)

- This is an implementation design spec, not a guarantee that every endpoint/class below currently exists as described.
- Current runtime character creation flow is primarily implemented through `CharacterCreationStepController` routes in `dungeoncrawler_content.routing.yml`.
- Treat this document as target-state guidance; verify active behavior against module routes/controllers before using as operational reference.

## Overview
Implement a multi-step character creation wizard that guides players through the Pathfinder 2E character creation process (10 steps). The system must enforce game rules, calculate derived statistics, and persist character data.

## Reference Documentation
Additional detailed game mechanics documentation is available in the `reference documentation/` subdirectory:
- PF2E Core Rulebook - Fourth Printing.txt
- PF2E Advanced Players Guide.txt
- PF2E Gods and Magic.txt
- Other supplementary rulebooks

These reference materials provide comprehensive rules for ancestries, classes, backgrounds, feats, and equipment that should be consulted during implementation.

## Controller Design

### CharacterCreationController

**Purpose**: Manage the character creation workflow and wizard UI

**Route**: `/characters/create`

**Key Methods**:

#### `index()`
- Display character creation wizard (single-page application)
- Load initial form with step 1 (concept creation)
- Returns: Character creation form view

#### `start(Request $request)`
- Initialize new character creation session
- Store session data for wizard state
- Parameters: `user_id`, `campaign_id` (optional)
- Returns: JSON with `session_id` and initial state

#### `saveStep(Request $request, $step)`
- Save progress for specific creation step (1-10)
- Validate step-specific data
- Parameters: `session_id`, `step`, JSON data payload
- Returns: Validation result and next step data

#### `preview(Request $request)`
- Generate character preview with all current selections
- Calculate all derived statistics
- Parameters: `session_id`
- Returns: Complete character sheet preview

#### `finalize(Request $request)`
- Complete character creation
- Persist to database
- Clear session data
- Parameters: `session_id`
- Returns: Character ID and redirect URL

#### `validateAbilityBoosts(Request $request)`
- Validate ability boost selections per step rules
- Ensure no double-boosting same ability in one step
- Parameters: `step`, `selected_boosts[]`, `current_abilities`
- Returns: Validation result

#### `getAncestryOptions()`
- Fetch available ancestries with traits
- Returns: JSON list of ancestries with HP, speed, languages, traits

#### `getBackgroundOptions()`
- Fetch available backgrounds
- Returns: JSON list with ability boosts, skill training, lore

#### `getClassOptions()`
- Fetch available classes with key abilities
- Returns: JSON list with class features, proficiencies, HP

#### `getHeritage Options($ancestry_id)`
- Fetch heritage options for selected ancestry
- Parameters: `ancestry_id`
- Returns: JSON list of heritage options

#### `getFeatOptions($type, $level, $class_id)`
- Fetch available feats by type and level
- Parameters: `type` (ancestry/class/skill), `level`, `class_id`
- Returns: Filtered feat list with prerequisites

#### `getEquipmentCatalog()`
- Fetch starting equipment options
- Filter by starting GP budget (15 gp)
- Returns: JSON equipment catalog with prices, bulk

## Schema Design

### character_creation_sessions

```sql
CREATE TABLE character_creation_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_token VARCHAR(64) UNIQUE NOT NULL,
    current_step TINYINT UNSIGNED DEFAULT 1,
    wizard_data JSON NOT NULL, -- All selections across steps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL, -- Session expiry (24 hours)
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_token (user_id, session_token),
    INDEX idx_expires (expires_at)
);
```

**wizard_data JSON Structure**:
```json
{
  "step_1_concept": {
    "character_name": "Gribbles Rindsworth",
    "personality": "Cheese-obsessed rogue",
    "backstory": "...",
    "notes": "..."
  },
  "step_2_ability_base": {
    "str": 10, "dex": 10, "con": 10,
    "int": 10, "wis": 10, "cha": 10
  },
  "step_3_ancestry": {
    "ancestry_id": 4,
    "heritage_id": 12,
    "ability_boosts": ["dex", "cha"],
    "ability_flaws": ["str"],
    "ancestry_feat_id": 87
  },
  "step_4_background": {
    "background_id": 23,
    "ability_boosts": ["dex", "int"],
    "skill_trained": "stealth",
    "lore_skill": "cheese_lore",
    "skill_feat_id": 156
  },
  "step_5_class": {
    "class_id": 10,
    "key_ability": "dex",
    "class_feat_id": 234
  },
  "step_6_final_abilities": {
    "str": 8, "dex": 18, "con": 12,
    "int": 14, "wis": 10, "cha": 14,
    "free_boosts": ["dex", "int", "con", "cha"]
  },
  "step_7_class_details": {
    "deity": null,
    "spell_selections": [],
    "initial_proficiencies": {...}
  },
  "step_8_equipment": {
    "purchased_items": [
      {"item_id": 45, "quantity": 1, "cost_gp": 8},
      {"item_id": 67, "quantity": 10, "cost_gp": 0.5}
    ],
    "total_spent_gp": 13.5,
    "remaining_gp": 1.5
  },
  "step_9_modifiers": {
    "perception": 5,
    "fortitude": 3,
    "reflex": 7,
    "will": 4,
    "melee_strikes": [...],
    "ranged_strikes": [...]
  },
  "step_10_details": {
    "alignment": "CN",
    "age": 23,
    "gender": "Male",
    "appearance": "...",
    "campaign_id": 5
  }
}
```

### characters (Extended from schema-design.md)

**Additional Fields Needed**:
```sql
-- Add to base characters table
gender VARCHAR(50),
size ENUM('tiny', 'small', 'medium', 'large', 'huge', 'gargantuan') DEFAULT 'medium',
speed_base TINYINT UNSIGNED DEFAULT 25,
speed_current TINYINT UNSIGNED DEFAULT 25,
languages JSON, -- ["Common", "Goblin", "Draconic"]
starting_gp DECIMAL(10,2) DEFAULT 15.00,
current_gp DECIMAL(10,2) DEFAULT 15.00,
character_concept TEXT,
```

### game_ancestries

```sql
CREATE TABLE game_ancestries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    hit_points TINYINT UNSIGNED NOT NULL,
    size ENUM('small', 'medium') NOT NULL,
    speed TINYINT UNSIGNED NOT NULL,
    ability_boosts JSON, -- ["str", "con", "free"] (free = player choice)
    ability_flaws JSON, -- ["int"]
    traits JSON, -- ["humanoid", "goblin"]
    languages JSON, -- ["common", "goblin"]
    bonus_languages JSON, -- Available with high Int
    special_abilities JSON,
    source_book VARCHAR(50) DEFAULT 'Core Rulebook',
    INDEX idx_name (name)
);
```

### game_heritages

```sql
CREATE TABLE game_heritages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ancestry_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    special_abilities JSON,
    traits JSON,
    source_book VARCHAR(50),
    FOREIGN KEY (ancestry_id) REFERENCES game_ancestries(id),
    INDEX idx_ancestry (ancestry_id)
);
```

### game_backgrounds

```sql
CREATE TABLE game_backgrounds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    ability_boosts JSON, -- ["str", "dex"] or ["free", "free"]
    skill_trained VARCHAR(50) NOT NULL, -- "athletics"
    lore_skill VARCHAR(100) NOT NULL, -- "mining_lore"
    skill_feat_text TEXT, -- Description of granted feat
    source_book VARCHAR(50) DEFAULT 'Core Rulebook',
    INDEX idx_name (name)
);
```

### game_classes

```sql
CREATE TABLE game_classes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    key_ability JSON, -- ["str", "dex"] (player choice if multiple)
    hp_per_level TINYINT UNSIGNED NOT NULL,
    perception_proficiency ENUM('trained', 'expert', 'master', 'legendary') DEFAULT 'trained',
    fortitude_proficiency ENUM('trained', 'expert', 'master', 'legendary'),
    reflex_proficiency ENUM('trained', 'expert', 'master', 'legendary'),
    will_proficiency ENUM('trained', 'expert', 'master', 'legendary'),
    skill_increases_base TINYINT UNSIGNED, -- Skills trained at level 1
    class_features JSON, -- Array of features by level
    proficiencies JSON, -- Weapon, armor, etc.
    spellcasting_type ENUM('prepared', 'spontaneous', 'focus', 'none') DEFAULT 'none',
    spell_tradition ENUM('arcane', 'divine', 'occult', 'primal', 'none') DEFAULT 'none',
    source_book VARCHAR(50) DEFAULT 'Core Rulebook',
    INDEX idx_name (name)
);
```

### game_feats

```sql
CREATE TABLE game_feats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    feat_type ENUM('ancestry', 'class', 'skill', 'general') NOT NULL,
    level TINYINT UNSIGNED NOT NULL,
    ancestry_id INT UNSIGNED NULL, -- For ancestry feats
    class_id INT UNSIGNED NULL, -- For class feats
    prerequisites JSON, -- ["trained in athletics", "strength 14"]
    traits JSON, -- ["goblin", "attack"]
    action_type ENUM('action', 'reaction', 'free', 'activity', 'passive') DEFAULT 'passive',
    actions TINYINT UNSIGNED, -- 1, 2, or 3
    description TEXT,
    special_text TEXT,
    source_book VARCHAR(50) DEFAULT 'Core Rulebook',
    
    FOREIGN KEY (ancestry_id) REFERENCES game_ancestries(id),
    FOREIGN KEY (class_id) REFERENCES game_classes(id),
    INDEX idx_type_level (feat_type, level),
    INDEX idx_ancestry (ancestry_id),
    INDEX idx_class (class_id)
);
```

### character_feats

```sql
CREATE TABLE character_feats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    feat_id INT UNSIGNED NOT NULL,
    feat_type ENUM('ancestry', 'class', 'skill', 'general') NOT NULL,
    gained_at_level TINYINT UNSIGNED NOT NULL,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (feat_id) REFERENCES game_feats(id),
    INDEX idx_character (character_id),
    UNIQUE KEY unique_character_feat (character_id, feat_id)
);
```

### game_equipment

```sql
CREATE TABLE game_equipment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    item_type ENUM('weapon', 'armor', 'shield', 'adventuring_gear', 'consumable', 'treasure') NOT NULL,
    price_gp DECIMAL(10, 2) NOT NULL,
    level TINYINT UNSIGNED DEFAULT 0,
    bulk DECIMAL(3, 1) DEFAULT 0, -- L = 0.1
    description TEXT,
    traits JSON,
    
    -- Weapon specific
    damage_dice VARCHAR(20), -- "1d6"
    damage_type VARCHAR(20), -- "slashing"
    weapon_group VARCHAR(50),
    weapon_category ENUM('simple', 'martial', 'advanced'),
    
    -- Armor specific
    armor_category ENUM('unarmored', 'light', 'medium', 'heavy'),
    ac_bonus TINYINT UNSIGNED,
    dex_cap TINYINT,
    check_penalty TINYINT,
    speed_penalty TINYINT UNSIGNED DEFAULT 0,
    strength_requirement TINYINT UNSIGNED,
    
    source_book VARCHAR(50) DEFAULT 'Core Rulebook',
    INDEX idx_type (item_type),
    INDEX idx_name (name)
);
```

### character_inventory

```sql
CREATE TABLE character_inventory (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    equipment_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED DEFAULT 1,
    equipped BOOLEAN DEFAULT FALSE,
    invested BOOLEAN DEFAULT FALSE, -- For magic items
    notes TEXT,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (equipment_id) REFERENCES game_equipment(id),
    INDEX idx_character (character_id),
    INDEX idx_equipped (character_id, equipped)
);
```

## Process Flow

### High-Level Wizard Flow

```
User clicks "Create Character"
    ↓
CharacterCreationController::start()
    ↓
Create session with wizard_data JSON
    ↓
Step 1: Character Concept (free-form entry)
Save → CharacterCreationController::saveStep(1)
    ↓
Step 2: Base Ability Scores (all start at 10)
Save → CharacterCreationController::saveStep(2)
    ↓
Step 3: Select Ancestry
    Load: getAncestryOptions()
    Load: getHeritageOptions($ancestry_id)
    Load: getFeatOptions('ancestry', 1, null)
    Apply: ability boosts/flaws
    Validate: validateAbilityBoosts($step=3)
Save → CharacterCreationController::saveStep(3)
    ↓
Step 4: Pick Background
    Load: getBackgroundOptions()
    Load: getFeatOptions('skill', 1, null)
    Apply: 2 ability boosts, skill training
    Validate: validateAbilityBoosts($step=4)
Save → CharacterCreationController::saveStep(4)
    ↓
Step 5: Choose Class
    Load: getClassOptions()
    Load: getFeatOptions('class', 1, $class_id)
    Apply: key ability boost
    Validate: validateAbilityBoosts($step=5)
Save → CharacterCreationController::saveStep(5)
    ↓
Step 6: Final Ability Scores
    Apply: 4 free ability boosts (each to different ability)
    Validate: validateAbilityBoosts($step=6)
    Calculate: final ability scores and modifiers
Save → CharacterCreationController::saveStep(6)
    ↓
Step 7: Record Class Details
    Calculate: HP (ancestry + class + Con mod)
    Load: proficiencies from class
    Load: spell options (if caster class)
    Calculate: initial proficiency bonuses
Save → CharacterCreationController::saveStep(7)
    ↓
Step 8: Buy Equipment
    Load: getEquipmentCatalog()
    Track: spending vs 15 gp budget
    Validate: total cost ≤ 15 gp
    Calculate: bulk carried
Save → CharacterCreationController::saveStep(8)
    ↓
Step 9: Calculate Modifiers
    Calculate: perception
    Calculate: saving throws (Fort, Ref, Will)
    Calculate: melee attack bonuses
    Calculate: ranged attack bonuses
    Calculate: AC from armor/dex
    Calculate: skill modifiers
Save → CharacterCreationController::saveStep(9)
    ↓
Step 10: Finishing Details
    Enter: alignment, age, appearance
    Select: campaign (optional)
Save → CharacterCreationController::saveStep(10)
    ↓
Preview Character Sheet
CharacterCreationController::preview()
    ↓
User confirms "Create Character"
    ↓
CharacterCreationController::finalize()
    INSERT INTO characters
    INSERT INTO character_feats (ancestry, class, skill feats)
    INSERT INTO character_inventory (equipment)
    INSERT INTO character_proficiencies
    DELETE character_creation_session
    ↓
Redirect to character sheet view
```

## Functions Required

### CharacterCalculationService

**Purpose**: Centralized calculation logic for character statistics

#### `calculateAbilityModifier($score)`
- Formula: `floor(($score - 10) / 2)`
- Parameters: ability score value
- Returns: modifier (-5 to +10 typically)

#### `calculateMaxHP($ancestry_hp, $class_hp_per_level, $level, $con_modifier)`
- Formula: `$ancestry_hp + ($class_hp_per_level + $con_modifier) * $level`
- Parameters: ancestry HP, class HP/level, character level, Con modifier
- Returns: max HP total

#### `calculateProficiencyBonus($rank, $level)`
- Formula: `$rank_value + $level`
- Rank values: untrained=0, trained=2, expert=4, master=6, legendary=8
- Parameters: proficiency rank, character level
- Returns: proficiency bonus

#### `calculatePerception($level, $proficiency_rank, $wis_modifier, $bonuses[])`
- Formula: `proficiency_bonus + $wis_modifier + sum($bonuses)`
- Parameters: level, rank, wisdom mod, additional bonuses array
- Returns: perception modifier

#### `calculateSavingThrow($level, $proficiency_rank, $ability_modifier, $bonuses[])`
- Formula: `proficiency_bonus + $ability_modifier + sum($bonuses)`
- Parameters: level, rank, ability mod, bonuses
- Returns: saving throw modifier

#### `calculateArmorClass($base_ac, $dex_modifier, $armor_bonus, $proficiency_bonus, $dex_cap)`
- Formula: `10 + min($dex_modifier, $dex_cap) + $armor_bonus + $proficiency_bonus`
- Parameters: base AC, dex mod, armor bonus, proficiency, dex cap
- Returns: total AC

#### `calculateAttackBonus($level, $proficiency_rank, $ability_modifier, $item_bonus)`
- Formula: `proficiency_bonus + $ability_modifier + $item_bonus`
- Parameters: level, weapon proficiency, relevant ability, weapon bonus
- Returns: attack roll modifier

#### `calculateSkillModifier($level, $proficiency_rank, $ability_modifier, $bonuses[])`
- Formula: `proficiency_bonus + $ability_modifier + sum($bonuses)`
- Parameters: level, skill proficiency, ability mod, bonuses
- Returns: skill check modifier

#### `applyAbilityBoost($current_score)`
- Formula: `$current_score + 2`
- Parameters: current ability score
- Returns: new ability score

#### `applyAbilityFlaw($current_score)`
- Formula: `$current_score - 2`
- Parameters: current ability score
- Returns: new ability score

#### `validateAbilityBoostRules($step, $current_abilities, $new_boosts[], $session_data)`
- Validates no double-boosting in same step
- Checks free boost count matches rules (step 6 = 4 boosts)
- Parameters: step number, current scores, proposed boosts, session history
- Returns: validation result with errors if any

#### `calculateBulkCarried($inventory[])`
- Sums bulk of all carried items
- Handles Light bulk (L = 0.1)
- Parameters: array of inventory items with quantities
- Returns: total bulk carried

#### `calculateBulkLimit($str_modifier)`
- Formula: `5 + $str_modifier` (encumbered), `10 + $str_modifier` (max)
- Parameters: Strength modifier
- Returns: [`encumbered_threshold`, `max_bulk`]

## Data Requirements Per Function

### For Character Creation Wizard:

**Step 1: Concept**
- Input: name, personality, backstory (user text input)
- Data: None required from DB

**Step 2: Base Abilities**
- Input: None (all default to 10)
- Data: None required

**Step 3: Ancestry Selection**
- Load: `game_ancestries` table (all rows)
- Load: `game_heritages` WHERE ancestry_id = selected_ancestry
- Load: `game_feats` WHERE feat_type = 'ancestry' AND level = 1 AND ancestry_id = selected_ancestry
- Apply: ability boosts/flaws to ability scores

**Step 4: Background Selection**
- Load: `game_backgrounds` table (all rows)
- Load: `game_feats` WHERE feat_type = 'skill' AND level = 1
- Apply: 2 ability boosts to selected abilities

**Step 5: Class Selection**
- Load: `game_classes` table (all rows)
- Load: `game_feats` WHERE feat_type = 'class' AND level = 1 AND class_id = selected_class
- Apply: key ability boost

**Step 6: Free Ability Boosts**
- Data: Current ability scores from session
- Apply: 4 free boosts (each to different ability)
- Calculate: final ability scores and modifiers

**Step 7: Class Details**
- Load: `game_classes` proficiencies JSON for selected class
- Load: `game_spells` if class has spellcasting (reference table needed)
- Calculate: max HP = ancestry_hp + (class_hp + con_mod) * 1

**Step 8: Equipment**
- Load: `game_equipment` table (filter by starting gear suitability)
- Track: total cost vs 15 gp budget
- Calculate: bulk carried

**Step 9: Modifiers**
- Calculate: All derived stats using CharacterCalculationService
- Data: ability scores, proficiencies, class features

**Step 10: Details**
- Input: alignment, age, appearance, campaign selection
- Load: `campaigns` table WHERE is_active = 1 (for dropdown)

### For Finalization:

**Persist Character**
- INSERT: `characters` with all core attributes
- INSERT: `character_feats` (3-4 feat entries: ancestry, class, skill, general if applicable)
- INSERT: `character_inventory` (all purchased items)
- INSERT: `character_proficiencies` (skills, weapons, armor, saves)

**Character Proficiencies Table** (additional schema needed):
```sql
CREATE TABLE character_proficiencies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    proficiency_type ENUM('skill', 'weapon', 'armor', 'save', 'perception', 'spell_attack', 'spell_dc') NOT NULL,
    proficiency_name VARCHAR(50) NOT NULL,
    proficiency_rank ENUM('untrained', 'trained', 'expert', 'master', 'legendary') NOT NULL,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    INDEX idx_character (character_id),
    INDEX idx_type (character_id, proficiency_type)
);
```

## API Endpoints Summary

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/characters/create/start` | Initialize wizard session |
| POST | `/api/characters/create/step/{step}` | Save step data |
| GET | `/api/characters/create/preview` | Get character preview |
| POST | `/api/characters/create/finalize` | Complete and persist character |
| GET | `/api/characters/create/ancestries` | List ancestries |
| GET | `/api/characters/create/heritages/{ancestry_id}` | List heritages for ancestry |
| GET | `/api/characters/create/backgrounds` | List backgrounds |
| GET | `/api/characters/create/classes` | List classes |
| GET | `/api/characters/create/feats` | List feats (filtered by type, level, class) |
| GET | `/api/characters/create/equipment` | List starting equipment |
| POST | `/api/characters/create/validate/boosts` | Validate ability boost selection |

## Success Criteria

- ✅ All 10 steps of character creation wizard functional
- ✅ Ability boost rules enforced (no double-boosting per step)
- ✅ Users can save progress and return to wizard later
- ✅ Character preview shows all calculated statistics accurately
- ✅ Finalization creates complete character ready for play
- ✅ Equipment budget enforced (≤ 15 gp)
- ✅ All game rule calculations match PF2E rules exactly
- ✅ Session data expires after 24 hours
- ✅ Database seeded with Core Rulebook content (ancestries, classes, backgrounds, feats, equipment)

## Future Enhancements

- Multi-class support (dual-class characters)
- Advanced Player Guide content (additional ancestries, classes)
- Character import from JSON
- Character templates for quick creation
- Random character generation
- Ancestry/class combo recommendations for new players
