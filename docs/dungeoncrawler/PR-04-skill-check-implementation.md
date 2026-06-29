# PR-04: Skill Check System Implementation

## Verification Notes (2026-02-18)

- This document is a target-state implementation plan.
- Current module runtime does not expose the full dedicated skill-check controller surface described here.
- Use this as design guidance and verify active endpoints against `dungeoncrawler_content.routing.yml` and implemented controllers.

## Overview
Implement a comprehensive skill check resolution system supporting Pathfinder 2E's four degrees of success, proficiency ranks, DC calculation, secret checks, and skill-based actions. The system must integrate with character proficiencies, combat actions, and provide both roll UI and automated calculation.

## Reference Documentation
Additional detailed game mechanics documentation is available in the `reference documentation/` subdirectory:
- PF2E Core Rulebook - Fourth Printing.txt
- PF2E Gamemastery Guide.txt
- Other supplementary rulebooks

These reference materials provide comprehensive rules for all skills, DCs, proficiency ranks, skill actions, and their applications that should be consulted during implementation.

## Controller Design

### SkillCheckController

**Purpose**: Manage skill check rolling and resolution

**Route Base**: `/skill-checks`

**Key Methods**:

#### `roll(Request $request)`
- Execute skill check roll
- Calculate total with modifiers
- Determine degree of success
- Parameters: `character_id`, `skill_name`, `dc`, `modifier_adjustments[]`, `is_secret`
- Returns: Roll result with degree of success

#### `rollOpposed(Request $request)`
- Execute opposed check (both participants roll)
- Determine winner
- Parameters: `character_id`, `opponent_id`, `skill_name`, `opponent_skill`
- Returns: Opposed check result

#### `calculateDC($difficulty_type, $level, $adjustments[])`
- Calculate DC for skill check
- Types: simple, level-based, specific
- Parameters: difficulty type, level, adjustment modifiers
- Returns: Final DC value

#### `getSkillModifier($character_id, $skill_name, $situation_bonuses[])`
- Calculate total skill modifier
- Parameters: character ID, skill name, situational bonuses
- Returns: Total modifier value

#### `recallKnowledge(Request $request)`
- Specialized skill check for knowledge recall
- Auto-select appropriate skill (Arcana, Nature, Occultism, Religion, Society)
- Parameters: `character_id`, `subject_type`, `subject_level`
- Returns: Knowledge result (info revealed)

#### `secretCheck(Request $request)`
- Execute secret check (GM-only visibility)
- Parameters: `character_id`, `skill_name`, `dc`, `reason`
- Returns: Result visible only to GM

#### `earnIncome(Request $request)`
- Crafting/Performance downtime activity
- Parameters: `character_id`, `skill_name`, `level`, `days`
- Returns: Income earned

#### `treatWounds(Request $request)`
- Medicine skill check to heal
- Parameters: `character_id`, `target_id`, `dc_level`
- Returns: HP healed

#### `identifyMagic(Request $request)`
- Identify magic item or effect
- Uses Arcana, Nature, Occultism, or Religion
- Parameters: `character_id`, `item_id` or `effect_id`, `tradition`
- Returns: Identification result

### SkillActionsController

**Purpose**: Execute skill-based actions in combat/exploration

**Route Base**: `/skill-actions`

**Key Methods**:

#### `demoralize(Request $request)`
- Intimidation check to frighten opponent
- Parameters: `combat_id`, `character_id`, `target_id`
- Returns: Result and frightened condition application

#### `feint(Request $request)`
- Deception check to make opponent flat-footed
- Parameters: `combat_id`, `character_id`, `target_id`
- Returns: Result and flat-footed status

#### `createDiversion(Request $request)`
- Deception to enable Hide or Sneak
- Parameters: `character_id`, `observer_ids[]`, `diversion_type`
- Returns: Success status for follow-up action

#### `hide(Request $request)`
- Stealth check to become hidden
- Parameters: `character_id`, `cover_available`, `observer_ids[]`
- Returns: Hidden status vs each observer

#### `sneak(Request $request)`
- Stealth check while moving
- Parameters: `character_id`, `destination`, `observer_ids[]`
- Returns: Movement result and stealth status

#### `seek(Request $request)`
- Perception check to find hidden things
- Parameters: `character_id`, `search_area`
- Returns: Things found (if any)

#### `senseMotive(Request $request)`
- Perception check to detect lies/intentions
- Parameters: `character_id`, `target_id`
- Returns: Insight gained (secret check)

#### `grapple(Request $request)`
- Athletics check vs Fortitude DC
- Has Attack trait (applies MAP)
- Parameters: `combat_id`, `character_id`, `target_id`
- Returns: Grapple result and grabbed condition

#### `shove(Request $request)`
- Athletics check vs Fortitude DC
- Has Attack trait
- Parameters: `combat_id`, `character_id`, `target_id`, `direction`
- Returns: Shove result and position change

#### `trip(Request $request)`
- Athletics check vs Reflex DC
- Has Attack trait
- Parameters: `combat_id`, `character_id`, `target_id`
- Returns: Trip result and prone condition

#### `disarm(Request $request)`
- Athletics check vs Reflex DC
- Has Attack trait
- Parameters: `combat_id`, `character_id`, `target_id`, `item_id`
- Returns: Disarm result and item status

#### `tumbleThrough(Request $request)`
- Acrobatics check vs Reflex DC to move through enemy space
- Parameters: `combat_id`, `character_id`, `target_id`, `destination`
- Returns: Movement result

#### `balance(Request $request)`
- Acrobatics check to cross narrow surface
- Parameters: `character_id`, `surface_dc`
- Returns: Balance result

#### `climb(Request $request)`
- Athletics check to climb surface
- Parameters: `character_id`, `surface_dc`, `distance`
- Returns: Climb result

#### `swim(Request $request)`
- Athletics check to swim
- Parameters: `character_id`, `water_dc`, `distance`
- Returns: Swim result

## Schema Design

### game_skills

```sql
CREATE TABLE game_skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    ability_score ENUM('str', 'dex', 'con', 'int', 'wis', 'cha') NOT NULL,
    description TEXT,
    untrained_uses TEXT, -- What can be done untrained
    trained_uses TEXT, -- What requires training
    
    -- Common Use Cases
    common_actions JSON, -- ["Demoralize", "Coerce", etc.]
    
    source_book VARCHAR(50) DEFAULT 'Core Rulebook',
    INDEX idx_name (name),
    INDEX idx_ability (ability_score)
);
```

**Seeded Data**:
```sql
INSERT INTO game_skills (name, ability_score, description, common_actions) VALUES
('Acrobatics', 'dex', 'Balance on narrow surfaces, tumble through enemy spaces, maneuver in the air.',
 '["Balance", "Tumble Through", "Maneuver in Flight", "Squeeze"]'),
('Athletics', 'str', 'Perform physical feats like climbing, swimming, and grappling.',
 '["Climb", "Force Open", "Grapple", "High Jump", "Long Jump", "Shove", "Swim", "Trip", "Disarm"]'),
('Deception', 'cha', 'Lie, feint in combat, and create diversions.',
 '["Create a Diversion", "Impersonate", "Lie", "Feint"]'),
('Intimidation', 'cha', 'Threaten enemies and force them to comply.',
 '["Coerce", "Demoralize"]'),
-- ... all 17 skills
('Lore', 'int', 'Recall information on specialized topics.',
 '["Recall Knowledge"]');
```

### character_skill_proficiencies

```sql
CREATE TABLE character_skill_proficiencies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    skill_id INT UNSIGNED NOT NULL,
    proficiency_rank ENUM('untrained', 'trained', 'expert', 'master', 'legendary') NOT NULL DEFAULT 'untrained',
    
    -- Tracking
    gained_at_level TINYINT UNSIGNED DEFAULT 1,
    notes VARCHAR(255), -- "From background", "From class feature"
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES game_skills(id),
    UNIQUE KEY unique_character_skill (character_id, skill_id),
    INDEX idx_character (character_id)
);
```

### skill_check_results

```sql
CREATE TABLE skill_check_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    skill_id INT UNSIGNED NOT NULL,
    
    -- Check Details
    dc INT UNSIGNED NOT NULL,
    roll_result TINYINT UNSIGNED NOT NULL, -- d20 result
    modifier_total TINYINT NOT NULL, -- Total bonuses/penalties
    final_result TINYINT NOT NULL, -- roll + modifier
    
    -- Outcome
    degree_of_success ENUM('critical_success', 'success', 'failure', 'critical_failure') NOT NULL,
    was_natural_20 BOOLEAN DEFAULT FALSE,
    was_natural_1 BOOLEAN DEFAULT FALSE,
    
    -- Context
    situation VARCHAR(255), -- "Climbing stone wall", "Recalling knowledge about dragon"
    combat_encounter_id BIGINT UNSIGNED NULL, -- If during combat
    is_secret BOOLEAN DEFAULT FALSE, -- GM-only visibility
    
    -- Modifiers Breakdown
    ability_modifier TINYINT NOT NULL,
    proficiency_bonus TINYINT NOT NULL,
    item_bonuses JSON, -- {"climbing_gear": 1}
    circumstance_bonuses JSON, -- {"favorable_weather": 2}
    status_bonuses JSON,
    penalties JSON, -- {"distracted": -2}
    
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES game_skills(id),
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE SET NULL,
    INDEX idx_character (character_id),
    INDEX idx_character_skill (character_id, skill_id),
    INDEX idx_combat (combat_encounter_id)
);
```

### difficulty_class_tables

```sql
CREATE TABLE difficulty_class_tables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dc_type ENUM('simple', 'level_based', 'spell_dc', 'class_dc') NOT NULL,
    reference_value INT NOT NULL, -- Level for level-based, difficulty rank for simple
    dc_value TINYINT UNSIGNED NOT NULL,
    description VARCHAR(255),
    
    INDEX idx_type_value (dc_type, reference_value)
);
```

**Seeded Data**:
```sql
-- Simple DCs
INSERT INTO difficulty_class_tables (dc_type, reference_value, dc_value, description) VALUES
('simple', 0, 10, 'Untrained'),
('simple', 1, 15, 'Trained'),
('simple', 2, 20, 'Expert'),
('simple', 3, 30, 'Master'),
('simple', 4, 40, 'Legendary');

-- Level-based DCs
INSERT INTO difficulty_class_tables (dc_type, reference_value, dc_value, description) VALUES
('level_based', 0, 14, 'Level 0'),
('level_based', 1, 15, 'Level 1'),
('level_based', 2, 16, 'Level 2'),
-- ... through level 20+
('level_based', 20, 40, 'Level 20');
```

### recall_knowledge_topics

```sql
CREATE TABLE recall_knowledge_topics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_name VARCHAR(100) NOT NULL,
    primary_skill ENUM('arcana', 'nature', 'occultism', 'religion', 'society', 'crafting', 'other') NOT NULL,
    alternative_skills JSON, -- ["society"] for history
    description TEXT,
    example_subjects TEXT,
    
    INDEX idx_primary_skill (primary_skill)
);
```

**Seeded Data**:
```sql
INSERT INTO recall_knowledge_topics (topic_name, primary_skill, example_subjects) VALUES
('Arcane Magic', 'arcana', 'Wizards, arcane spells, magic items, arcane traditions'),
('Constructs', 'arcana', 'Golems, animated objects, magical constructs'),
('Dragons', 'arcana', 'All dragon types, draconic creatures'),
('Animals', 'nature', 'Beasts, animals, natural creatures'),
('Fey', 'nature', 'Fairies, sprites, fey creatures'),
('Plants', 'nature', 'Plant creatures, fungi, oozes'),
('Aberrations', 'occultism', 'Alien entities, aberrant creatures'),
('Undead', 'religion', 'Zombies, skeletons, vampires, liches'),
('Celestials', 'religion', 'Angels, archons, good outsiders'),
('Fiends', 'religion', 'Demons, devils, daemons, evil outsiders'),
('History', 'society', 'Historical events, civilizations, culture'),
('Geography', 'society', 'Locations, settlements, landmarks');
```

### lore_skills

```sql
CREATE TABLE lore_skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    lore_name VARCHAR(100) NOT NULL, -- "Cheese Lore", "Mining Lore", etc.
    proficiency_rank ENUM('trained', 'expert', 'master', 'legendary') NOT NULL DEFAULT 'trained',
    source VARCHAR(100), -- "Background: Miner", "Bardic Lore feat"
    description TEXT,
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    INDEX idx_character (character_id)
);
```

## Process Flow

### Basic Skill Check Flow

```
Player initiates skill check
    ↓
SkillCheckController::roll()
    ↓
Load character data:
    - character_skill_proficiencies for skill
    - character ability scores
    - active buffs/debuffs
    ↓
Calculate skill modifier:
    = proficiency_bonus (rank + level)
    + ability_modifier
    + item_bonuses
    + circumstance_bonuses
    + status_bonuses
    - penalties
    ↓
Roll d20
    ↓
Calculate final result:
    = d20_roll + skill_modifier
    ↓
Compare to DC:
    final_result vs dc
    ↓
Determine degree of success:
    If natural_20:
        Improve degree by 1 step
    If natural_1:
        Worsen degree by 1 step
    
    If final_result >= dc + 10:
        Critical Success
    Else if final_result >= dc:
        Success
    Else if final_result <= dc - 10:
        Critical Failure
    Else:
        Failure
    ↓
Apply outcome effects (if in combat/specific action):
    - Critical Success: Best result
    - Success: Normal result
    - Failure: No effect or minor consequence
    - Critical Failure: Worst result, often with complications
    ↓
Log to skill_check_results
    INSERT with full breakdown
    ↓
Return result to UI
    Display: roll, modifier, total, degree, outcome
```

### Skill-Based Combat Action Flow

```
Player uses skill action in combat (e.g., Demoralize)
    ↓
SkillActionsController::demoralize()
    ↓
Validate action:
    - Character has actions remaining
    - Target is valid and within range
    - Character can act (no paralyzed, etc.)
    ↓
Calculate DC:
    DC = target's Will DC (10 + level + Wis mod + proficiency)
    ↓
SkillCheckController::roll()
    skill: Intimidation
    dc: target's Will DC
    ↓
Roll and determine degree
    ↓
Apply effects based on degree:
    
    Critical Success:
        → CombatController::applyCondition()
        → Add frightened 2 condition to target
        → Duration: 1 round
    
    Success:
        → Apply frightened 1 condition
        → Duration: 1 round
    
    Failure:
        → No effect
        → Target immune to your Demoralize for 10 minutes
    
    Critical Failure:
        → No effect
        → Character is flat-footed until end of their turn
    ↓
Deduct action from character's action economy:
    UPDATE combat_participants SET actions_remaining -= 1
    ↓
Log action to combat_actions table
    ↓
Return result with visual feedback
```

### Opposed Check Flow

```
Opposed check initiated (e.g., Stealth vs Perception)
    ↓
SkillCheckController::rollOpposed()
    ↓
Both participants roll:
    
    Attacker/Actor:
        Roll skill check (e.g., Stealth)
        Calculate: d20 + Stealth modifier
        = attacker_result
    
    Defender/Observer:
        Roll opposing check (e.g., Perception)
        Calculate: d20 + Perception modifier
        = defender_result
    ↓
Compare results:
    difference = attacker_result - defender_result
    ↓
Determine degree:
    If attacker_result >= defender_result + 10:
        Critical Success for attacker
    Else if attacker_result > defender_result:
        Success for attacker
    Else if attacker_result == defender_result:
        Tie: Higher modifier wins
        (If still tied: defender/observer wins)
    Else if attacker_result <= defender_result - 10:
        Critical Failure for attacker
    Else:
        Failure for attacker
    ↓
Apply outcome:
    Example (Stealth vs Perception):
        Critical Success: Character is undetected
        Success: Character is hidden
        Failure: Character is observed
        Critical Failure: Character spotted and gives away allies' positions
    ↓
Log both rolls to skill_check_results
    ↓
Return result
```

### Secret Check Flow

```
GM initiates secret check (e.g., Sense Motive, Seek)
    ↓
SkillCheckController::secretCheck()
    ↓
Roll skill check normally
    ↓
Store in skill_check_results with is_secret = TRUE
    ↓
Return result only to GM:
    - Player UI shows "You attempt to [action]..."
    - GM UI shows full roll result and degree
    - GM narrates outcome based on result
    ↓
If critical failure leading to false info:
    GM gives misleading information
    Character believes false info is true
```

### Recall Knowledge Flow

```
Player attempts to recall knowledge about creature/topic
    ↓
SkillCheckController::recallKnowledge()
    ↓
Determine appropriate skill:
    Load subject (monster, item, phenomenon)
    Check recall_knowledge_topics for primary_skill
    
    Examples:
    - Dragon → Arcana
    - Undead → Religion
    - Animal → Nature
    - Historical event → Society
    ↓
Calculate DC:
    DC = subject level (from difficulty_class_tables)
    Apply rarity adjustments:
        Common: +0
        Uncommon: +2
        Rare: +5
        Unique: +10
    ↓
Optional: Make as secret check (GM decides)
SkillCheckController::secretCheck() OR ::roll()
    ↓
Determine information revealed based on degree:
    
    Critical Success:
        - Full stat block revealed (if monster)
        - Learn unusual weaknesses
        - Learn special abilities
        - GM provides extensive information
    
    Success:
        - Basic information revealed
        - Creature type, general abilities
        - GM provides useful tidbits
    
    Failure:
        - No information
        - "You don't recall anything useful"
    
    Critical Failure:
        - False information (secret check)
        - OR waste time (if not secret)
    ↓
Return information package to GM
    GM narrates what character recalls
```

## Functions Required

### DifficultyCalculationService

**Purpose**: Calculate DCs for various situations

#### `getSimpleDC($difficulty_rank)`
- Lookup simple DC by rank (0-4)
- Parameters: rank (0=Untrained, 1=Trained, 2=Expert, 3=Master, 4=Legendary)
- Returns: DC value (10, 15, 20, 30, 40)

#### `getLevelBasedDC($level)`
- Lookup DC by level (0-20+)
- Parameters: level
- Returns: DC value

#### `adjustDCForRarity($base_dc, $rarity)`
- Apply rarity modifier
- Parameters: base DC, rarity (common/uncommon/rare/unique)
- Returns: Adjusted DC (+0/+2/+5/+10)

#### `calculateClassDC($level, $key_ability_modifier, $proficiency_rank)`
- Calculate spell DC or class DC
- Formula: `10 + level + key_ability_mod + proficiency_bonus`
- Parameters: level, ability mod, proficiency
- Returns: Class/Spell DC

#### `calculateSavingThrowDC($level, $ability_modifier, $proficiency_rank)`
- Calculate character's save DC for others to target
- Formula: `10 + level + ability_mod + proficiency_bonus`
- Parameters: level, ability mod, proficiency
- Returns: Save DC

### SkillModifierCalculationService

**Purpose**: Calculate total skill modifiers

#### `calculateSkillModifier($character, $skill_name, $bonuses[], $penalties[])`
- Calculate complete skill modifier
- Components:
  - Proficiency bonus (rank + level)
  - Ability modifier
  - Item bonuses (best only)
  - Status bonuses (best only)
  - Circumstance bonuses (best only)
  - All penalties (stacking)
- Parameters: character object, skill name, bonus/penalty arrays
- Returns: Total modifier

#### `getProficiencyBonus($character_level, $proficiency_rank)`
- Calculate proficiency bonus
- Formula: `rank_value + character_level`
- Rank values: untrained=0, trained=2, expert=4, master=6, legendary=8
- Parameters: level, rank
- Returns: Proficiency bonus

#### `getAbilityModifierForSkill($character, $skill_name)`
- Lookup skill's ability score
- Get character's modifier for that ability
- Parameters: character, skill name
- Returns: Ability modifier

#### `aggregateBonuses($bonus_array[], $type)`
- Apply bonus aggregation rules (highest of each type)
- Parameters: bonus array, type (item/status/circumstance/untyped)
- Returns: Best bonus of that type

#### `aggregatePenalties($penalty_array[])`
- Stack all penalties (PF2E rules)
- Parameters: penalty array
- Returns: Total penalties

### DegreeOfSuccessService

**Purpose**: Determine outcome degrees

#### `determineDegree($roll_result, $dc, $is_natural_20, $is_natural_1)`
- Calculate degree of success
- Handle natural 20/1 adjustments
- Parameters: result, DC, natural flags
- Returns: Degree enum (critical_success, success, failure, critical_failure)

#### `improveDegree($current_degree)`
- Improve by one step (natural 20, fortune effects)
- Parameters: current degree
- Returns: Improved degree

#### `worsenDegree($current_degree)`
- Worsen by one step (natural 1, misfortune effects)
- Parameters: current degree
- Returns: Worsened degree

#### `compareDegrees($result_a, $result_b)`
- Compare two degrees for opposed checks
- Parameters: two degrees
- Returns: Winner determination

## Data Requirements Per Function

### Making Skill Check:
- Load: `character_skill_proficiencies` for skill proficiency rank
- Load: `characters` for level and ability scores
- Load: `game_skills` for which ability score applies
- Load: `combat_conditions` if in combat (for status penalties)
- Load: `character_inventory` for item bonuses (tools, magic items)
- Calculate: Proficiency bonus, ability modifier, total bonuses/penalties
- Roll: d20
- Calculate: Final result
- Insert: `skill_check_results` log

### Skill-Based Combat Action:
- All of above for skill check
- Load: `combat_participants` for target's DC
- Load: `combat_encounters` for context
- Execute: Skill check
- Apply: Condition or effect based on degree
- Update: `combat_participants` (actions remaining)
- Insert: `combat_actions` log
- Insert: `combat_conditions` if condition applied

### Recall Knowledge:
- Load: Subject (from `game_monsters` or other table)
- Load: `recall_knowledge_topics` to determine appropriate skill
- Load: `difficulty_class_tables` for level-based DC
- Execute: Skill check (often secret)
- Return: Information package based on degree

### Opposed Check:
- Load: Both participants' skill proficiencies and modifiers
- Roll: Both d20s
- Calculate: Both results
- Compare: Determine winner
- Insert: Two `skill_check_results` entries
- Return: Outcome

## API Endpoints Summary

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/skill-checks/roll` | Make skill check |
| POST | `/skill-checks/opposed` | Make opposed check |
| POST | `/skill-checks/secret` | GM makes secret check |
| GET | `/skill-checks/dc/calculate` | Calculate DC |
| GET | `/skill-checks/modifier/{character}/{skill}` | Get skill modifier |
| POST | `/skill-checks/recall-knowledge` | Recall knowledge about subject |
| GET | `/skill-checks/history/{character}` | Get character's skill check history |
| POST | `/skill-actions/demoralize` | Intimidation: Demoralize |
| POST | `/skill-actions/feint` | Deception: Feint |
| POST | `/skill-actions/create-diversion` | Deception: Create Diversion |
| POST | `/skill-actions/hide` | Stealth: Hide |
| POST | `/skill-actions/sneak` | Stealth: Sneak |
| POST | `/skill-actions/seek` | Perception: Seek |
| POST | `/skill-actions/sense-motive` | Perception: Sense Motive |
| POST | `/skill-actions/grapple` | Athletics: Grapple |
| POST | `/skill-actions/shove` | Athletics: Shove |
| POST | `/skill-actions/trip` | Athletics: Trip |
| POST | `/skill-actions/disarm` | Athletics: Disarm |
| POST | `/skill-actions/tumble-through` | Acrobatics: Tumble Through |
| POST | `/skill-actions/balance` | Acrobatics: Balance |
| POST | `/skill-actions/climb` | Athletics: Climb |
| POST | `/skill-actions/swim` | Athletics: Swim |
| POST | `/skill-actions/treat-wounds` | Medicine: Treat Wounds |
| POST | `/skill-actions/identify-magic` | Various: Identify Magic |

## Success Criteria

- ✅ All 17 core skills defined in database
- ✅ Proficiency ranks correctly affect skill modifiers (untrained through legendary)
- ✅ Four degrees of success calculated correctly (including natural 20/1 adjustments)
- ✅ Bonus aggregation rules enforced (highest of each type)
- ✅ Penalty stacking applied correctly
- ✅ Simple DCs and level-based DCs lookupable
- ✅ Opposed checks resolved with both rolls
- ✅ Secret checks hidden from player UI
- ✅ Recall Knowledge automatically selects appropriate skill
- ✅ All common skill actions functional (Demoralize, Feint, Trip, Grapple, etc.)
- ✅ Skill-based combat actions integrate with combat system
- ✅ Attack trait actions apply MAP correctly
- ✅ Skill check history logged for analytics

## UI Components Needed

### Skill Check Dialog
- Skill dropdown/selection
- DC input or auto-calculation
- Situational modifier inputs (+/- fields)
- "Roll" button
- Result display with breakdown:
  - d20 roll result
  - Modifiers list
  - Total
  - Degree of success (color-coded)

### Character Sheet Skill List
- All skills with current modifiers
- Click skill to roll
- Proficiency rank indicators (U/T/E/M/L)
- Ability score shown
- Common uses listed

### Combat Skill Actions Panel
- Quick buttons for combat-relevant skill actions
- Demoralize, Feint, Trip, Grapple, etc.
- Show action cost (1-action icon)
- Disabled if not enough actions

### Skill Check History Log
- Chronological list of recent checks
- Filterable by skill, success/failure
- Expandable for full details
- Useful for GM review

## Future Enhancements

- Skill challenge system (multiple interconnected checks)
- Group skill checks (entire party rolls)
- Take 10 (Assurance feat) automation
- Passive Perception system
- Skill training advancement tracking
- Suggested skills for situations (AI-assisted)
- Skill check probabilities calculator
- Custom Lore skills creator
- Skill feat recommendations
- Mobile-optimized skill roller
