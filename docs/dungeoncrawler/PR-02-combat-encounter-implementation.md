# PR-02: Combat Encounter System Implementation

## Verification Notes (2026-02-18)

- This document is target-state implementation design.
- Current module runtime exposes a lightweight combat encounter API via `CombatEncounterApiController` (`/api/combat/start`, `/api/combat/end-turn`, `/api/combat/attack`, `/api/combat/get`, `/api/combat/set`).
- Full controller/service surface described here should be treated as planned unless directly present in current routing/controller code.

## Overview
Implement a real-time combat encounter system that manages initiative, turn order, actions, damage, conditions, and encounter lifecycle. The system must enforce Pathfinder 2E combat rules including the 3-action economy, Multiple Attack Penalty (MAP), and condition effects.

## Reference Documentation
Additional detailed game mechanics documentation is available in the `reference documentation/` subdirectory:
- PF2E Core Rulebook - Fourth Printing.txt
- PF2E Gamemastery Guide.txt
- PF2E Bestiary 1.txt, Bestiary 2.txt, Bestiary 3.txt
- Other supplementary rulebooks

These reference materials provide comprehensive rules for combat mechanics, conditions, monster stat blocks, and encounter building that should be consulted during implementation.

## Controller Design

### CombatController

**Purpose**: Manage combat encounters and battle flow

**Route Base**: `/combat`

**Key Methods**:

#### `index()`
- Display list of active/historical combats for GM
- Parameters: `campaign_id`, `status` filter
- Returns: Combat encounters list view

#### `create(Request $request)`
- Initialize new combat encounter
- Generate initiative rolls for all participants
- Parameters: `campaign_id`, `character_ids[]`, `monster_ids[]`, `encounter_name`
- Returns: Combat ID and redirect to combat view

#### `show($combat_id)`
- Display combat encounter interface
- Real-time combat tracker UI
- Parameters: `combat_id`
- Returns: Combat view with initiative order, HP tracking, turn management

#### `rollInitiative(Request $request, $combat_id)`
- Roll initiative for all participants
- Determine turn order
- Parameters: `combat_id`, `participants[]` with perception modifiers
- Returns: Sorted initiative order

#### `startRound($combat_id)`
- Begin next combat round
- Decrement round-based effect durations
- Reset action economy for all participants
- Returns: Round number and first participant's turn

#### `startTurn($combat_id, $participant_id)`
- Begin specific participant's turn
- Grant 3 actions + 1 reaction
- Trigger start-of-turn effects
- Parameters: `combat_id`, `participant_id`
- Returns: Available actions and current status

#### `endTurn($combat_id, $participant_id)`
- Complete participant's turn
- Apply end-of-turn effects
- Process persistent damage
- Advance to next participant
- Parameters: `combat_id`, `participant_id`
- Returns: Next participant info

#### `takeAction(Request $request, $combat_id)`
- Execute combat action (Strike, Stride, Cast Spell, etc.)
- Update action economy
- Apply Multiple Attack Penalty if applicable
- Parameters: `combat_id`, `participant_id`, `action_type`, `action_data`
- Returns: Action result and updated combat state

#### `makeAttackRoll(Request $request, $combat_id)`
- Roll attack against target
- Calculate bonuses/penalties (MAP, flanking, conditions)
- Determine degree of success
- Parameters: `attacker_id`, `target_id`, `weapon_id`, `attack_number`
- Returns: Attack result with damage roll prompt

#### `rollDamage(Request $request, $combat_id)`
- Roll damage for successful attack
- Apply resistances/weaknesses
- Update target HP
- Parameters: `attack_result`, `damage_dice`, `modifiers`
- Returns: Damage dealt and updated HP

#### `applyCondition(Request $request, $combat_id)`
- Add condition to participant
- Track condition duration and effects
- Parameters: `participant_id`, `condition_name`, `value`, `duration`
- Returns: Updated participant status

#### `removeCondition(Request $request, $combat_id)`
- Remove condition from participant
- Parameters: `participant_id`, `condition_id`
- Returns: Updated participant status

#### `updateHP(Request $request, $combat_id)`
- Modify participant HP (damage/healing)
- Check for dying/unconscious conditions
- Parameters: `participant_id`, `hp_change`, `change_type`
- Returns: Updated HP and condition status

#### `delay(Request $request, $combat_id)`
- Participant delays turn to act later
- Adjust initiative order
- Parameters: `participant_id`
- Returns: Updated initiative order

#### `ready(Request $request, $combat_id)`
- Prepare readied action with trigger
- Parameters: `participant_id`, `action_data`, `trigger_description`
- Returns: Readied action registered

#### `useReaction(Request $request, $combat_id)`
- Execute reaction (Attack of Opportunity, Shield Block, etc.)
- Mark reaction as used
- Parameters: `participant_id`, `reaction_type`, `trigger_data`
- Returns: Reaction result

#### `endEncounter(Request $request, $combat_id)`
- Conclude combat encounter
- Award XP
- Log encounter results
- Parameters: `combat_id`, `outcome`
- Returns: Summary and XP awards

#### `getEncounterState($combat_id)`
- Fetch current combat state for real-time updates
- Parameters: `combat_id`
- Returns: JSON with all participant statuses, initiative, current turn

## Schema Design

### combat_encounters

```sql
CREATE TABLE combat_encounters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NOT NULL,
    encounter_name VARCHAR(255),
    current_round TINYINT UNSIGNED DEFAULT 1,
    current_turn_participant_id BIGINT UNSIGNED NULL,
    status ENUM('preparing', 'active', 'paused', 'ended') DEFAULT 'preparing',
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    outcome ENUM('victory', 'defeat', 'retreat', 'truce', 'other') NULL,
    total_xp_awarded INT UNSIGNED DEFAULT 0,
    encounter_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    INDEX idx_campaign_status (campaign_id, status),
    INDEX idx_status (status)
);
```

### combat_participants

```sql
CREATE TABLE combat_participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combat_encounter_id BIGINT UNSIGNED NOT NULL,
    participant_type ENUM('character', 'monster', 'npc') NOT NULL,
    character_id BIGINT UNSIGNED NULL,
    monster_id BIGINT UNSIGNED NULL,
    
    -- Initiative
    initiative_roll TINYINT UNSIGNED NOT NULL,
    initiative_modifier TINYINT NOT NULL,
    initiative_total TINYINT NOT NULL,
    
    -- HP Tracking
    max_hp INT UNSIGNED NOT NULL,
    current_hp INT NOT NULL, -- Can go negative for massive damage
    temp_hp INT UNSIGNED DEFAULT 0,
    
    -- Action Economy
    actions_remaining TINYINT UNSIGNED DEFAULT 3,
    reaction_available BOOLEAN DEFAULT TRUE,
    
    -- Attack Tracking
    attacks_this_turn TINYINT UNSIGNED DEFAULT 0,
    current_map_penalty TINYINT DEFAULT 0,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE, -- Participating in combat
    is_conscious BOOLEAN DEFAULT TRUE,
    
    -- Positioning
    position_x INT NULL, -- Grid coordinates if using battle mat
    position_y INT NULL,
    
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    INDEX idx_encounter_initiative (combat_encounter_id, initiative_total DESC),
    INDEX idx_encounter (combat_encounter_id)
);
```

### combat_conditions

```sql
CREATE TABLE combat_conditions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combat_participant_id BIGINT UNSIGNED NOT NULL,
    condition_type ENUM(
        'blinded', 'broken', 'clumsy', 'concealed', 'confused', 'controlled',
        'dazzled', 'deafened', 'doomed', 'drained', 'dying', 'enfeebled',
        'fascinated', 'fatigued', 'flat_footed', 'fleeing', 'friendly',
        'frightened', 'grabbed', 'helpful', 'hidden', 'hostile', 'immobilized',
        'indifferent', 'invisible', 'observed', 'paralyzed', 'persistent_damage',
        'petrified', 'prone', 'quickened', 'restrained', 'sickened', 'slowed',
        'stunned', 'stupefied', 'unconscious', 'undetected', 'unfriendly',
        'unnoticed', 'wounded'
    ) NOT NULL,
    condition_value TINYINT UNSIGNED NULL, -- For valued conditions (frightened 2, etc.)
    duration_type ENUM('rounds', 'turns', 'minutes', 'permanent', 'sustained') DEFAULT 'rounds',
    duration_remaining INT UNSIGNED NULL,
    source_description VARCHAR(255), -- "Goblin Warrior's spell"
    applied_at_round TINYINT UNSIGNED,
    
    FOREIGN KEY (combat_participant_id) REFERENCES combat_participants(id) ON DELETE CASCADE,
    INDEX idx_participant (combat_participant_id),
    INDEX idx_type (condition_type)
);
```

### combat_actions

```sql
CREATE TABLE combat_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combat_encounter_id BIGINT UNSIGNED NOT NULL,
    round_number TINYINT UNSIGNED NOT NULL,
    participant_id BIGINT UNSIGNED NOT NULL,
    action_type ENUM(
        'stride', 'strike', 'step', 'interact', 'seek', 'raise_shield',
        'take_cover', 'cast_spell', 'leap', 'crawl', 'ready', 'delay',
        'demoralize', 'feint', 'aid', 'escape', 'grapple', 'shove',
        'trip', 'disarm', 'force_open', 'hide', 'sneak', 'administer_first_aid',
        'treat_wounds', 'other'
    ) NOT NULL,
    actions_spent TINYINT UNSIGNED NOT NULL, -- 1, 2, or 3
    target_id BIGINT UNSIGNED NULL, -- Target participant if applicable
    
    -- Attack Details (if Strike)
    attack_roll TINYINT NULL,
    attack_total TINYINT NULL,
    target_ac TINYINT UNSIGNED NULL,
    degree_of_success ENUM('critical_success', 'success', 'failure', 'critical_failure') NULL,
    damage_dealt INT UNSIGNED NULL,
    map_penalty_applied TINYINT NULL,
    
    -- Movement Details
    distance_moved SMALLINT UNSIGNED NULL, -- Feet moved
    
    -- Spell Details
    spell_cast VARCHAR(100) NULL,
    spell_level TINYINT UNSIGNED NULL,
    
    action_details JSON, -- Flexible field for action-specific data
    action_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES combat_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (target_id) REFERENCES combat_participants(id) ON DELETE SET NULL,
    INDEX idx_encounter_round (combat_encounter_id, round_number),
    INDEX idx_participant (participant_id)
);
```

### combat_reactions

```sql
CREATE TABLE combat_reactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    combat_encounter_id BIGINT UNSIGNED NOT NULL,
    round_number TINYINT UNSIGNED NOT NULL,
    participant_id BIGINT UNSIGNED NOT NULL,
    reaction_type ENUM(
        'attack_of_opportunity', 'shield_block', 'retributive_strike',
        'nimble_dodge', 'aid', 'readied_action', 'other'
    ) NOT NULL,
    trigger_description TEXT,
    trigger_action_id BIGINT UNSIGNED NULL, -- Action that triggered reaction
    
    -- Reaction Result
    attack_roll TINYINT NULL,
    damage_dealt INT UNSIGNED NULL,
    damage_prevented INT UNSIGNED NULL, -- For Shield Block
    
    reaction_details JSON,
    reaction_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (combat_encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES combat_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (trigger_action_id) REFERENCES combat_actions(id) ON DELETE SET NULL,
    INDEX idx_encounter_round (combat_encounter_id, round_number)
);
```

### game_monsters

```sql
CREATE TABLE game_monsters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    level TINYINT NOT NULL, -- Can be negative for very weak creatures
    creature_type VARCHAR(50), -- "humanoid", "beast", "undead", etc.
    size ENUM('tiny', 'small', 'medium', 'large', 'huge', 'gargantuan') NOT NULL,
    alignment VARCHAR(20),
    
    -- Perception
    perception_modifier TINYINT NOT NULL,
    special_senses JSON, -- ["darkvision 60 ft", "scent"]
    
    -- Defenses
    ac TINYINT UNSIGNED NOT NULL,
    fortitude_save TINYINT NOT NULL,
    reflex_save TINYINT NOT NULL,
    will_save TINYINT NOT NULL,
    max_hp INT UNSIGNED NOT NULL,
    
    -- Resistances/Immunities/Weaknesses
    immunities JSON, -- ["fire", "poison"]
    resistances JSON, -- {"fire": 10, "cold": 5}
    weaknesses JSON, -- {"fire": 5}
    
    -- Offense
    speed TINYINT UNSIGNED NOT NULL,
    special_speeds JSON, -- {"climb": 20, "fly": 30}
    melee_attacks JSON, -- Array of melee attack objects
    ranged_attacks JSON, -- Array of ranged attack objects
    
    -- Abilities
    ability_str TINYINT NOT NULL,
    ability_dex TINYINT NOT NULL,
    ability_con TINYINT NOT NULL,
    ability_int TINYINT NOT NULL,
    ability_wis TINYINT NOT NULL,
    ability_cha TINYINT NOT NULL,
    
    -- Skills
    skills JSON, -- {"athletics": 12, "stealth": 8}
    
    -- Special Abilities
    special_abilities JSON, -- Array of ability objects
    spells JSON, -- Spell list if creature can cast spells
    
    traits JSON, -- ["goblin", "humanoid"]
    languages JSON, -- ["Common", "Goblin"]
    
    source_book VARCHAR(50) DEFAULT 'Bestiary 1',
    description TEXT,
    
    INDEX idx_name (name),
    INDEX idx_level (level),
    INDEX idx_type (creature_type)
);
```

**Example melee_attacks JSON**:
```json
[
  {
    "name": "Dogslicer",
    "attack_bonus": 9,
    "damage": "1d6+3",
    "damage_type": "slashing",
    "traits": ["agile", "backstabber", "finesse"]
  }
]
```

## Process Flow

### Starting Combat Encounter

```
GM clicks "Start Combat"
    ↓
CombatController::create()
    ↓
Select participants (characters + monsters)
    ↓
Roll initiative for all
    ↓
INSERT combat_encounters (status='active', round=1)
INSERT combat_participants (with initiative rolls)
    ↓
Sort participants by initiative (high to low)
    ↓
Set current_turn_participant_id to first in order
    ↓
CombatController::startRound(1)
    ↓
Display combat tracker interface
```

### Combat Round Flow

```
Round N begins
    ↓
CombatController::startRound($combat_id)
    - Decrement all round-based condition durations
    - Check for expired conditions (frightened, stunned, etc.)
    ↓
For each participant in initiative order:
    ↓
    CombatController::startTurn($participant_id)
        - Grant 3 actions
        - Grant 1 reaction
        - Reset MAP to 0
        - Trigger start-of-turn effects
        - Roll recovery check if dying
        ↓
    Participant takes actions:
        CombatController::takeAction() (up to 3 times)
            - Deduct action(s) from actions_remaining
            - Execute action logic
            - Update combat state
            
        Examples:
        - Strike: makeAttackRoll() → rollDamage() → updateHP()
        - Stride: Update position, deduct 1 action
        - Cast Spell: Deduct 2 actions, trigger spell effect
        - Raise Shield: Apply +2 AC until next turn start
        ↓
    CombatController::endTurn($participant_id)
        - Apply persistent damage
        - Trigger end-of-turn effects
        - Remove expired buffs
        - Mark reaction as used if expended
        - Advance to next participant
    ↓
All participants have taken turns
    ↓
Round ends
    ↓
CombatController::startRound(N+1)
```

### Attack Resolution Flow

```
Attacker clicks "Strike"
    ↓
CombatController::makeAttackRoll()
    ↓
Calculate attack bonus:
    = weapon_proficiency_bonus
    + ability_modifier (Str/Dex)
    + weapon_item_bonus
    - MAP penalty (0/-5/-10 based on attacks_this_turn)
    - condition penalties (flat-footed, etc.)
    + circumstance bonuses (flanking +2)
    ↓
Roll d20 + bonuses
    ↓
Compare to target AC
    ↓
Determine degree of success:
    - Critical Success: roll ≥ AC+10 OR natural 20
    - Success: roll ≥ AC
    - Failure: roll < AC
    - Critical Failure: roll ≤ AC-10 OR natural 1
    ↓
If Success or Critical Success:
    CombatController::rollDamage()
        Roll weapon damage dice
        Add Strength modifier (melee) or Dexterity (ranged)
        Apply critical hit rules (double damage dice)
        ↓
        Apply target resistances/weaknesses
        ↓
        CombatController::updateHP(target, -damage)
            Reduce current_hp
            Check if HP ≤ 0:
                If HP = 0: unconscious
                If HP < 0 and > -Constitution: dying 1
                If HP ≤ -Constitution: dying 2
            ↓
            If dying condition gained:
                CombatController::applyCondition('dying')
    ↓
Increment attacks_this_turn
Update current_map_penalty
    ↓
Log action to combat_actions table
```

### Condition Management Flow

```
Condition applied to participant
    ↓
CombatController::applyCondition()
    ↓
INSERT combat_conditions
    (participant_id, condition_type, value, duration)
    ↓
Apply immediate effects:
    - flat_footed: -2 AC
    - frightened X: -X to all checks/DCs
    - prone: -2 AC vs melee, +2 AC vs ranged
    - slowed X: reduce actions by X
    - stunned X: lose X actions
    - persistent_damage: track for end-of-turn
    ↓
At end of each round:
    Decrement duration_remaining for 'rounds' type
    Remove if duration_remaining = 0
    ↓
At end of participant's turn:
    Decrement duration_remaining for 'turns' type
    Apply end-of-turn effects (persistent damage)
```

### Ending Combat

```
All enemies defeated/fled OR party retreats
    ↓
CombatController::endEncounter()
    ↓
Calculate XP awards (level-based, challenge rating)
    ↓
UPDATE combat_encounters
    SET status='ended', ended_at=NOW(), outcome='victory'
    ↓
Award XP to surviving characters
    ↓
Log summary to combat_actions
    ↓
Check for level-ups (XP ≥ 1000)
    ↓
Return to campaign/exploration view
```

## Functions Required

### CombatCalculationService

**Purpose**: Combat-specific calculations and rule enforcement

#### `calculateInitiative($perception_modifier, $bonuses[])`
- Roll: `d20 + perception_modifier + sum(bonuses)`
- Parameters: perception mod, bonus array
- Returns: initiative total

#### `sortInitiativeOrder($participants[])`
- Sort by initiative_total DESC
- Tie-breaker: NPCs before PCs
- Parameters: array of participants with initiative
- Returns: sorted participant list

#### `calculateAttackBonus($proficiency, $ability_mod, $item_bonus, $map, $bonuses[], $penalties[])`
- Formula: `proficiency + ability_mod + item_bonus - map + sum(bonuses) - sum(penalties)`
- Parameters: all attack modifiers
- Returns: total attack bonus

#### `calculateMAP($attacks_this_turn, $is_agile_weapon)`
- MAP: -5/-10 for normal, -4/-8 for agile
- Parameters: attack count, weapon agility
- Returns: MAP penalty

#### `determineDegreeOfSuccess($roll, $dc, $is_natural_1, $is_natural_20)`
- Critical Success: roll ≥ DC+10 OR natural 20 (improves by 1 step)
- Success: roll ≥ DC
- Failure: roll < DC
- Critical Failure: roll ≤ DC-10 OR natural 1 (worsens by 1 step)
- Parameters: d20 result, target DC, natural 1/20 flags
- Returns: degree enum

#### `rollDamage($damage_dice, $ability_modifier, $is_critical, $bonuses[])`
- Roll damage dice
- Add modifiers
- If critical: double dice rolls (not modifiers)
- Parameters: dice notation, ability mod, critical flag, bonuses
- Returns: total damage

#### `applyResistancesWeaknesses($damage, $damage_type, $resistances, $weaknesses)`
- Apply resistances: `damage - resistance_value` (minimum 0)
- Apply weaknesses: `damage + weakness_value`
- Parameters: base damage, type, resistance/weakness objects
- Returns: final damage after adjustments

#### `checkFlanking($attacker_position, $ally_positions[], $target_position)`
- Check if attacker and ally on opposite sides of target
- Both must threaten target (within reach)
- Parameters: positions on grid
- Returns: boolean (is flanking)

#### `calculateAC($base_ac, $dex_mod, $armor_bonus, $shield_raised, $conditions[])`
- Formula: `10 + dex_mod + armor_bonus + shield_bonus - condition_penalties`
- Apply flat-footed: -2 AC
- Apply prone: -2 AC vs melee, +2 vs ranged
- Parameters: all AC components
- Returns: total AC

#### `applyConditionEffects($participant, $condition_type, $value)`
- Modify participant stats based on condition
- Examples:
  - frightened X: -X to all checks
  - clumsy X: -X to Dex-based checks, Reflex saves
  - enfeebled X: -X to Str-based checks, melee damage
  - stunned X: lose X actions
  - slowed X: reduce max actions by X
- Parameters: participant object, condition, value
- Returns: modified participant stats

#### `processEndOfTurn($participant)`
- Apply persistent damage (roll flat check DC 15 to end)
- Remove "until end of turn" effects
- Decrement condition durations (turn-based)
- Reduce frightened value by 1
- Parameters: participant object
- Returns: updated participant with condition changes

#### `processDyingCondition($participant, $constitution_modifier)`
- Roll recovery check: d20 + con mod vs DC 10+dying value
- Critical Success: reduce dying by 2
- Success: reduce dying by 1
- Failure: increase dying by 1
- Critical Failure: increase dying by 2
- If dying reaches 4: character dies
- If dying reduced to 0: become wounded, gain 1 HP
- Parameters: participant, con mod
- Returns: dying condition result

#### `calculateCover($attacker_position, $target_position, $terrain[])`
- Standard cover: +2 AC, +2 Reflex saves
- Greater cover: +4 AC, +4 Reflex saves
- Parameters: positions, terrain obstacles
- Returns: cover bonus amount

#### `calculateDistanceMoved($actions_spent, $speed, $difficult_terrain)`
- Normal: actions × speed
- Difficult terrain: halve movement
- Parameters: actions, speed, terrain type
- Returns: distance in feet

## Data Requirements Per Function

### Starting Combat:
- Load: `characters` for campaign (character stats)
- Load: `game_monsters` for encounter (monster stats)
- Roll: initiative for each participant
- Insert: `combat_encounters` row
- Insert: `combat_participants` rows (one per character/monster)

### During Combat Turn:
- Load: `combat_participants` for current encounter (ordered by initiative)
- Load: `combat_conditions` for participant (active conditions)
- Load: `character_inventory` for equipped weapons/armor
- Load: `game_equipment` for weapon/armor stats
- Calculate: attack bonuses, AC, damage
- Update: `combat_participants` (HP, actions, MAP)
- Insert: `combat_actions` (log all actions)

### Attack Resolution:
- Load: attacker's weapon from `character_inventory` + `game_equipment`
- Load: attacker's proficiencies from `character_proficiencies`
- Load: target's AC from `combat_participants`
- Load: target's conditions from `combat_conditions`
- Calculate: attack bonus with MAP
- Roll: d20 + bonuses vs AC
- If hit, roll: damage dice + modifiers
- Update: target's current_hp in `combat_participants`
- Insert: `combat_actions` with attack details

### Condition Application:
- Insert: `combat_conditions` row
- Update: `combat_participants` (if condition affects immediate stats)
- Check: condition effects per type
- At end of turn/round: UPDATE duration_remaining, DELETE if expired

### Ending Combat:
- Calculate: XP based on monster levels and party level
- Update: `characters` (add XP to experience_points)
- Update: `combat_encounters` (status='ended', outcome)
- Check: if any character XP ≥ 1000 (level up prompt)

## API Endpoints Summary

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/combat` | List active/past combats |
| POST | `/combat/create` | Start new combat encounter |
| GET | `/combat/{id}` | Display combat tracker UI |
| POST | `/combat/{id}/initiative` | Roll and set initiative |
| POST | `/combat/{id}/start-round` | Begin new round |
| POST | `/combat/{id}/start-turn/{participant}` | Begin participant's turn |
| POST | `/combat/{id}/end-turn/{participant}` | End participant's turn |
| POST | `/combat/{id}/action` | Execute action |
| POST | `/combat/{id}/attack` | Make attack roll |
| POST | `/combat/{id}/damage` | Roll damage |
| POST | `/combat/{id}/condition/apply` | Apply condition |
| DELETE | `/combat/{id}/condition/{condition_id}` | Remove condition |
| PATCH | `/combat/{id}/hp` | Update HP |
| POST | `/combat/{id}/delay` | Delay turn |
| POST | `/combat/{id}/ready` | Ready an action |
| POST | `/combat/{id}/reaction` | Use reaction |
| POST | `/combat/{id}/end` | End encounter |
| GET | `/combat/{id}/state` | Get current combat state (AJAX polling) |

## Success Criteria

- ✅ Combat encounters track initiative and turn order correctly
- ✅ 3-action economy enforced per turn
- ✅ Multiple Attack Penalty applied correctly (-5/-10 or -4/-8 for agile)
- ✅ Attack rolls calculate with all bonuses/penalties (flanking, conditions, MAP)
- ✅ Damage applies resistances/weaknesses correctly
- ✅ HP tracking with dying/unconscious conditions
- ✅ Conditions apply appropriate effects and track durations
- ✅ Reactions usable once per round when triggered
- ✅ Round-based and turn-based durations decrement correctly
- ✅ Combat log records all actions taken
- ✅ XP awarded correctly at end of encounter
- ✅ Real-time UI updates for all players and GM

## Future Enhancements

- Battle map integration with grid positioning
- Movement range visualization
- Area of effect spell visualization
- Automated monster AI for GM assistance
- Combat analytics and statistics
- Import/export encounter setups
- Encounter builder with difficulty calculator
- Sound effects and animations
- Mobile-optimized combat tracker
- Simultaneous turn mode (faster combat for minions)
