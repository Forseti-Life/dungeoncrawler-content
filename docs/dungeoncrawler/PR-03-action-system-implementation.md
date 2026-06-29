# PR-03: Action System Implementation

## Verification Notes (2026-02-18)

- This file defines intended action-system architecture and should be treated as a design/implementation plan.
- Active code paths currently use a mix of lightweight combat APIs and ECS runtime behavior; not all controllers/services listed here are implemented as routed endpoints.
- Validate current behavior in `dungeoncrawler_content.routing.yml` and `src/Controller/*` before operational use.

## Overview
Implement a comprehensive action library and execution system for the Pathfinder 2E three-action economy. The system must define all basic actions, activities, reactions, and free actions, enforce action costs (1/2/3 actions), manage traits (Attack, Manipulate, Move, Concentrate), and integrate with the combat encounter system.

## Reference Documentation
Additional detailed game mechanics documentation is available in the `reference documentation/` subdirectory:
- PF2E Core Rulebook - Fourth Printing.txt
- PF2E Advanced Players Guide.txt
- Other supplementary rulebooks

These reference materials provide comprehensive rules for all actions, activities, reactions, traits, and their interactions that should be consulted during implementation.

## Controller Design

### ActionController

**Purpose**: Manage action execution and action library

**Route Base**: `/actions`

**Key Methods**:

#### `index()`
- Display action library/compendium
- Filter by action type, traits, skills
- Returns: Action library view

#### `show($action_id)`
- Display detailed action information
- Show traits, requirements, effects
- Parameters: `action_id`
- Returns: Action detail view

#### `execute(Request $request)`
- Execute action during combat
- Validate requirements
- Apply effects
- Parameters: `combat_id`, `participant_id`, `action_id`, `target_id`, `action_data`
- Returns: Action execution result

#### `validateAction(Request $request)`
- Check if action can be performed
- Verify prerequisites, action economy, range
- Parameters: `participant_id`, `action_id`, `target_id`, `context`
- Returns: Validation result with errors if any

#### `getAvailableActions($participant_id, $combat_id)`
- List actions current participant can take
- Filter by remaining actions, conditions, equipment
- Parameters: `participant_id`, `combat_id`
- Returns: JSON array of available actions

#### `getActionsByTrait($trait)`
- Filter actions by trait
- Parameters: trait name (Attack, Manipulate, Move, etc.)
- Returns: JSON array of matching actions

#### `getActionsBySkill($skill_name)`
- Get skill-based actions (Demoralize, Feint, etc.)
- Parameters: skill name
- Returns: JSON array of skill actions

### ActionExecutionService

**Purpose**: Handle action execution logic and effects

**Key Methods**:

#### `executeStrike($attacker, $target, $weapon, $combat_id)`
- Perform Strike action
- Integrate with combat attack roll system
- Apply MAP
- Parameters: attacker participant, target, weapon, combat context
- Returns: Attack result

#### `executeStride($participant, $distance, $destination, $combat_id)`
- Move participant up to Speed
- Check for difficult terrain
- Trigger opportunity attacks if leaving threatened squares
- Parameters: participant, distance in feet, destination coordinates, combat
- Returns: New position

#### `executeStep($participant, $destination, $combat_id)`
- Move 5 feet without triggering reactions
- Validate not difficult terrain
- Parameters: participant, destination, combat
- Returns: New position or error

#### `executeInteract($participant, $object_id, $interaction_type, $combat_id)`
- Manipulate object (draw weapon, open door, etc.)
- Check for Attack of Opportunity triggers
- Parameters: participant, object, interaction type, combat
- Returns: Interaction result

#### `executeRaiseShield($participant, $shield_id, $combat_id)`
- Apply shield AC bonus until start of next turn
- Requires shield equipped
- Parameters: participant, shield, combat
- Returns: AC modifier applied

#### `executeTakeCover($participant, $cover_source, $combat_id)`
- Apply cover bonuses to AC and Reflex saves
- Validate cover available at position
- Parameters: participant, cover object/terrain, combat
- Returns: Cover bonuses applied

#### `executeReady($participant, $action_id, $trigger_description, $combat_id)`
- Store readied action for later reaction
- Consumes 2 actions
- Parameters: participant, action to ready, trigger, combat
- Returns: Readied action registered

#### `executeDelay($participant, $combat_id)`
- Move participant later in initiative order
- Free action
- Parameters: participant, combat
- Returns: Updated initiative order

#### `executeDemoralize($participant, $target, $combat_id)`
- Intimidation check vs target's Will DC
- On success: frightened 1, critical success: frightened 2
- Parameters: participant, target, combat
- Returns: Intimidation result and condition applied

#### `executeFeint($participant, $target, $combat_id)`
- Deception check vs target's Perception DC
- On success: target is flat-footed vs attacker until end of turn
- Parameters: participant, target, combat
- Returns: Feint result

#### `executeTrip($participant, $target, $combat_id)`
- Athletics check vs target's Reflex DC
- On success: target becomes prone
- Has Attack trait (applies MAP)
- Parameters: participant, target, combat
- Returns: Trip result

#### `executeGrapple($participant, $target, $combat_id)`
- Athletics check vs target's Fortitude DC
- On success: target is grabbed
- Has Attack trait (applies MAP)
- Parameters: participant, target, combat
- Returns: Grapple result

#### `executeShove($participant, $target, $direction, $combat_id)`
- Athletics check vs target's Fortitude DC
- On success: push target 5 feet
- Has Attack trait (applies MAP)
- Parameters: participant, target, direction, combat
- Returns: Shove result and new position

#### `executeDisarm($participant, $target, $item_id, $combat_id)`
- Athletics check vs target's Reflex DC
- On success: target drops item
- Has Attack trait (applies MAP)
- Parameters: participant, target, item, combat
- Returns: Disarm result

#### `executeHide($participant, $cover_available, $combat_id)`
- Stealth check vs observers' Perception DCs
- Requires cover or concealment
- Parameters: participant, cover status, combat
- Returns: Hidden status result

#### `executeSneak($participant, $destination, $combat_id)`
- Move while maintaining hidden status
- Stealth check vs observers' Perception DCs
- Parameters: participant, destination, combat
- Returns: Movement result and stealth status

#### `executeSeek($participant, $area, $combat_id)`
- Perception check to find hidden creatures/objects
- Parameters: participant, search area, combat
- Returns: Search results

#### `executeAid(Request $request, $combat_id)`
- Reaction: Help ally's check with +1 circumstance bonus
- Requires relevant skill check
- Parameters: participant, ally, check type, combat
- Returns: Aid result

#### `executeAttackOfOpportunity($participant, $target, $trigger_action, $combat_id)`
- Reaction: Strike when target uses manipulate/move action
- Requires feat: Attack of Opportunity
- Parameters: participant, target, triggering action, combat
- Returns: Attack result

#### `executeShieldBlock($participant, $incoming_damage, $shield, $combat_id)`
- Reaction: Shield absorbs damage
- Shield takes damage/breaks
- Parameters: participant, damage amount, shield, combat
- Returns: Damage absorbed, shield damage

## Schema Design

### game_actions

```sql
CREATE TABLE game_actions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    action_type ENUM('single_action', 'activity', 'reaction', 'free_action') NOT NULL,
    actions_cost TINYINT UNSIGNED, -- 1, 2, or 3 (NULL for reaction/free)
    
    -- Traits
    traits JSON, -- ["attack", "manipulate", "move", "concentrate", etc.]
    
    -- Requirements
    requirements TEXT, -- "You are wielding a melee weapon"
    skill_required VARCHAR(50) NULL, -- "athletics", "stealth", etc.
    feat_required INT UNSIGNED NULL, -- Feat ID if action requires feat
    
    -- Trigger (for reactions)
    trigger_description TEXT,
    
    -- Effects
    effect_type ENUM(
        'attack_roll', 'damage', 'movement', 'skill_check', 'condition_apply',
        'buff', 'debuff', 'healing', 'utility', 'special'
    ) NOT NULL,
    effect_details JSON, -- {"target_DC": "reflex", "condition": "prone", "duration": "instant"}
    
    -- Action Description
    description TEXT NOT NULL,
    success_text TEXT, -- What happens on success
    failure_text TEXT, -- What happens on failure
    critical_success_text TEXT,
    critical_failure_text TEXT,
    
    -- Limitations
    frequency VARCHAR(100), -- "once per turn", "once per round", etc.
    range_feet INT UNSIGNED NULL, -- Reach/range in feet
    
    -- Source
    source_book VARCHAR(50) DEFAULT 'Core Rulebook',
    page_number SMALLINT UNSIGNED,
    
    INDEX idx_name (name),
    INDEX idx_type (action_type),
    INDEX idx_skill (skill_required)
);
```

**Example game_actions rows**:

```sql
-- Strike
INSERT INTO game_actions (name, action_type, actions_cost, traits, effect_type, description) VALUES
('Strike', 'single_action', 1, '["attack"]', 'attack_roll', 'You attack with a weapon you\'re wielding or with an unarmed attack...');

-- Stride
INSERT INTO game_actions (name, action_type, actions_cost, traits, effect_type, description) VALUES
('Stride', 'single_action', 1, '["move"]', 'movement', 'You move up to your Speed...');

-- Demoralize
INSERT INTO game_actions (name, action_type, actions_cost, traits, skill_required, effect_type, effect_details, description) VALUES
('Demoralize', 'single_action', 1, '["auditory", "emotion", "mental", "fear"]', 
'intimidation', 'condition_apply', 
'{"target_DC": "will", "condition": "frightened", "value": 1, "critical_value": 2, "duration_rounds": 1}',
'With a sudden shout, a well-timed taunt, or a cutting insult, you can shake an enemy\'s resolve...');

-- Ready
INSERT INTO game_actions (name, action_type, actions_cost, traits, effect_type, description) VALUES
('Ready', 'activity', 2, '["concentrate"]', 'special', 'You prepare to use an action that will occur outside your turn...');

-- Attack of Opportunity
INSERT INTO game_actions (name, action_type, actions_cost, traits, trigger_description, effect_type, feat_required, description) VALUES
('Attack of Opportunity', 'reaction', NULL, '["attack"]', 
'A creature within your reach uses a manipulate action or a move action, makes a ranged attack, or leaves a square during a move action it\'s using',
'attack_roll', 234, -- Feat ID for Attack of Opportunity
'You lash out at a foe that leaves an opening...');
```

### action_trait_definitions

```sql
CREATE TABLE action_trait_definitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trait_name VARCHAR(50) UNIQUE NOT NULL,
    trait_category ENUM('basic', 'alignment', 'elemental', 'magical', 'school', 'tradition', 'weapon', 'other') NOT NULL,
    description TEXT,
    game_effects TEXT, -- How this trait affects gameplay
    
    -- Specific Effects
    triggers_aoo BOOLEAN DEFAULT FALSE, -- Triggers Attack of Opportunity
    affected_by_map BOOLEAN DEFAULT FALSE, -- Subject to Multiple Attack Penalty
    can_be_disrupted BOOLEAN DEFAULT FALSE, -- Can be disrupted (Concentrate trait)
    
    source_book VARCHAR(50) DEFAULT 'Core Rulebook'
);
```

**Example trait definitions**:

```sql
INSERT INTO action_trait_definitions (trait_name, trait_category, triggers_aoo, affected_by_map, description, game_effects) VALUES
('attack', 'basic', FALSE, TRUE, 'An ability with this trait involves an attack roll against a target\'s Armor Class',
 'Subject to Multiple Attack Penalty. Each additional attack on your turn takes an increasing penalty.'),
 
('manipulate', 'basic', TRUE, FALSE, 'You must physically manipulate an item or make gestures',
 'Triggers reactions like Attack of Opportunity. Can\'t be used while grabbed or restrained without special feat.'),
 
('move', 'basic', TRUE, FALSE, 'An action with this trait involves moving from one space to another',
 'Triggers reactions like Attack of Opportunity when leaving threatened squares.'),
 
('concentrate', 'basic', FALSE, FALSE, 'An action with this trait requires mental focus',
 'Can be disrupted by damage or environmental effects. If you take damage while using Concentrate action, you must make DC 5 flat check or action is disrupted.');
```

### character_action_restrictions

```sql
CREATE TABLE character_action_restrictions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    action_id INT UNSIGNED NOT NULL,
    restriction_type ENUM('unavailable', 'limited', 'disabled') NOT NULL,
    reason TEXT, -- "Missing required weapon", "Condition: Paralyzed"
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (action_id) REFERENCES game_actions(id),
    INDEX idx_character (character_id)
);
```

### action_macros (User-Created Action Shortcuts)

```sql
CREATE TABLE action_macros (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    macro_name VARCHAR(100) NOT NULL,
    action_sequence JSON, -- Array of action IDs in order
    description TEXT,
    icon VARCHAR(50),
    
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    INDEX idx_character (character_id),
    
    -- Example macro: "Full Attack" = [Strike, Strike, Strike]
    -- Example macro: "Defensive Move" = [Stride, Stride, Raise Shield]
);
```

**Example macro JSON**:
```json
{
  "actions": [
    {"action_id": 1, "action_name": "Strike", "target": "selected"},
    {"action_id": 1, "action_name": "Strike", "target": "same"},
    {"action_id": 15, "action_name": "Raise Shield"}
  ]
}
```

## Process Flow

### Action Execution Flow (During Combat)

```
Player clicks action button in combat UI
    ↓
ActionController::execute()
    ↓
Load game_actions row for selected action
    ↓
ActionController::validateAction()
    - Check actions_cost ≤ participant.actions_remaining
    - Check requirements met (weapon equipped, feat owned, etc.)
    - Check not restricted by conditions
    - Check target is valid (range, line of sight if applicable)
    ↓
If validation fails:
    Return error message
    Display why action unavailable
    ↓
If validation succeeds:
    ActionExecutionService::execute[ActionName]()
        ↓
        Based on effect_type, route to specific handler:
        
        If 'attack_roll':
            → executeStrike()
            → Calculate attack bonus
            → Apply MAP if attacks_this_turn > 0
            → Roll d20 + bonuses vs target AC
            → If hit: prompt for damage roll
            → Increment attacks_this_turn
            
        If 'movement':
            → executeStride() or executeStep()
            → Calculate distance moved
            → Check for difficult terrain
            → Update position
            → Check for opportunity attacks (if Stride)
            
        If 'skill_check':
            → execute[SkillAction]() (Demoralize, Feint, etc.)
            → Roll skill check vs target DC
            → Determine degree of success
            → Apply effects based on result
            
        If 'condition_apply':
            → Apply condition to target
            → INSERT into combat_conditions
            → Calculate duration
            
        If 'utility':
            → Execute specific utility logic
            → Apply effects (Raise Shield, Take Cover, Interact, etc.)
    ↓
Deduct actions_cost from participant.actions_remaining
    UPDATE combat_participants SET actions_remaining -= cost
    ↓
Log action to combat_actions table
    INSERT INTO combat_actions
    ↓
Check for trait-based triggers:
    If has 'manipulate' or 'move' trait:
        Check if enemies have Attack of Opportunity
        Prompt for reactions
    ↓
Return action result to UI
    Update combat state display
    Show action feedback message
    Update participant action count (3 → 2 → 1 → 0)
```

### Action Validation Flow

```
ActionController::validateAction($participant, $action, $target, $context)
    ↓
Check action economy:
    If action.actions_cost > participant.actions_remaining:
        Return error: "Not enough actions remaining"
    ↓
Check condition restrictions:
    Load participant conditions from combat_conditions
    
    If has 'paralyzed' or 'unconscious' or 'petrified':
        Return error: "Cannot act while [condition]"
    
    If has 'stunned X':
        Return error: "Lose actions equal to stunned value"
    
    If has 'slowed X':
        Check if actions_remaining already reduced
        Return error if trying to exceed reduced maximum
    
    If action has 'manipulate' trait AND participant has 'grabbed':
        Return error: "Cannot use manipulate actions while grabbed"
    
    If action has 'concentrate' trait AND participant has condition preventing concentration:
        Return error: "Cannot concentrate while [condition]"
    ↓
Check requirements:
    If action.requirements:
        Parse requirements text
        Check each requirement:
        - "wielding a melee weapon" → check character_inventory for equipped weapon
        - "free hand" → check hands available
        - "within reach" → check distance to target ≤ reach
        If any requirement not met:
            Return error with specific failure reason
    ↓
Check feat requirements:
    If action.feat_required:
        Check if participant has feat in character_feats
        If not:
            Return error: "Requires [feat name] feat"
    ↓
Check skill requirements:
    If action.skill_required:
        Check participant proficiency in skill
        If untrained and action requires training:
            Return error: "Not trained in [skill]"
    ↓
Check range/reach:
    If action.range_feet:
        Calculate distance to target
        If distance > action.range_feet:
            Return error: "Target out of range"
    ↓
Check frequency limitations:
    If action.frequency:
        Check if action already used this turn/round
        Query combat_actions for recent usage
        If exceeded:
            Return error: "Can only use [frequency]"
    ↓
All validations passed:
    Return success: true
```

### Reaction Trigger Flow

```
Action executed that has triggering trait (manipulate, move)
    ↓
Query enemies within reach of acting participant
    ↓
For each enemy:
    Check if has reaction available (reaction_available = true)
    Check if has reaction that matches trigger
    
    Load character_feats for reactions:
        - Attack of Opportunity
        - Shield Block (for incoming attack)
        - Other reaction feats
    ↓
If enemy has matching reaction:
    Prompt GM/player: "Enemy can use [reaction], use it?"
    ↓
If reaction used:
    ActionController::execute() for reaction
    ActionExecutionService::execute[ReactionName]()
    
    Examples:
    - Attack of Opportunity → Strike action with no MAP
    - Shield Block → Reduce damage, apply to shield
    
    UPDATE combat_participants SET reaction_available = FALSE
    INSERT combat_reactions log
    ↓
Continue with original action resolution
```

### Action Macro Execution

```
Player clicks macro button
    ↓
Load action_macros row
    ↓
Parse action_sequence JSON
    ↓
For each action in sequence:
    ActionController::validateAction()
    If invalid:
        Stop macro execution
        Show error
        Return actions already spent
    ↓
    If valid:
        ActionController::execute()
        Wait for resolution (especially if requires targeting)
    ↓
    If any action fails or runs out of actions:
        Stop macro
    ↓
All actions in macro completed
    Return macro execution summary
```

## Functions Required

### ActionValidationService

**Purpose**: Validate action preconditions and requirements

#### `canPerformAction($participant, $action, $context)`
- Check all prerequisites for action
- Parameters: participant object, action object, combat context
- Returns: `{valid: boolean, errors: []}`

#### `hasRequiredWeapon($participant, $weapon_type)`
- Check if appropriate weapon equipped
- Parameters: participant, weapon type requirement
- Returns: boolean

#### `hasRequiredFeat($participant, $feat_id)`
- Check character_feats for required feat
- Parameters: participant, feat ID
- Returns: boolean

#### `isWithinRange($participant_position, $target_position, $range_feet)`
- Calculate distance and compare to range
- Parameters: positions, range limit
- Returns: boolean

#### `isConditionRestricted($participant, $action_traits)`
- Check if conditions prevent action
- Examples: paralyzed prevents all actions, grabbed prevents manipulate
- Parameters: participant conditions, action traits
- Returns: `{restricted: boolean, reason: string}`

#### `getActionEconomyStatus($participant)`
- Get current action/reaction availability
- Parameters: participant
- Returns: `{actions_remaining: int, reaction_available: boolean, restrictions: []}`

### ActionTraitService

**Purpose**: Handle trait-based mechanics

#### `triggersAttackOfOpportunity($action)`
- Check if action has manipulate or move trait (AND doesn't have special exception)
- Parameters: action object
- Returns: boolean

#### `appliesMultipleAttackPenalty($action)`
- Check if action has attack trait
- Parameters: action object
- Returns: boolean

#### `requiresConcentration($action)`
- Check if action has concentrate trait
- Parameters: action object
- Returns: boolean

#### `canBeDisrupted($action, $participant_conditions)`
- Determine if concentrate action can be disrupted by current conditions
- Parameters: action, participant conditions
- Returns: boolean

#### `getActionsWithTrait($trait_name)`
- Query game_actions filtered by trait
- Parameters: trait name
- Returns: array of actions

### ActionEffectService

**Purpose**: Apply action effects and outcomes

#### `applySkillCheckEffect($result, $action, $target)`
- Based on degree of success, apply action effects
- Parameters: check result, action definition, target
- Returns: effect application result

#### `applyConditionFromAction($target, $condition_type, $value, $duration)`
- Apply condition as result of action (Demoralize → frightened, Trip → prone)
- Parameters: target, condition, value, duration
- Returns: condition application result

#### `applyMovementEffect($participant, $new_position, $distance)`
- Update participant position, check difficult terrain
- Parameters: participant, new coordinates, distance moved
- Returns: movement result

#### `applyBuffEffect($participant, $buff_type, $bonus_value, $duration)`
- Apply temporary bonus (Raise Shield → +2 AC)
- Parameters: participant, buff type, value, duration
- Returns: buff application result

#### `resolveReadiedAction($participant, $trigger_observed, $combat_id)`
- Check if readied action's trigger occurred
- If yes, execute action as reaction
- Parameters: participant, trigger event, combat
- Returns: action execution result or null

## Data Requirements Per Function

### Loading Available Actions:
- Load: `game_actions` table (all actions)
- Load: `character_feats` for participant (feat-locked actions)
- Load: `combat_participants` (action economy status)
- Load: `combat_conditions` (restrictions)
- Filter: Actions participant can currently use

### Executing Action:
- Load: `game_actions` row for action
- Load: `action_trait_definitions` for trait effects
- Load: `combat_participants` for attacker and target stats
- Load: `character_inventory` for equipped items (if weapon usage)
- Load: `character_proficiencies` for skill checks
- Update: `combat_participants` (actions_remaining, attacks_this_turn, position)
- Insert: `combat_actions` log
- Insert: `combat_conditions` if applying condition

### Checking Reactions:
- Load: `combat_participants` within reach with reaction_available = true
- Load: `character_feats` WHERE feat_type = 'class' AND has reaction feats
- Load: `game_actions` WHERE action_type = 'reaction'
- Match: trigger_description to action being performed

### Validating Action:
- Load: `game_actions` for requirements and traits
- Load: `character_feats` if feat_required
- Load: `combat_participants` for action economy
- Load: `combat_conditions` for restrictions
- Load: `character_inventory` for equipment requirements

## API Endpoints Summary

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/actions` | List all actions (library) |
| GET | `/actions/{id}` | Get action details |
| GET | `/actions/available/{participant_id}` | Get available actions for participant |
| GET | `/actions/trait/{trait}` | Filter actions by trait |
| GET | `/actions/skill/{skill}` | Get skill-based actions |
| POST | `/actions/execute` | Execute action in combat |
| POST | `/actions/validate` | Validate if action can be performed |
| GET | `/actions/macros/{character_id}` | Get character's action macros |
| POST | `/actions/macros` | Create action macro |
| PUT | `/actions/macros/{id}` | Update action macro |
| DELETE | `/actions/macros/{id}` | Delete action macro |

## Success Criteria

- ✅ All Core Rulebook basic actions defined in game_actions table
- ✅ Action cost enforcement (1/2/3 actions per turn limit)
- ✅ Trait system functional (Attack, Manipulate, Move, Concentrate)
- ✅ Multiple Attack Penalty correctly applied to Attack trait actions
- ✅ Manipulate and Move traits trigger Attack of Opportunity reactions
- ✅ Action validation prevents illegal actions (conditions, requirements, range)
- ✅ Skill-based actions (Demoralize, Feint, Trip, etc.) functional with skill checks
- ✅ Reaction system allows reactions when triggers occur
- ✅ Combat action log records all actions taken
- ✅ Action macros allow players to create custom action sequences
- ✅ UI clearly shows available actions and actions remaining

## UI Components Needed

### Combat Action Bar
- Display actions remaining (3/3, 2/3, 1/3, 0/3)
- Display reaction status (available/used)
- Action buttons filtered to available actions only
- Disabled buttons show why unavailable (hover tooltip)

### Action Buttons
- Icon + name
- Action cost indicator (1/2/3 action symbols)
- Trait badges (Attack, Move, Manipulate, etc.)
- Quick description on hover

### Reaction Prompt
- Modal popup when reaction trigger occurs
- Show triggering action
- Show available reactions
- "Use Reaction" or "Skip" buttons
- Timer (optional, for fast-paced play)

### Action Log
- Chronological list of all actions in combat
- Filterable by participant
- Color-coded by action type
- Expandable for details (rolls, results)

## Future Enhancements

- Custom actions (homebrew content)
- Action templates for common combinations
- AI-suggested optimal actions based on situation
- Action history analysis (most-used actions)
- Action animation integration
- Voice commands for action execution
- Mobile-optimized action interface
- Drag-and-drop action planning
- Action prediction (show likely enemy actions)
- Collaborative action coordination (plan combos with allies)
