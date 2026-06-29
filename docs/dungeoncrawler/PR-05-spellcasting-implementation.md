# PR-05: Spellcasting System Implementation

## Verification Notes (2026-02-18)

- This file is implementation design, not an assertion that all listed routes/services are currently live.
- Current spellcasting-related runtime behavior is primarily represented through character state APIs and service logic, not the full standalone route surface described below.
- Confirm active behavior via current routing/controller code before using as operational documentation.

## Overview
Implement a comprehensive spellcasting system supporting Pathfinder 2E's four magical traditions (Arcane, Divine, Occult, Primal), spell slots, prepared vs spontaneous casting, spell components, concentration, heightening, and spell resolution. The system must integrate with character classes, combat encounters, and action economy.

## Reference Documentation
Additional detailed game mechanics documentation is available in the `reference documentation/` subdirectory:
- PF2E Core Rulebook - Fourth Printing.txt
- PF2E Secrets of Magic.txt
- PF2E Gods and Magic.txt
- PF2E Advanced Players Guide.txt
- Other supplementary rulebooks

These reference materials provide comprehensive rules for all spells, traditions, casting methods, spell schools, and magical mechanics that should be consulted during implementation.

## Controller Design

### SpellcastingController

**Purpose**: Manage spell lists, preparation, and casting

**Route Base**: `/spellcasting`

**Key Methods**:

#### `index($character_id)`
- Display character's spellbook/repertoire
- Show available spell slots
- Parameters: `character_id`
- Returns: Spellcasting interface view

#### `prepareSpells(Request $request)`
- Daily spell preparation for prepared casters
- Parameters: `character_id`, `spell_slots_preparation[]`
- Returns: Updated spell slots

#### `castSpell(Request $request)`
- Execute spell casting
- Validate components, slots, range
- Parameters: `combat_id`, `character_id`, `spell_id`, `spell_level`, `targets[]`, `heighten_level`
- Returns: Spell casting result

#### `makeSpellAttackRoll(Request $request)`
- Roll spell attack vs AC
- Parameters: `caster_id`, `target_id`, `spell_id`, `spell_level`
- Returns: Attack result and damage prompt

#### `resolveSpellSave(Request $request)`
- Target rolls save vs spell DC
- Determine degree of success
- Parameters: `spell_id`, `caster_spell_dc`, `target_id`, `save_type`
- Returns: Save result and effect application

#### `sustainSpell(Request $request)`
- Use Sustain a Spell action
- Extend spell duration by 1 round
- Parameters: `combat_id`, `character_id`, `active_spell_id`
- Returns: Sustained spell confirmation

#### `dismissSpell(Request $request)`
- End spell early
- Parameters: `character_id`, `active_spell_id`
- Returns: Dismissal confirmation

#### `heightenSpell(Request $request)`
- Calculate heightened spell effects
- Parameters: `spell_id`, `base_level`, `heighten_level`
- Returns: Heightened spell data

#### `identifySpell(Request $request)`
- Recognize being-cast spell (Arcana/Nature/Occultism/Religion)
- Parameters: `observer_id`, `caster_id`, `spell_being_cast_id`
- Returns: Identification result

#### `counterspell(Request $request)`
- Reaction: Attempt to counter enemy spell
- Parameters: `character_id`, `enemy_spell_id`
- Returns: Counterspell result

#### `calculateSpellDC($character_id)`
- Calculate character's spell DC
- Formula: 10 + level + key_ability + proficiency
- Parameters: `character_id`
- Returns: Spell DC value

#### `calculateSpellAttackBonus($character_id)`
- Calculate spell attack modifier
- Formula: level + key_ability + proficiency + bonuses
- Parameters: `character_id`
- Returns: Attack bonus

#### `getAvailableSpells($character_id, $spell_level)`
- Get spells character can cast at level
- Filter by tradition, known/prepared, available slots
- Parameters: `character_id`, optional spell level
- Returns: Spell list

#### `learnSpell(Request $request)`
- Add spell to spellbook (Wizard) or repertoire (Sorcerer/Bard)
- Parameters: `character_id`, `spell_id`
- Returns: Updated spell list

#### `forgetSpell(Request $request)`
- Remove spell from repertoire (Sorcerer/Bard on level-up)
- Parameters: `character_id`, `spell_id`
- Returns: Updated spell list

## Schema Design

### game_spells

```sql
CREATE TABLE game_spells (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    spell_level TINYINT UNSIGNED NOT NULL, -- 0 = cantrip, 1-10
    traditions JSON NOT NULL, -- ["arcane", "divine", "occult", "primal"]
    
    -- Casting Requirements
    actions_cost TINYINT UNSIGNED, -- 1, 2, 3 (NULL for reaction/free)
    action_type ENUM('single_action', 'two_actions', 'three_actions', 'reaction', 'free_action') DEFAULT 'two_actions',
    components JSON, -- ["material", "somatic", "verbal"]
    
    -- Trigger (for reactions)
    trigger_description TEXT,
    
    -- Effect Type
    effect_category ENUM('attack', 'save', 'utility', 'healing', 'buff', 'debuff', 'summon', 'teleport', 'other') NOT NULL,
    
    -- Attack/Save Details
    requires_attack_roll BOOLEAN DEFAULT FALSE,
    saving_throw_type ENUM('fortitude', 'reflex', 'will', 'none') DEFAULT 'none',
    is_basic_save BOOLEAN DEFAULT FALSE, -- Basic save (for damage scaling)
    
    -- Targeting
    range_feet INT NULL, -- NULL = touch, 0 = self
    range_description VARCHAR(100), -- "touch", "30 feet", "line of sight"
    area_type ENUM('burst', 'cone', 'emanation', 'line', 'none') DEFAULT 'none',
    area_size VARCHAR(50), -- "20-foot burst", "60-foot line"
    max_targets TINYINT UNSIGNED, -- NULL = unlimited
    
    -- Duration
    duration_type ENUM('instantaneous', 'rounds', 'minutes', 'hours', 'sustained', 'until_daily_prep', 'unlimited') NOT NULL,
    duration_value SMALLINT UNSIGNED, -- Number of rounds/minutes/hours
    can_be_sustained BOOLEAN DEFAULT FALSE,
    
    -- Traits
    traits JSON, -- ["attack", "fire", "evocation", "concentrate", etc.]
    
    -- School and Tradition
    spell_school ENUM('abjuration', 'conjuration', 'divination', 'enchantment', 'evocation', 'illusion', 'necromancy', 'transmutation') NOT NULL,
    
    -- Description
    description TEXT NOT NULL,
    heightened_effects JSON, -- {2: "+1d6 damage", 5: "+2d6 damage and 10-foot burst"}
    
    -- Damage (if applicable)
    base_damage_dice VARCHAR(20), -- "6d6", "1d4+1"
    damage_type VARCHAR(20), -- "fire", "cold", "bludgeoning", etc.
    
    -- Metadata
    source_book VARCHAR(50) DEFAULT 'Core Rulebook',
    page_number SMALLINT UNSIGNED,
    rarity ENUM('common', 'uncommon', 'rare', 'unique') DEFAULT 'common',
    
    INDEX idx_name (name),
    INDEX idx_level (spell_level),
    INDEX idx_school (spell_school),
    FULLTEXT idx_description (name, description)
);
```

### character_spells_known

**For spontaneous casters (Bard, Sorcerer) - their repertoire**

```sql
CREATE TABLE character_spells_known (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    spell_id INT UNSIGNED NOT NULL,
    spell_level TINYINT UNSIGNED NOT NULL, -- Level at which they know it
    
    -- When learned
    learned_at_level TINYINT UNSIGNED,
    learned_source VARCHAR(100), -- "Class feature", "Feat: Bard Dedication"
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (spell_id) REFERENCES game_spells(id),
    UNIQUE KEY unique_character_spell (character_id, spell_id),
    INDEX idx_character (character_id),
    INDEX idx_level (character_id, spell_level)
);
```

### character_spellbook

**For prepared casters (Cleric, Druid, Wizard) - their spellbook/spell access**

```sql
CREATE TABLE character_spellbook (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    spell_id INT UNSIGNED NOT NULL,
    
    -- Wizard-specific
    learned_at_level TINYINT UNSIGNED, -- When added to spellbook
    acquisition_method VARCHAR(100), -- "Starting spells", "Found scroll", "Learned from master"
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (spell_id) REFERENCES game_spells(id),
    UNIQUE KEY unique_character_spell (character_id, spell_id),
    INDEX idx_character (character_id)
);
```

**Note**: Clerics and Druids have access to ALL spells of their tradition, so they don't populate this table (or have special flag)

### character_spell_slots

```sql
CREATE TABLE character_spell_slots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    spell_level TINYINT UNSIGNED NOT NULL, -- 0-10 (0 = cantrips, infinite)
    total_slots TINYINT UNSIGNED NOT NULL,
    used_slots TINYINT UNSIGNED DEFAULT 0,
    
    -- Daily Prep Tracking
    last_preparation TIMESTAMP NULL,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_character_level (character_id, spell_level),
    INDEX idx_character (character_id)
);
```

### character_prepared_spells

**For prepared casters - what they prepared today**

```sql
CREATE TABLE character_prepared_spells (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    spell_id INT UNSIGNED NOT NULL,
    spell_level TINYINT UNSIGNED NOT NULL, -- Slot level used
    slot_number TINYINT UNSIGNED NOT NULL, -- Which slot (1, 2, 3, etc.)
    
    -- Status
    is_expended BOOLEAN DEFAULT FALSE,
    prepared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (spell_id) REFERENCES game_spells(id),
    UNIQUE KEY unique_character_slot (character_id, spell_level, slot_number),
    INDEX idx_character (character_id),
    INDEX idx_available (character_id, is_expended)
);
```

### active_spell_effects

```sql
CREATE TABLE active_spell_effects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caster_character_id BIGINT UNSIGNED NOT NULL,
    spell_id INT UNSIGNED NOT NULL,
    spell_level_cast TINYINT UNSIGNED NOT NULL, -- Level at which spell was cast
    
    -- Targets
    target_type ENUM('character', 'monster', 'area', 'self') NOT NULL,
    target_ids JSON, -- Array of character/monster IDs affected
    
    -- Duration Tracking
    duration_type ENUM('rounds', 'minutes', 'sustained', 'concentration', 'until_daily_prep', 'unlimited') NOT NULL,
    rounds_remaining INT NULL,
    last_sustained_round INT NULL, -- Track when last sustained
    
    -- Combat Context
    combat_encounter_id BIGINT UNSIGNED NULL,
    cast_at_round TINYINT UNSIGNED NULL,
    
    -- Dismissible
    can_be_dismissed BOOLEAN DEFAULT TRUE,
    
    -- Effect Details
    effect_data JSON, -- Specific effect parameters (bonus amounts, damage values, etc.)
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    FOREIGN KEY (caster_character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (spell_id) REFERENCES game_spells(id),
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE SET NULL,
    INDEX idx_caster (caster_character_id),
    INDEX idx_combat (combat_encounter_id),
    INDEX idx_active (caster_character_id, duration_type)
);
```

### spell_cast_log

```sql
CREATE TABLE spell_cast_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    spell_id INT UNSIGNED NOT NULL,
    spell_level_cast TINYINT UNSIGNED NOT NULL,
    
    -- Context
    combat_encounter_id BIGINT UNSIGNED NULL,
    round_number TINYINT UNSIGNED NULL,
    
    -- Outcome
    was_heightened BOOLEAN DEFAULT FALSE,
    heightened_to_level TINYINT UNSIGNED NULL,
    required_attack_roll BOOLEAN DEFAULT FALSE,
    attack_result ENUM('critical_hit', 'hit', 'miss', 'critical_miss') NULL,
    required_save BOOLEAN DEFAULT FALSE,
    save_results JSON, -- Array of {target_id, degree: "success"}
    
    -- Disruption
    was_disrupted BOOLEAN DEFAULT FALSE,
    disruption_reason VARCHAR(255),
    
    -- Targets
    target_ids JSON,
    
    cast_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (spell_id) REFERENCES game_spells(id),
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE SET NULL,
    INDEX idx_character (character_id),
    INDEX idx_spell (spell_id),
    INDEX idx_combat (combat_encounter_id)
);
```

### character_spellcasting_stats

**Stores calculated spell DC and attack bonus**

```sql
CREATE TABLE character_spellcasting_stats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    tradition ENUM('arcane', 'divine', 'occult', 'primal') NOT NULL,
    
    -- Class providing spellcasting
    class_id INT UNSIGNED NOT NULL,
    
    -- Key Ability
    key_ability ENUM('int', 'wis', 'cha') NOT NULL,
    
    -- Proficiency
    spell_attack_proficiency ENUM('trained', 'expert', 'master', 'legendary') NOT NULL,
    spell_dc_proficiency ENUM('trained', 'expert', 'master', 'legendary') NOT NULL,
    
    -- Calculated Values (cached)
    spell_dc TINYINT UNSIGNED NOT NULL,
    spell_attack_bonus TINYINT NOT NULL,
    
    -- Casting Type
    casting_type ENUM('prepared', 'spontaneous', 'focus_only') NOT NULL,
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES game_classes(id),
    INDEX idx_character (character_id)
);
```

## Process Flow

### Daily Spell Preparation (Prepared Casters)

```
Character rests for 8 hours
    ↓
Players initiates "Prepare Spells"
    ↓
SpellcastingController::prepareSpells()
    ↓
Load character's spell access:
    - Clerics: All divine spells
    - Druids: All primal spells
    - Wizards: spells in character_spellbook
    ↓
Load character_spell_slots for available slots
    ↓
UI displays spell selection interface:
    For each spell level with slots:
        Show number of slots (e.g., "1st-level: 3 slots")
        Show available spells to prepare
    ↓
Player selects spell for each slot:
    Slot 1 (1st-level): Magic Missile
    Slot 2 (1st-level): Magic Missile (can prepare same spell multiple times)
    Slot 3 (1st-level): Mage Armor
    Slot 1 (2nd-level): Flaming Sphere
    Slot 2 (2nd-level): Invisibility
    ... etc
    ↓
Validate selection:
    - All slots filled
    - Selected spells are accessible
    - Character has tradition access
    ↓
Clear previous preparation:
    DELETE FROM character_prepared_spells WHERE character_id = X
    ↓
Insert new preparation:
    INSERT INTO character_prepared_spells
    For each slot with spell selection
    ↓
Reset spell slots:
    UPDATE character_spell_slots
    SET used_slots = 0, last_preparation = NOW()
    ↓
Return confirmation
    "Spells prepared successfully"
```

### Casting a Spell (Combat)

```
Player clicks "Cast Spell" in combat
    ↓
SpellcastingController::castSpell()
    ↓
Select spell and heighten level (if applicable)
    ↓
Validate can cast:
    Check actions remaining >= spell.actions_cost
    Check spell slot available:
        Prepared casters: Check character_prepared_spells for unpended spell
        Spontaneous casters: Check character_spell_slots for unused slots
    Check components can be provided:
        - Material: Has spell component pouch or focus
        - Somatic: Free hand available
        - Verbal: Can speak (not silenced)
    Check range to target
    ↓
If validation fails:
    Return error message
    ↓
If validation succeeds:
    Deduct actions:
        UPDATE combat_participants SET actions_remaining -= spell.actions_cost
    
    Check for spell disruption triggers:
        If has concentrate trait:
            Register vulnerability to disruption until cast completes
    ↓
Determine spell execution path based on effect_category:
    
    IF requires_attack_roll:
        → SpellcastingController::makeSpellAttackRoll()
        Calculate: spell_attack_bonus
        Roll: d20 + spell_attack_bonus
        Compare to target AC
        Determine degree (critical hit/hit/miss/critical miss)
        If hit: prompt for damage roll or apply effect
        Apply MAP if subsequent spell attack this turn
    
    ELSE IF saving_throw_type != 'none':
        → SpellcastingController::resolveSpellSave()
        Calculate: caster's spell_dc
        For each target:
            Roll save: d20 + target's save modifier
            Determine degree of success
            Apply effects based on degree:
                If is_basic_save:
                    Critical Success: No damage
                    Success: Half damage
                    Failure: Full damage
                    Critical Failure: Double damage
                Else:
                    Apply per spell description
    
    ELSE (utility/buff/healing/other):
        Apply spell effects directly
        Examples:
            - Mage Armor: Add +1 AC status bonus
            - Heal: Restore HP
            - Invisibility: Apply invisible condition
    ↓
Expend spell resource:
    Prepared casters:
        UPDATE character_prepared_spells SET is_expended = TRUE WHERE id = X
    
    Spontaneous casters:
        UPDATE character_spell_slots SET used_slots += 1
            WHERE character_id = X AND spell_level = Y
    ↓
If duration > instantaneous:
    INSERT INTO active_spell_effects
    Track duration, targets, effect data
    ↓
Log cast:
    INSERT INTO spell_cast_log
    ↓
Return spell result to UI
    Show spell effects, damage rolls, condition applications
```

### Sustained Spell Flow

```
Spell with sustained duration active
    ↓
At start of caster's turn (or during turn):
    Player clicks "Sustain" next to active spell
    ↓
SpellcastingController::sustainSpell()
    ↓
Validate:
    - Spell is sustainables (can_be_sustained = TRUE)
    - Spell is active
    - Caster has action available
    ↓
Deduct 1 action:
    UPDATE combat_participants SET actions_remaining -= 1
    ↓
Extend spell duration:
    UPDATE active_spell_effects
    SET rounds_remaining += 1, last_sustained_round = current_round
    ↓
Return confirmation
    "Spell sustained for another round"
    ↓
If not sustained by end of turn:
    Spell ends
    DELETE FROM active_spell_effects
```

### Spell Disruption Flow

```
Caster casting spell with concentrate trait
    ↓
Before spell completes, caster takes damage
    ↓
System checks if spell has concentrate trait:
    If yes:
        Caster must make flat check DC 5
        Roll: d20
        If result < 5:
            Spell is disrupted
            ↓
            SpellcastingController::castSpell() interrupted
                Mark was_disrupted = TRUE
                Expend spell slot/preparation
                Actions already spent (lost)
                No spell effect occurs
                ↓
                UPDATE spell_cast_log SET was_disrupted = TRUE
                ↓
                Return failure message
                "Your spell was disrupted by damage!"
        
        If result >= 5:
            Spell continues normally
            No disruption
```

### Heightening Calculation

```
Player selects spell to cast
    ↓
Player selects heighten level (if higher slot available)
    ↓
SpellcastingController::heightenSpell()
    ↓
Load game_spells.heightened_effects JSON
    ↓
Example spell: Magic Missile (1st level)
    Base: 1d4+1 force damage, 1 missile
    heightened_effects: {
        "2": "+1 missile",
        "3": "+1 missile",
        "5": "+1 missile",
        "7": "+1 missile",
        "9": "+1 missile"
    }
    ↓
Calculate cumulative heightening:
    If cast at 5th level:
        +1 missile (2nd level)
        +1 missile (3rd level)
        +1 missile (5th level)
        = 4 missiles total
    ↓
Return heightened spell data:
    {
        "base_damage": "1d4+1",
        "missile_count": 4,
        "total_damage": "4d4+4"
    }
```

## Functions Required

### SpellcastingCalculationService

**Purpose**: Calculate spell DC, attack bonus, and availability

#### `calculateSpellDC($character, $tradition)`
- Formula: `10 + character_level + key_ability_modifier + proficiency_bonus`
- Parameters: character object, spell tradition
- Returns: Spell DC value

#### `calculateSpellAttackBonus($character, $tradition)`
- Formula: `character_level + key_ability_modifier + proficiency_bonus`
- Parameters: character object, spell tradition
- Returns: Spell attack bonus

#### `getAvailableSpellSlots($character, $spell_level)`
- Query character_spell_slots
- Calculate: total_slots - used_slots
- Parameters: character, spell level
- Returns: Available slots count

#### `canCastSpell($character, $spell, $heighten_level, $context)`
- Check spell slot availability
- Check component requirements
- Check action economy
- Check range/line of sight
- Parameters: character, spell, heighten level, combat context
- Returns: `{can_cast: boolean, reasons: []}`

#### `getKeyAbilityForTradition($character, $tradition)`
- Lookup character's spellcasting class
- Return key ability for tradition (Int/Wis/Cha)
- Parameters: character, tradition
- Returns: Ability score name

### SpellEffectService

**Purpose**: Apply spell effects and outcomes

#### `applySpellDamage($targets[], $damage_roll, $damage_type, $is_basic_save, $save_results[])`
- Calculate damage per target based on saves
- Apply resistances/weaknesses
- Update HP
- Parameters: target array, damage, type, basic save flag, save results
- Returns: Damage application results

#### `applySpellCondition($targets[], $condition_type, $value, $duration, $save_results[])`
- Apply condition based on save degree
- Parameters: targets, condition, value, duration, saves
- Returns: Condition application results

#### `applySpellBuff($targets[], $buff_type, $bonus_value, $duration)`
- Apply status bonus (Bless, Haste, etc.)
- Insert active_spell_effects
- Parameters: targets, buff, value, duration
- Returns: Buff application results

#### `applyHealing($targets[], $healing_amount, $is_vitality)`
- Heal hit points
- Check for undead (damages if vitality/positive energy)
- Parameters: targets, healing amount, energy type
- Returns: Healing results

#### `createSpellArea($area_type, $area_size, $origin_point, $direction)`
- Calculate which squares are in spell area
- Determine affected creatures
- Parameters: area type, size, origin, direction (for cones/lines)
- Returns: List of affected coordinates and creatures

#### `checkLineOfSight($caster_position, $target_position, $terrain[])`
- Determine if caster can see target
- Check for obstructions
- Parameters: positions, terrain data
- Returns: Boolean (has line of sight)

### SpellComponentService

**Purpose**: Validate spell component requirements

#### `canProvideComponents($character, $spell, $context)`
- Check material component (pouch/focus/consumed materials)
- Check somatic (free hand)
- Check verbal (not silenced)
- Parameters: character, spell, context
- Returns: `{can_provide: boolean, missing: []}`

#### `hasFreSomatic($character, $context)`
- Check hands not full
- Check not grabbed/restrained (without special feat)
- Parameters: character, context
- Returns: Boolean

#### `canSpeakVerbal($character, $context)`
- Check not silenced
- Check not gagged
- Check conscious
- Parameters: character, context
- Returns: Boolean

#### `hasMaterialComponents($character, $spell)`
- Check component pouch
- Check focus item for tradition
- Check consumed materials if required
- Parameters: character, spell
- Returns: Boolean

### SpellDurationService

**Purpose**: Track spell durations and sustained spells

#### `createActiveSpellEffect($caster, $spell, $targets, $spell_level, $combat_id)`
- Insert into active_spell_effects
- Calculate duration based on spell type
- Parameters: caster, spell, targets, level, combat
- Returns: Active spell effect ID

#### `decrementSpellDurations($character_id, $round_decrement)`
- Called at start of character's turn
- Decrement all round-based durations by 1
- Remove expired spells
- Parameters: character ID, rounds to decrement
- Returns: Expired spells list

#### `checkSustainedSpells($character_id, $current_round)`
- Check if sustained spells were sustained this round
- If not sustained: end spell
- Parameters: character ID, current round
- Returns: Spells that ended due to lack of sustain

#### `dismissSpell($active_spell_effect_id)`
- End spell early
- Remove from active_spell_effects
- Remove effects from targets
- Parameters: spell effect ID
- Returns: Dismissal confirmation

## Data Requirements Per Function

### Casting Spell:
- Load: `game_spells` for spell definition
- Load: `character_spellcasting_stats` for spell DC and attack
- Load: `character_spell_slots` for available slots
- Load: `character_prepared_spells` OR `character_spells_known` (casting type)
- Load: `character_inventory` for focus/component pouch
- Load: `combat_participants` for action economy and position
- Load: `combat_conditions` for silenced/grabbed/etc conditions
- Update: Spell slots/prepared spells (expend)
- Update: `combat_participants` (action economy)
- Insert: `active_spell_effects` if duration > instantaneous
- Insert: `spell_cast_log`

### Preparing Spells:
- Load: `character_spellbook` OR all tradition spells (class dependent)
- Load: `character_spell_slots` for slot counts
- Delete: Previous `character_prepared_spells`
- Insert: New `character_prepared_spells`
- Update: `character_spell_slots` (reset used_slots to 0)

### Sustaining Spell:
- Load: `active_spell_effects` for active spells
- Update: `active_spell_effects` (extend duration)
- Update: `combat_participants` (deduct action)

### Resolving Spell:
- Load: `game_spells` for effect details
- Load: Target stats for AC/saves
- Roll: Attack or saves
- Calculate: Damage/effects
- Update: Target HP/conditions
- Insert: `combat_actions` or specialized log

## API Endpoints Summary

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/spellcasting/{character}` | Show character's spellcasting interface |
| POST | `/spellcasting/prepare` | Prepare daily spells |
| POST | `/spellcasting/cast` | Cast a spell |
| POST | `/spellcasting/attack-roll` | Make spell attack roll |
| POST | `/spellcasting/save` | Resolve spell save |
| POST | `/spellcasting/sustain` | Sustain a spell |
| POST | `/spellcasting/dismiss` | Dismiss active spell |
| GET | `/spellcasting/available-slots/{character}` | Get available spell slots |
| POST | `/spellcasting/heighten` | Calculate heightened effects |
| POST | `/spellcasting/learn` | Learn new spell (levelup) |
| POST | `/spellcasting/forget` | Forget spell (spontaneous caster) |
| POST | `/spellcasting/counterspell` | Attempt to counter spell |
| POST | `/spellcasting/identify` | Identify being-cast spell |
| GET | `/spellcasting/dc/{character}` | Get spell DC |
| GET | `/spellcasting/attack-bonus/{character}` | Get spell attack bonus |
| GET | `/spells` | Browse spell library |
| GET | `/spells/{id}` | Get spell details |
| GET | `/spells/filter` | Filter spells by tradition/level/school |

## Success Criteria

- ✅ All Core Rulebook spells defined in game_spells table
- ✅ Four spell traditions implemented (Arcane, Divine, Occult, Primal)
- ✅ Prepared casters can prepare spells daily with slot management
- ✅ Spontaneous casters use spell repertoire with flexible casting
- ✅ Cantrips cast unlimited times and auto-heighten
- ✅ Spell slots track used/available correctly
- ✅ Heightening system functional with correct effect scaling
- ✅ Spell attack rolls integrate with combat system and MAP
- ✅ Spell saves resolved with four degrees of success
- ✅ Basic saves calculate damage correctly (none/half/full/double)
- ✅ Sustained spells require Sustain action each round
- ✅ Concentrate trait spells can be disrupted by damage
- ✅ Component requirements validated (material/somatic/verbal)
- ✅ Active spell effects tracked with durations
- ✅ Spell DC and attack bonus calculate correctly
- ✅ Range and area effects determine targets accurately
- ✅ Spell cast log records all casting attempts

## UI Components Needed

### Spellcasting Interface
- Available spell slots display (e.g., "1st: ●●○ 2nd: ●○")
- Spell list/grid with icons
- Filter by level, school, tradition
- Search bar
- "Prepare Spells" button (prepared casters)
- Spell detail panel

### Combat Spellcasting UI
- Quick-access spell cards
- Heighten level selector
- Target selector (single/multiple/area)
- Component availability indicators
- Cast button with action cost
- Active spells panel with Sustain buttons

### Spell Preparation Interface
- Slot allocation grid
- Drag-and-drop spells to slots
- "Copy spell to multiple slots" feature
- Confirmation before finalizing

### Active Spells Tracker
- List of active spell effects
- Duration countdown (rounds/minutes remaining)
- Sustain button (if applicable)
- Dismiss button
- Target icons

### Spell Card Display
- Spell name and level
- Traditions that can cast
- Actions required (icon)
- Components (M/S/V indicators)
- Range and area
- Duration
- Saving throw/attack type
- Description text
- Heightened effects expandable section

## Future Enhancements

- Ritual casting system
- Spell scrolls and wands
- Spell research (creating custom spells)
- Spell component marketplace
- Metamagic feat integration
- Automatic spell damage calculator
- Spell combo suggestions (AI-powered)
- Spell casting animations
- Voice-activated casting (accessibility)
- Focus spell system (class-specific)
- Innate spells (ancestry/heritage)
- Spell slot recovery (arcane bondresting)
- Counterspell mini-game
- Spell effect visualizations on battle map
