# PR-06: Character Leveling System Implementation

## Verification Notes (2026-02-18)

- This document describes target-state leveling architecture.
- Current implementation exposes selected leveling/state operations through existing character state APIs; dedicated route/controller surface described below may be partial or planned.
- Verify operational behavior directly against module routing and active controllers.

## Overview
Implement a comprehensive character leveling system that manages XP tracking, level-up triggers, guided leveling workflow, ability score increases, feat selection, proficiency improvements, HP increases, spell progression, and stat recalculation. The system must enforce Pathfinder 2E rules for level milestones and maintain character progression history.

## Reference Documentation
Additional detailed game mechanics documentation is available in the `reference documentation/` subdirectory:
- PF2E Core Rulebook - Fourth Printing.txt
- PF2E Advanced Players Guide.txt
- PF2E Gamemastery Guide.txt
- Other supplementary rulebooks

These reference materials provide comprehensive rules for character advancement, XP awards, level-based features, feat progressions, and proficiency improvements that should be consulted during implementation.

## Controller Design

### LevelingController

**Purpose**: Manage character leveling and progression

**Route Base**: `/character/leveling`

**Key Methods**:

#### `checkLevelUp($character_id)`
- Check if character has enough XP to level up (≥1000 XP)
- Trigger level-up notification
- Parameters: `character_id`
- Returns: Level-up eligibility status

#### `startLevelUp(Request $request)`
- Initialize level-up wizard
- Create leveling session
- Parameters: `character_id`
- Returns: Level-up wizard interface

#### `processLevelUp(Request $request)`
- Complete level-up transaction
- Apply all changes atomically
- Parameters: `character_id`, `leveling_choices`
- Returns: Updated character confirmation

#### `increaseLevel($character_id)`
- Increment character level by 1
- Subtract 1000 XP
- Keep remaining XP
- Parameters: `character_id`
- Returns: New level and XP

#### `calculateHPIncrease($character)`
- Class HP + Constitution modifier
- Additional HP if Constitution improved
- Parameters: character object
- Returns: HP increase amount

#### `selectAbilityBoosts(Request $request)`
- Apply 4 ability boosts (levels 5, 10, 15, 20)
- Validate no double-boosting
- Parameters: `character_id`, `boost_selections[]`
- Returns: Updated ability scores

#### `selectFeat(Request $request)`
- Add feat to character
- Validate prerequisites
- Parameters: `character_id`, `feat_id`, `feat_type`
- Returns: Updated feat list

#### `selectSpells(Request $request)`
- Add spells to repertoire (spontaneous casters)
- Optionally swap one spell
- Parameters: `character_id`, `new_spell_ids[]`, `swap_spell_id`
- Returns: Updated spell list

#### `improveProficiency(Request $request)`
- Increase skill proficiency rank
- Validate progression (trained → expert → master → legendary)
- Parameters: `character_id`, `proficiency_type`, `proficiency_name`, `new_rank`
- Returns: Updated proficiency

#### `recalculateStats($character_id)`
- Recalculate all derived statistics
- Update AC, saves, skills, attacks
- Parameters: `character_id`
- Returns: Updated stat block

#### `previewLevelUp($character_id)`
- Show what will be gained at next level
- Parameters: `character_id`
- Returns: Level-up preview data

#### `getAvailableFeats($character_id, $feat_type, $level)`
- Filter feats by type, level, prerequisites
- Parameters: `character_id`, feat type, level
- Returns: Available feat list

#### `validateLevelUpChoices(Request $request)`
- Verify all required selections made
- Check prerequisites met
- Parameters: `character_id`, `leveling_data`
- Returns: Validation result

### ExperienceController

**Purpose**: Manage XP awards and tracking

**Route Base**: `/experience`

**Key Methods**:

#### `awardXP(Request $request)`
- Grant XP to character(s)
- Check for level-up trigger
- Parameters: `character_ids[]`, `xp_amount`, `reason`
- Returns: XP award confirmation

#### `awardEncounterXP(Request $request)`
- Calculate and award XP based on encounter difficulty
- Parameters: `character_ids[]`, `encounter_difficulty`, `party_level`
- Returns: XP award

#### `awardAccomplishmentXP(Request $request)`
- Award XP for story accomplishments
- Parameters: `character_ids[]`, `accomplishment_type`
- Returns: XP award

#### `getXPHistory($character_id)`
- Retrieve XP gain history
- Parameters: `character_id`
- Returns: XP log

## Schema Design

### level_up_sessions

```sql
CREATE TABLE level_up_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    from_level TINYINT UNSIGNED NOT NULL,
    to_level TINYINT UNSIGNED NOT NULL,
    
    -- Wizard State
    current_step TINYINT UNSIGNED DEFAULT 1,
    is_complete BOOLEAN DEFAULT FALSE,
    
    -- Choices Made
    ability_boosts JSON, -- ["str", "dex", "con", "wis"] (if milestone level)
    selected_feats JSON, -- [{type: "ancestry", feat_id: 45}]
    proficiency_increases JSON, -- [{type: "skill", name: "athletics", from: "trained", to: "expert"}]
    new_spells JSON, -- [spell_id1, spell_id2]
    swapped_spell_id INT UNSIGNED NULL, -- For spontaneous casters
    
    -- Calculated Changes
    hp_increase INT UNSIGNED,
    constitution_retroactive_hp INT UNSIGNED DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    INDEX idx_character (character_id),
    INDEX idx_incomplete (character_id, is_complete)
);
```

### character_level_history

```sql
CREATE TABLE character_level_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    level TINYINT UNSIGNED NOT NULL,
    
    -- Snapshot of key stats at this level
    total_hp INT UNSIGNED NOT NULL,
    ability_scores JSON, -- {str: 16, dex: 14, ...}
    
    -- What was gained this level
    feats_gained JSON, -- [{type: "class", feat_id: 123, name: "Power Attack"}]
    proficiencies_improved JSON, -- [{type: "skill", name: "athletics", new_rank: "expert"}]
    class_features_gained JSON, -- ["Weapon Mastery", "Second Attack"]
    spells_gained JSON, -- [spell_id1, spell_id2]
    
    -- When leveled
    leveled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    INDEX idx_character_level (character_id, level)
);
```

### experience_log

```sql
CREATE TABLE experience_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    xp_amount SMALLINT NOT NULL, -- Can be negative for penalties
    xp_total_after INT UNSIGNED NOT NULL,
    
    -- Source
    source_type ENUM('encounter', 'accomplishment', 'story', 'quest', 'gm_award', 'penalty', 'other') NOT NULL,
    source_description VARCHAR(255),
    encounter_id BIGINT UNSIGNED NULL,
    campaign_session_id BIGINT UNSIGNED NULL,
    
    -- Encounter Details (if applicable)
    encounter_difficulty ENUM('trivial', 'low', 'moderate', 'severe', 'extreme') NULL,
    party_level TINYINT UNSIGNED NULL,
    
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    awarded_by_user_id BIGINT UNSIGNED NULL, -- GM who awarded
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (encounter_id) REFERENCES combat_encounters(id) ON DELETE SET NULL,
    FOREIGN KEY (campaign_session_id) REFERENCES sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (awarded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_character (character_id),
    INDEX idx_character_date (character_id, awarded_at)
);
```

### ability_score_history

```sql
CREATE TABLE ability_score_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    level_gained TINYINT UNSIGNED NOT NULL,
    
    -- Boosts Applied
    ability_name ENUM('str', 'dex', 'con', 'int', 'wis', 'cha') NOT NULL,
    previous_value TINYINT UNSIGNED NOT NULL,
    new_value TINYINT UNSIGNED NOT NULL,
    boost_source VARCHAR(100), -- "Level 5 milestone", "Apex item"
    
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    INDEX idx_character_level (character_id, level_gained)
);
```

### feat_prerequisites

**Many-to-many for feat prerequisite relationships**

```sql
CREATE TABLE feat_prerequisites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feat_id INT UNSIGNED NOT NULL,
    prerequisite_type ENUM('feat', 'level', 'proficiency', 'ability_score', 'class_feature', 'other') NOT NULL,
    
    -- Type-specific fields
    required_feat_id INT UNSIGNED NULL,
    required_level TINYINT UNSIGNED NULL,
    required_proficiency VARCHAR(50) NULL, -- "Expert in Stealth"
    required_proficiency_rank ENUM('trained', 'expert', 'master', 'legendary') NULL,
    required_ability_score ENUM('str', 'dex', 'con', 'int', 'wis', 'cha') NULL,
    required_ability_value TINYINT UNSIGNED NULL,
    prerequisite_text VARCHAR(255), -- Human-readable
    
    FOREIGN KEY (feat_id) REFERENCES game_feats(id) ON DELETE CASCADE,
    FOREIGN KEY (required_feat_id) REFERENCES game_feats(id) ON DELETE CASCADE,
    INDEX idx_feat (feat_id)
);
```

### xp_tables

**Reference table for XP awards**

```sql
CREATE TABLE xp_tables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    award_type ENUM('encounter', 'accomplishment') NOT NULL,
    difficulty_or_significance ENUM('trivial', 'low', 'moderate', 'severe', 'extreme', 'minor', 'moderate', 'major') NOT NULL,
    xp_value SMALLINT UNSIGNED NOT NULL,
    description TEXT,
    
    INDEX idx_type_difficulty (award_type, difficulty_or_significance)
);
```

**Seeded Data**:
```sql
-- Encounter XP (per party member for standard 4-person party)
INSERT INTO xp_tables (award_type, difficulty_or_significance, xp_value, description) VALUES
('encounter', 'trivial', 40, 'Considerably weaker than party'),
('encounter', 'low', 60, 'Somewhat weaker than party'),
('encounter', 'moderate', 80, 'Even match for party'),
('encounter', 'severe', 120, 'Serious challenge'),
('encounter', 'extreme', 160, 'Potentially deadly encounter');

-- Accomplishment XP
INSERT INTO xp_tables (award_type, difficulty_or_significance, xp_value, description) VALUES
('accomplishment', 'minor', 10, 'Small victory or discovery'),
('accomplishment', 'moderate', 30, 'Significant achievement'),
('accomplishment', 'major', 80, 'Extraordinary accomplishment');
```

## Process Flow

### Level-Up Workflow

```
Character gains 1000+ XP
    ↓
ExperienceController::awardXP()
    UPDATE characters SET experience_points += amount
    INSERT experience_log
    ↓
Check if XP >= 1000:
    If yes:
        Trigger level-up notification
        "You have enough XP to level up!"
    ↓
Player clicks "Level Up"
    ↓
LevelingController::startLevelUp()
    ↓
Create level_up_sessions row
    from_level = current_level
    to_level = current_level + 1
    ↓
Display Level-Up Wizard Interface
    ↓
Step 1: Increase Level
    LevelingController::increaseLevel()
        level += 1
        experience_points -= 1000
    Display new level
    Show proficiency bonus increase (+1)
    ↓
Step 2: Increase HP
    LevelingController::calculateHPIncrease()
        class_hp = game_classes.hp_per_level
        con_modifier = characters.ability_con modifier
        hp_increase = class_hp + con_modifier
        
        If Constitution modifier increased:
            retroactive_hp = (new_con_mod - old_con_mod) × level
            total_hp_increase = hp_increase + retroactive_hp
        
        UPDATE characters SET max_hp += total_hp_increase
        UPDATE level_up_sessions SET hp_increase = total_hp_increase
    ↓
Step 3: Class Features
    Load game_classes.class_features JSON
    Filter features for new level
    Display features gained
    
    Automatic class features applied:
        - Proficiency increases
        - Class abilities
    ↓
Step 4: Ability Boosts (if milestone level 5/10/15/20)
    If level IN (5, 10, 15, 20):
        LevelingController::selectAbilityBoosts()
        
        UI displays 6 ability scores
        Player selects 4 different abilities to boost
        
        Validate: Each boost to different ability
        
        For each boost:
            ability_score += 2
            
            If modifier increased (even number reached):
                Recalculate dependent stats
                If Constitution: Calculate retroactive HP
                If Intelligence: Offer additional skill training
        
        UPDATE ability_score_history
        UPDATE level_up_sessions SET ability_boosts = [...]
    ↓
Step 5: Select Feats
    Determine which feats gained this level:
        Check level for feat types:
            - Odd levels: Ancestry feat, General/Skill feat
            - Even levels: Skill feat, potentially Class feat
            - Class-specific: Check class table
    
    For each feat selection:
        LevelingController::getAvailableFeats(type, level)
            Query game_feats
            Filter by type and level ≤ character level
            Check feat_prerequisites for each feat
            Remove feats with unmet prerequisites
        
        Display filtered feat list
        
        Player selects feat
        
        LevelingController::selectFeat()
            Validate prerequisites again
            INSERT character_feats
            UPDATE level_up_sessions.selected_feats
    ↓
Step 6: Skill Increases (if granted by class)
    If class grants skill increases this level:
        LevelingController::improveProficiency()
        
        Options:
        a) Increase existing skill proficiency rank
           (Trained → Expert → Master → Legendary)
        b) Become trained in new skill (if untrained slots available)
        
        Validate progression rules:
            - Can't skip ranks
            - Must meet minimum level for rank (Expert at 3+, Master at 7+, Legendary at 15+)
        
        UPDATE character_skill_proficiencies
        UPDATE level_up_sessions.proficiency_increases
    ↓
Step 7: Spells (if spellcaster)
    If character has spellcasting:
        Load character_spellcasting_stats
        
        Prepared Casters (Cleric, Druid, Wizard):
            - Gain new spell slots automatically
            - Update character_spell_slots
            - No spell selection needed (prepare later)
            
            Wizards only:
                - Add 2 spells to spellbook
                - LevelingController::selectSpells()
                - INSERT character_spellbook
        
        Spontaneous Casters (Bard, Sorcerer):
            - Gain new spell slots automatically
            - Update character_spell_slots
            - Add new spells to repertoire
            - LevelingController::selectSpells()
            - Can swap 1 existing spell
            - INSERT character_spells_known
            - If swapping: DELETE old spell from character_spells_known
    ↓
Step 8: Recalculate All Stats
    LevelingController::recalculateStats()
        
        Update all proficiency bonuses (+1 from level):
            - AC
            - Saves (Fort, Ref, Will)
            - Perception
            - Skills
            - Attack rolls
            - Spell attack/DC
        
        Recalculate from ability score changes:
            - Update modifiers
            - Update saves (ability-based)
            - Update skills (ability-based)
            - Update attacks (ability-based)
            - Update AC (Dex-based)
            - Update HP (Con retroactive)
        
        Apply proficiency rank increases:
            - Class features granting Expert/Master/Legendary
            - Skill increases selected
        
        Update spell slots:
            - Add new spell level slots
            - Increase existing spell level slot counts
        
        Check feat scaling:
            - Toughness: +HP equal to level
            - Fleet: Speed bonus
            - Others with level-based effects
    ↓
Step 9: Review and Confirm
    LevelingController::previewLevelUp()
        Display summary:
            - Level increased: X → Y
            - HP gained: +Z
            - Ability scores changed: [list]
            - Feats gained: [list]
            - Proficiencies improved: [list]
            - Class features gained: [list]
            - Spells gained: [list]
            - New stats: [show updated character sheet]
    
    Player confirms "Complete Level Up"
    ↓
Step 10: Finalize
    LevelingController::processLevelUp()
        
        All changes already applied, now finalize:
        
        UPDATE level_up_sessions SET is_complete = TRUE, completed_at = NOW()
        
        INSERT character_level_history
            (snapshot of this level with all gains)
        
        Notification: "Level up complete! You are now level X."
        
        Redirect to character sheet
```

### XP Award Flow

```
Combat encounter ends OR story accomplishment occurs
    ↓
GM clicks "Award XP"
    ↓
ExperienceController::awardXP() OR ::awardEncounterXP()
    ↓
If encounter XP:
    Calculate XP based on:
        - Encounter difficulty (trivial/low/moderate/severe/extreme)
        - Party level
        - Party size
    
    Load xp_tables for base XP value
    Adjust for party size if needed
    
If accomplishment XP:
    Load xp_tables for accomplishment type
    (minor/moderate/major)
    ↓
For each character in party:
    UPDATE characters SET experience_points += xp_amount
    
    INSERT experience_log
        (character_id, xp_amount, source_type, etc.)
    
    Check if experience_points >= 1000:
        If yes:
            Trigger notification:
                "You gained X XP! You can now level up."
    ↓
Return XP award summary
    "Party gained X XP each"
```

### Ability Score Boost Validation

```
Player selects 4 ability boosts
    ↓
LevelingController::selectAbilityBoosts()
    ↓
Validate:
    1. Exactly 4 boosts selected
    2. Each boost to different ability
    3. No ability boosted twice
    ↓
If validation passes:
    For each selected ability:
        old_value = current ability score
        new_value = old_value + 2
        
        UPDATE characters SET ability_[name] = new_value
        
        INSERT ability_score_history
        
        Check if modifier changed:
            old_mod = floor((old_value - 10) / 2)
            new_mod = floor((new_value - 10) / 2)
            
            If new_mod > old_mod:
                Trigger dependent recalculations:
                    - Constitution: Retroactive HP
                    - Intelligence: Offer skill training
                    - Dexterity: AC recalc
                    - Etc.
```

## Functions Required

### LevelUpCalculationService

**Purpose**: Calculate level-up gains and requirements

#### `getClassFeaturesForLevel($class_id, $level)`
- Query game_classes.class_features JSON
- Filter features gained at level
- Parameters: class ID, level
- Returns: Class features array

#### `getFeatsEarnedAtLevel($level, $class_id)`
- Determine which feat types gained
- Check level patterns (odd/even) and class table
- Parameters: level, class ID
- Returns: Array of feat types to select

#### `calculateHPGain($class_id, $con_modifier, $is_first_level)`
- Formula: class_hp_per_level + con_modifier
- Special handling for 1st level (ancestry HP)
- Parameters: class, Con mod, first level flag
- Returns: HP gain

#### `calculateRetroactiveHP($level, $old_con_mod, $new_con_mod)`
- Formula: (new_mod - old_mod) × level
- Only if Constitution modifier increased
- Parameters: level, old/new mods
- Returns: Retroactive HP gain

#### `getProficiencyIncreases($class_id, $level)`
- Check class advancement table
- Return proficiency rank improvements for level
- Parameters: class ID, level
- Returns: Array of proficiency improvements

#### `getSpellSlotIncrease($class_id, $level, $tradition)`
- Query class spell progression table
- Determine new slots gained
- Parameters: class, level, tradition
- Returns: Spell slot increases

#### `canSelectFeat($character, $feat_id)`
- Check all prerequisites
- Verify level requirement
- Check prerequisite feats owned
- Check ability scores meet requirements
- Parameters: character, feat ID
- Returns: `{can_select: boolean, missing_prerequisites: []}`

### ProficiencyProgressionService

**Purpose**: Manage proficiency rank improvements

#### `canImproveProficiency($character, $proficiency_type, $proficiency_name, $from_rank, $to_rank)`
- Validate rank progression (can't skip)
- Check minimum level for rank
- Verify has improvement available
- Parameters: character, type, name, ranks
- Returns: `{can_improve: boolean, reason: string}`

#### `getMinimumLevelForRank($rank)`
- Expert: level 3+
- Master: level 7+
- Legendary: level 15+
- Parameters: rank name
- Returns: Minimum level

#### `improveProficiencyRank($character_id, $proficiency_type, $proficiency_name, $new_rank)`
- Update character_proficiencies or character_skill_proficiencies
- Parameters: character, type, name, new rank
- Returns: Update confirmation

### StatRecalculationService

**Purpose**: Recalculate derived statistics

#### `recalculateAllStats($character_id)`
- Comprehensive stat recalculation
- Call all specialized recalc functions
- Parameters: character ID
- Returns: Updated stats

#### `recalculateAC($character)`
- Formula: 10 + Dex mod + armor prof + armor bonus + shield
- Parameters: character object
- Returns: New AC value

#### `recalculateSaves($character)`
- Fort: 10 + level + Con mod + prof
- Ref: 10 + level + Dex mod + prof
- Will: 10 + level + Wis mod + prof
- Parameters: character object
- Returns: Save values

#### `recalculateSkills($character)`
- For each skill: level + ability mod + prof + bonuses
- Parameters: character object
- Returns: Skill modifiers object

#### `recalculateAttacks($character)`
- For each weapon: level + ability mod + prof + item bonus
- Parameters: character object
- Returns: Attack bonuses

#### `recalculateSpellStats($character)`
- Spell DC: 10 + level + key ability + prof
- Spell attack: level + key ability + prof
- Parameters: character object
- Returns: Spell DC and attack bonus

## Data Requirements Per Function

### Starting Level-Up:
- Load: `characters` for current level and XP
- Load: `game_classes` for class advancement table
- Insert: `level_up_sessions` to track wizard state

### Processing Level-Up:
- Load: `game_classes` for HP, class features, proficiencies
- Load: `game_feats` for feat selections
- Load: `feat_prerequisites` to validate feat choices
- Load: `game_spells` for spell selections (casters)
- Load: `character_skill_proficiencies` for skill improvements
- Update: `characters` (level, XP, HP, abilities, all stats)
- Update: `character_spell_slots` (for casters)
- Insert: `character_feats` (new feats)
- Insert: `character_spells_known` or `character_spellbook` (casters)
- Insert: `character_level_history` (snapshot)
- Insert: `ability_score_history` (if boosts applied)
- Update: `character_proficiencies` (rank improvements)
- Update: `level_up_sessions` (mark complete)

### Awarding XP:
- Update: `characters` (experience_points)
- Insert: `experience_log`
- Load: `xp_tables` for reference values
- Check: XP threshold for level-up notification

### Validating Feat Selection:
- Load: `game_feats` for feat definition
- Load: `feat_prerequisites` for requirements
- Load: `character_feats` (owned feats)
- Load: `characters` (level, ability scores)
- Load: `character_proficiencies` (for proficiency reqs)

## API Endpoints Summary

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/leveling/check/{character}` | Check if can level up |
| POST | `/leveling/start` | Start level-up wizard |
| POST | `/leveling/process` | Complete level-up |
| POST | `/leveling/ability-boosts` | Apply ability boosts |
| POST | `/leveling/select-feat` | Select feat |
| POST | `/leveling/select-spells` | Select spells (casters) |
| POST | `/leveling/improve-proficiency` | Improve proficiency rank |
| POST | `/leveling/recalculate` | Recalculate all stats |
| GET | `/leveling/preview/{character}` | Preview next level gains |
| GET | `/leveling/available-feats` | Get selectable feats |
| POST | `/leveling/validate` | Validate choices |
| POST | `/experience/award` | Award XP |
| POST | `/experience/award-encounter` | Award encounter XP |
| POST | `/experience/award-accomplishment` | Award accomplishment XP |
| GET | `/experience/history/{character}` | Get XP log |
| GET | `/character/level-history/{character}` | Get level progression history |

## Success Criteria

- ✅ XP tracking accurate with 1000 XP threshold for level-up
- ✅ Level-up wizard guides through all 8-10 steps
- ✅ All class features granted automatically per class table
- ✅ Ability boost system functional for milestone levels (5/10/15/20)
- ✅ Feat selection validates prerequisites correctly
- ✅ Proficiency rank improvements follow progression rules
- ✅ HP increases correctly including Constitution retroactive bonuses
- ✅ Spell slot increases applied for spellcasters
- ✅ Spontaneous casters can add/swap spells in repertoire
- ✅ Prepared casters gain spell slots (Wizards add to spellbook)
- ✅ All stats recalculated automatically (AC, saves, skills, attacks)
- ✅ Level history logged for each level gained
- ✅ XP awards track source and reason
- ✅ Encounter XP calculated by difficulty
- ✅ Accomplishment XP supports minor/moderate/major

## UI Components Needed

### Level-Up Wizard
- Multi-step progress indicator
- "Next" / "Previous" buttons
- Current step highlighted
- Summary panel showing cumulative changes

### Ability Boost Selector
- 6 ability score panels
- +2 boost buttons
- Visual indicator of selected boosts (4 max)
- Show old → new values
- Show modifier changes

### Feat Selection Interface
- Filterable feat list (by type, search)
- Feat cards with name, description, prerequisites
- "Select" button (disabled if prerequisites not met)
- Prerequisite validator with explanations

### Skill Improvement Interface
- Current proficiencies list
- "Improve" button for each skill
- Rank progression indicator (U → T → E → M → L)
- Skill usage examples

### Spell Selection Interface (Casters)
- Available spells by level
- Add to repertoire/spellbook buttons
- Swap interface for spontaneous casters
- Spell cards with details

### Level Summary Screen
- Before/After comparison
- Stats that changed highlighted
- New abilities/feats listed
- HP increase shown
- "Confirm Level Up" button

### XP Tracker
- Current XP / 1000 progress bar
- Recent XP gains log
- "XP to next level" countdown
- Level-up notification badge

## Future Enhancements

- Automated feat recommendations based on build
- Level-up simulation (plan future levels)
- Retraining system for feats and skills
- Multi-class/archetype dedication support
- Character build templates
- Level-up history timeline visualization
- Export character at specific level
- Milestone leveling mode (no XP tracking)
- Party XP sync (automatic party-wide awards)
- Achievement system tied to XP bonuses
- XP decay/penalties for death
- Custom XP tables for house rules
- Guided feat chains (show full progression)
- AI-powered build optimizer
- Character retirement mechanics (level 20+)
