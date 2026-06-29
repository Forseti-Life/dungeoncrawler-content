# Combat Engine Service

**Part of**: [Issue #4: Combat & Encounter System Design](./issue-4-combat-encounter-system-design.md)  
**Status**: Design Document  
**Last Updated**: 2026-02-12

## Overview

The Combat Engine Service is the core business logic layer for managing combat encounters. It orchestrates combat state, processes actions, enforces PF2e rules, and coordinates between the database layer and API controllers.

## Service Architecture

```
┌────────────────────────────────────────────────────────┐
│           Combat Engine Service Layer                  │
├────────────────────────────────────────────────────────┤
│                                                        │
│  ┌──────────────────┐    ┌──────────────────┐        │
│  │  CombatEngine    │    │  ActionProcessor │        │
│  │  (Orchestrator)  │◄──►│  (Actions)       │        │
│  └─────────┬────────┘    └──────────────────┘        │
│            │                                           │
│  ┌─────────▼────────┐    ┌──────────────────┐        │
│  │  StateManager    │    │  RulesEngine     │        │
│  │  (State Machine) │◄──►│  (Validation)    │        │
│  └──────────────────┘    └──────────────────┘        │
│                                                        │
│  ┌──────────────────┐    ┌──────────────────┐        │
│  │  Calculator      │    │  ConditionMgr    │        │
│  │  (Math/Formulas) │    │  (Conditions)    │        │
│  └──────────────────┘    └──────────────────┘        │
│                                                        │
└────────────────────────────────────────────────────────┘
           │                          │
           ▼                          ▼
    ┌────────────┐            ┌────────────┐
    │ Repository │            │ Event      │
    │ Layer      │            │ Dispatcher │
    └────────────┘            └────────────┘
```

## Core Services

---

## 1. CombatEngine (Main Orchestrator)

**Purpose**: Main orchestrator for combat operations, coordinates all combat services.

### Encounter Management

#### `createEncounter($campaign_id, $encounter_name, $participants[], $settings)`
- Create new combat encounter in SETUP state
- Parameters: campaign ID, encounter name, participant array, settings object
- Returns: encounter ID

#### `startEncounter($encounter_id)`
- Transition encounter from SETUP to ROLLING_INITIATIVE
- Roll initiative for all participants
- Sort by initiative order
- Transition to INITIATIVE_SET
- Parameters: encounter ID
- Returns: sorted initiative order

#### `beginCombat($encounter_id)`
- Transition from INITIATIVE_SET to ACTIVE
- Start round 1
- Set current turn to first participant
- Emit combat started event
- Parameters: encounter ID
- Returns: combat state

#### `pauseEncounter($encounter_id, $reason)`
- Transition from ACTIVE to PAUSED
- Preserve all combat state
- Parameters: encounter ID, pause reason
- Returns: success boolean

#### `resumeEncounter($encounter_id)`
- Transition from PAUSED to ACTIVE
- Continue from saved state
- Parameters: encounter ID
- Returns: combat state

#### `endEncounter($encounter_id, $outcome, $victory_condition)`
- Transition to CONCLUDED
- Calculate XP rewards
- Finalize combat log
- Update character records
- Parameters: encounter ID, outcome enum, victory description
- Returns: encounter summary with XP awards

### Round Management

#### `startRound($encounter_id, $round_number)`
- Increment round counter
- Decrement round-based condition durations
- Remove expired conditions
- Reset turn order to start
- Grant action economy to all participants
- Parameters: encounter ID, round number
- Returns: round state

#### `endRound($encounter_id)`
- Process end-of-round effects
- Check win/lose conditions
- Determine if combat continues
- If continues: startRound(next_round)
- If ended: endEncounter()
- Parameters: encounter ID
- Returns: next action (new round or end)

### Turn Management

#### `startTurn($encounter_id, $participant_id)`
- Grant 3 actions + 1 reaction
- Reset MAP to 0
- Process start-of-turn effects
- Handle dying recovery check if applicable
- Decrement valued conditions (frightened, etc.)
- Check for stunned/slowed/quickened conditions
- Parameters: encounter ID, participant ID
- Returns: turn state

#### `endTurn($encounter_id, $participant_id)`
- Apply persistent damage (with flat check DC 15)
- Process end-of-turn effects
- Decrement turn-based conditions
- Remove expired effects
- Advance to next participant
- If last participant: endRound()
- Parameters: encounter ID, participant ID
- Returns: next turn info

#### `delayTurn($encounter_id, $participant_id)`
- Mark participant as delaying
- Store original initiative
- Remove from current turn order
- Participant can rejoin at chosen initiative
- Parameters: encounter ID, participant ID
- Returns: success boolean

#### `resumeFromDelay($encounter_id, $participant_id, $new_initiative)`
- Reinsert participant at new initiative
- Lock in new position permanently
- Parameters: encounter ID, participant ID, new initiative value
- Returns: updated initiative order

---

## 2. ActionProcessor

**Purpose**: Execute and validate combat actions.

### Action Execution

#### `executeAction($encounter_id, $participant_id, $action_type, $action_data)`
- Validate action legality (economy, prerequisites, conditions)
- Deduct action cost from available actions
- Execute action handler for specific type
- Apply action effects to targets
- Update MAP if attack action
- Log action to combat_actions table
- Check for triggered reactions
- Parameters: encounter ID, participant ID, action type, action data object
- Returns: action result

#### `executeStrike($attacker_id, $target_id, $weapon, $encounter_id)`
- Calculate attack bonus with current MAP
- Roll d20 + bonuses
- Compare to target AC
- Determine degree of success
- If hit: roll damage
- Apply resistances/weaknesses
- Update target HP
- Check for dying condition
- Increment attacks_this_turn
- Update MAP
- Log strike action
- Parameters: attacker ID, target ID, weapon object, encounter ID
- Returns: strike result

#### `executeStride($participant_id, $distance, $path, $encounter_id)`
- Validate movement (not paralyzed/restrained)
- Check for difficult terrain
- Calculate actual distance moved
- Check for reactions (Attack of Opportunity)
- Update participant position
- Deduct 1 action
- Log movement
- Parameters: participant ID, distance, path array, encounter ID
- Returns: movement result

#### `executeCastSpell($caster_id, $spell_id, $spell_level, $targets[], $encounter_id)`
- Validate spell can be cast (slots available, not silenced)
- Deduct spell slot
- Execute spell effects by type (attack roll, save, automatic)
- Apply conditions/damage/buffs to targets
- Deduct actions (usually 2)
- Log spell cast
- Parameters: caster ID, spell ID, spell level, targets array, encounter ID
- Returns: spell result

#### `executeSkillAction($participant_id, $skill_action, $target_id, $encounter_id)`
- Validate skill action legality
- Roll skill check + modifiers
- Determine degree of success
- Apply skill action effects (Demoralize → frightened, Trip → prone, etc.)
- Deduct actions
- Apply MAP if attack trait
- Log skill action
- Parameters: participant ID, skill action type, target ID, encounter ID
- Returns: skill action result

### Ready and Delay

#### `readyAction($participant_id, $action_data, $trigger_description, $encounter_id)`
- Spend 2 actions to ready
- Store readied action with trigger
- Mark participant as having readied action
- When trigger occurs: execute as reaction
- Parameters: participant ID, action data, trigger text, encounter ID
- Returns: success boolean

#### `triggerReadiedAction($participant_id, $trigger_data, $encounter_id)`
- Check if trigger conditions match readied action
- Execute readied action as reaction
- Mark reaction as used
- Remove readied action state
- Parameters: participant ID, trigger data, encounter ID
- Returns: action result

---

## 3. Calculator Service

**Purpose**: All combat-related calculations and formulas.

### Initiative

#### `calculateInitiative($perception_modifier, $bonuses[])`
- Roll: d20 + perception_modifier + sum(bonuses)
- Parameters: perception modifier, bonus array
- Returns: initiative total

#### `sortInitiativeOrder($participants[])`
- Sort by initiative_total DESC
- Secondary sort by initiative_tiebreaker DESC
- Tie-breaker rule: NPCs before PCs (or random 0-99)
- Parameters: participant array with initiative
- Returns: sorted participant list

### Attack Calculations

#### `calculateAttackBonus($proficiency, $ability_mod, $item_bonus, $map, $bonuses[], $penalties[])`
- Formula: proficiency + ability_mod + item_bonus - map + sum(bonuses) - sum(penalties)
- Parameters: all attack components
- Returns: total attack bonus

#### `calculateMAP($attacks_this_turn, $is_agile_weapon)`
- First attack: 0
- Second attack: -5 (normal) or -4 (agile)
- Third+ attack: -10 (normal) or -8 (agile)
- Parameters: attack count, weapon agility
- Returns: MAP penalty value

#### `determineDegreeOfSuccess($roll, $dc, $is_natural_1, $is_natural_20)`
- Natural 20: improve result by 1 degree
- Natural 1: worsen result by 1 degree
- Critical Success: roll >= DC + 10
- Success: roll >= DC
- Failure: roll < DC
- Critical Failure: roll <= DC - 10
- Parameters: d20 result, target DC, natural 1/20 flags
- Returns: degree enum (critical_success, success, failure, critical_failure)

### Damage Calculations

#### `rollDamage($damage_dice, $ability_modifier, $bonuses[])`
- Roll all damage dice
- Add ability modifier
- Add bonus damage
- Parameters: dice notation, ability mod, bonus array
- Returns: total damage

#### `applyCriticalDamage($base_damage_rolls, $static_modifiers)`
- Double all dice rolls (NOT modifiers)
- Add original static modifiers once
- Parameters: array of die rolls, static modifiers
- Returns: critical damage total

#### `applyResistancesWeaknesses($damage, $damage_type, $resistances, $weaknesses)`
- Apply resistance: max(0, damage - resistance_value)
- Apply weakness: damage + weakness_value
- Order: resistances first, then weaknesses
- Parameters: base damage, damage type, resistance/weakness objects
- Returns: final damage

### AC and Saves

#### `calculateAC($base_ac, $dex_mod, $armor_bonus, $shield_raised, $conditions[])`
- Formula: 10 + dex_mod + armor_bonus + shield_bonus - condition_penalties
- Flat-footed: -2 AC
- Prone vs melee: -2 AC
- Prone vs ranged: +2 AC
- Parameters: all AC components
- Returns: total AC

#### `calculateSave($save_type, $proficiency, $ability_mod, $bonuses[], $penalties[])`
- Formula: proficiency + ability_mod + sum(bonuses) - sum(penalties)
- Parameters: save type (Fort/Ref/Will), proficiency, ability mod, modifiers
- Returns: save total

### Distance and Movement

#### `calculateDistance($from_x, $from_y, $to_x, $to_y, $use_grid)`
- If grid: count diagonal/orthogonal squares (PF2e rules: 1-1-2 pattern)
- If not grid: straight line distance
- Parameters: start coordinates, end coordinates, grid flag
- Returns: distance in feet

#### `calculateMovementCost($distance, $speed, $difficult_terrain, $terrain_penalties[])`
- Normal: distance uses feet of movement 1:1
- Difficult terrain: each 5 feet costs 10 feet
- Greater difficult: each 5 feet costs 15 feet
- Parameters: distance, speed, terrain flags, penalties
- Returns: movement cost in feet

### Flanking and Positioning

#### `checkFlanking($attacker_pos, $ally_positions[], $target_pos, $reach)`
- Attacker and ally must be on opposite sides of target
- Both must threaten target (within reach)
- Use 180-degree rule for opposite sides
- Parameters: attacker position, ally positions, target position, reach distance
- Returns: boolean (is flanking)

#### `calculateCover($attacker_pos, $target_pos, $obstacles[])`
- Check line of sight for obstacles
- Standard cover: +2 AC, +2 Reflex
- Greater cover: +4 AC, +4 Reflex
- Parameters: attacker position, target position, obstacle array
- Returns: cover bonus amount

---

## 4. ConditionManager

**Purpose**: Manage combat conditions and their effects.

### Condition Application

#### `applyCondition($participant_id, $condition_type, $value, $duration, $source, $encounter_id)`
- Validate condition can be applied
- Check for immunities
- Check stacking rules (same type conditions)
- Insert into combat_conditions table
- Apply immediate stat effects
- Log condition application
- Parameters: participant ID, condition type, value, duration info, source description, encounter ID
- Returns: condition ID

#### `removeCondition($participant_id, $condition_id, $encounter_id)`
- Remove condition effects from participant
- Mark removed_at timestamp
- Restore affected stats
- Log condition removal
- Parameters: participant ID, condition ID, encounter ID
- Returns: success boolean

#### `updateConditionDuration($condition_id, $duration_change, $encounter_id)`
- Decrement or increment duration_remaining
- If duration reaches 0: removeCondition()
- Parameters: condition ID, duration change amount, encounter ID
- Returns: new duration

### Condition Effects

#### `applyConditionEffects($participant, $condition_type, $value)`
- Apply stat modifications based on condition:
  - **Blinded**: Flat-footed, auto-fail Perception checks, -4 to Perception
  - **Clumsy X**: -X to Dex-based checks, Reflex saves, and AC
  - **Enfeebled X**: -X to Str-based checks and melee damage
  - **Frightened X**: -X to all checks and DCs
  - **Flat-footed**: -2 AC
  - **Grabbed**: Can't move, flat-footed
  - **Prone**: -2 AC vs melee, +2 AC vs ranged, -2 to attack rolls
  - **Slowed X**: Reduce actions by X
  - **Stunned X**: Lose X actions
  - **Quickened**: Gain 1 extra action with restrictions
  - **Dying X**: Unconscious, make recovery checks
  - **Unconscious**: Flat-footed, -4 AC, can't act
- Parameters: participant object, condition type, value
- Returns: modified participant stats

#### `getConditionModifiers($participant_id, $stat_type, $encounter_id)`
- Aggregate all active condition modifiers for a stat
- Return net modifier with proper stacking rules
- Same type bonuses: take highest
- Penalties: stack
- Parameters: participant ID, stat type, encounter ID
- Returns: total modifier

### Special Conditions

#### `processPersistentDamage($participant_id, $encounter_id)`
- Apply persistent damage amount
- Roll flat check DC 15 to end
- If DC 15: remove persistent damage condition
- If fail: damage persists
- Parameters: participant ID, encounter ID
- Returns: damage dealt and check result

#### `processDyingCondition($participant_id, $constitution_modifier, $encounter_id)`
- Roll recovery check: d20 + con_mod vs DC (10 + dying_value)
- Critical Success: reduce dying by 2
- Success: reduce dying by 1
- Failure: increase dying by 1
- Critical Failure: increase dying by 2
- If dying reaches 4: participant dies
- If dying reduced to 0: gain 1 HP, gain wounded condition
- Parameters: participant ID, con modifier, encounter ID
- Returns: dying condition result

#### `processWoundedCondition($participant_id, $encounter_id)`
- When healed from dying: gain wounded 1 (or increase by 1)
- When dying again: dying value starts at wounded value
- Maximum wounded 3
- Parameters: participant ID, encounter ID
- Returns: wounded value

---

## 5. RulesEngine

**Purpose**: Validate actions against PF2e rules.

### Action Validation

#### `validateAction($participant_id, $action, $encounter_id)`
- Check action economy (enough actions remaining)
- Check action prerequisites (weapon equipped, target valid, etc.)
- Check condition restrictions (not paralyzed/stunned/unconscious)
- Check special requirements (spell slots, ability uses, etc.)
- Parameters: participant ID, action object, encounter ID
- Returns: {is_valid: boolean, reason: string}

#### `validateActionEconomy($participant, $action_cost)`
- Check actions_remaining >= action_cost
- Check for slowed condition (reduces max actions)
- Check for quickened condition (grants extra action)
- Parameters: participant object, action cost
- Returns: {is_valid: boolean, actions_after: number}

#### `validateActionPrerequisites($participant, $action, $target)`
- Check action-specific requirements:
  - Strike: weapon equipped or unarmed
  - Cast Spell: spell slots available, not silenced
  - Stride: not immobilized/paralyzed/grabbed
  - Grapple: target within reach, free hand
  - Shield Block: shield raised, shield not broken
- Parameters: participant, action, target
- Returns: {is_valid: boolean, reason: string}

### Condition Restrictions

#### `checkConditionRestrictions($participant, $action_type)`
- Cannot act if: paralyzed, unconscious, stunned (all actions), petrified
- Cannot move if: immobilized, grabbed, paralyzed, restrained
- Cannot use manipulate actions if: grabbed (without Escape)
- Cannot use concentrate actions if: confused (without check)
- Parameters: participant, action type
- Returns: {can_act: boolean, restriction: string}

#### `checkImmunities($participant, $effect_type, $effect_source)`
- Check participant immunities against effect
- Immunity types: condition, damage type, spell school, trait
- Parameters: participant, effect type, effect source
- Returns: {is_immune: boolean}

### Attack Validation

#### `validateAttack($attacker, $target, $weapon, $encounter_id)`
- Check attacker can make attacks (not unconscious/stunned all)
- Check weapon is equipped and usable
- Check target is valid (not on same team, in range)
- Check line of sight (if ranged)
- Check cover penalties
- Parameters: attacker, target, weapon, encounter ID
- Returns: {is_valid: boolean, modifiers: object}

#### `validateSpellCast($caster, $spell, $spell_level, $targets[], $encounter_id)`
- Check spell slots available at level
- Check spellcasting not prevented (silence, etc.)
- Check targets valid for spell
- Check range to targets
- Check concentration (if sustaining other spell)
- Parameters: caster, spell, level, targets, encounter ID
- Returns: {is_valid: boolean, reason: string}

---

## 6. StateManager

**Purpose**: Manage combat state transitions and persistence.

### State Transitions

#### `transitionState($encounter_id, $new_state, $reason)`
- Validate transition is allowed (see state machine)
- Update encounter status
- Log state transition to history
- Emit state change event
- Parameters: encounter ID, new state, reason
- Returns: {success: boolean, new_state: string}

#### `getCurrentState($encounter_id)`
- Load encounter from cache or database
- Return current state and sub-states
- Parameters: encounter ID
- Returns: combat state object

#### `saveStateSnapshot($encounter_id, $round, $turn_sequence)`
- Capture complete combat state
- Store in state_snapshots table
- Enable point-in-time recovery
- Parameters: encounter ID, round number, turn sequence
- Returns: snapshot ID

#### `restoreStateSnapshot($encounter_id, $snapshot_id)`
- Load snapshot data
- Restore encounter to snapshot state
- Log restoration action
- Parameters: encounter ID, snapshot ID
- Returns: restored state

### State Queries

#### `getInitiativeOrder($encounter_id)`
- Load all active participants
- Sort by initiative (with tiebreaker)
- Include HP, conditions, and status
- Parameters: encounter ID
- Returns: sorted participant array

#### `getCurrentTurnParticipant($encounter_id)`
- Get participant whose turn it currently is
- Load full stats, conditions, and available actions
- Parameters: encounter ID
- Returns: participant object

#### `getParticipantState($participant_id, $encounter_id)`
- Load participant stats
- Load active conditions
- Calculate derived stats (AC, saves, etc.)
- Load equipped items
- Parameters: participant ID, encounter ID
- Returns: complete participant state

---

## 7. ReactionHandler

**Purpose**: Handle reaction triggers and execution.

### Reaction Processing

#### `checkForReactions($encounter_id, $action, $actor)`
- Get all participants with available reactions
- Check each participant's reactions for triggers
- Filter by trigger conditions match
- Prompt eligible participants for reaction choice
- Parameters: encounter ID, action object, actor
- Returns: triggered reaction array

#### `executeReaction($participant_id, $reaction_type, $trigger_action, $encounter_id)`
- Validate reaction available
- Execute reaction logic by type:
  - Attack of Opportunity: Strike with no MAP
  - Shield Block: Reduce damage by hardness
  - Nimble Dodge: +2 AC against triggering attack
  - Aid: Grant +1 bonus to ally's check
  - Readied Action: Execute prepared action
- Mark reaction as used
- Log reaction
- Return result that may modify trigger action
- Parameters: participant ID, reaction type, trigger action, encounter ID
- Returns: reaction result

#### `processAttackOfOpportunity($participant_id, $triggering_action, $target_id, $encounter_id)`
- Validate target within reach
- Make Strike with no MAP penalty
- Use same action resolution as Strike
- May interrupt triggering action
- Parameters: participant ID, triggering action, target ID, encounter ID
- Returns: attack result

#### `processShieldBlock($participant_id, $incoming_damage, $damage_type, $encounter_id)`
- Get shield stats (hardness, HP)
- Reduce damage by shield hardness
- Apply remaining damage to shield
- Check if shield breaks (HP = 0)
- Apply remaining damage to participant if shield breaks
- Parameters: participant ID, damage amount, damage type, encounter ID
- Returns: {damage_blocked: number, shield_broke: boolean}

---

## 8. HPManager

**Purpose**: Manage hit point changes and dying/wounded conditions.

### HP Changes

#### `applyDamage($participant_id, $damage, $damage_type, $source, $encounter_id)`
- Apply to temp HP first
- Calculate remaining damage after temp HP
- Apply resistances/weaknesses
- Reduce current_hp
- Check for death/dying (HP <= 0)
- If HP = 0: unconscious
- If HP < 0: dying 1 (or wounded value if wounded)
- If HP <= -max_hp: instant death (massive damage)
- Log damage in combat_damage_log
- Parameters: participant ID, damage amount, type, source description, encounter ID
- Returns: {final_damage: number, new_hp: number, new_status: string}

#### `applyHealing($participant_id, $healing, $source, $encounter_id)`
- Cannot heal if dead
- Increase current_hp
- Cap at max_hp
- If was dying: remove dying, add wounded
- Log healing
- Parameters: participant ID, healing amount, source, encounter ID
- Returns: {healing_applied: number, new_hp: number}

#### `applyTemporaryHP($participant_id, $temp_hp, $source, $encounter_id)`
- Temp HP doesn't stack (take higher value)
- Replaces existing temp HP if new value higher
- Parameters: participant ID, temp HP amount, source, encounter ID
- Returns: new temp HP value

### Death and Dying

#### `checkDeathCondition($participant_id, $encounter_id)`
- Check if HP <= -max_hp: instant death
- Check if dying value >= 4: death
- If dead: mark as defeated, remove from combat
- Parameters: participant ID, encounter ID
- Returns: {is_dead: boolean, death_reason: string}

#### `applyDyingCondition($participant_id, $dying_value, $encounter_id)`
- Set unconscious condition
- Set dying condition with value
- Remove actions and reactions
- Mark as prone
- If has wounded: dying_value += wounded_value
- Parameters: participant ID, dying value, encounter ID
- Returns: success boolean

#### `stabilizeCharacter($participant_id, $encounter_id)`
- Remove dying condition
- Gain wounded 1 (or increase by 1)
- Remain unconscious
- Set HP to 1
- Parameters: participant ID, encounter ID
- Returns: success boolean

---

## Service Integration Example

### Complete Action Flow

```python
# Pseudocode showing how services work together

def handle_strike_action(encounter_id, attacker_id, target_id, weapon_id):
    """Complete flow for a Strike action"""
    
    # 1. Load combat state
    encounter = StateManager.getCurrentState(encounter_id)
    attacker = StateManager.getParticipantState(attacker_id, encounter_id)
    target = StateManager.getParticipantState(target_id, encounter_id)
    weapon = get_weapon(weapon_id)
    
    # 2. Validate action
    validation = RulesEngine.validateAttack(
        attacker, target, weapon, encounter_id
    )
    if not validation.is_valid:
        return ActionResult(success=False, reason=validation.reason)
    
    # 3. Calculate attack
    attack_bonus = Calculator.calculateAttackBonus(
        attacker.weapon_proficiency,
        attacker.get_ability_modifier(weapon.ability),
        weapon.item_bonus,
        attacker.current_map_penalty,
        validation.modifiers.bonuses,
        validation.modifiers.penalties
    )
    
    # 4. Roll attack
    attack_roll = roll_d20()
    attack_total = attack_roll + attack_bonus
    target_ac = Calculator.calculateAC(
        target.base_ac,
        target.dex_modifier,
        target.armor_bonus,
        target.shield_raised,
        target.conditions
    )
    
    # 5. Determine hit
    degree = Calculator.determineDegreeOfSuccess(
        attack_total,
        target_ac,
        attack_roll == 1,
        attack_roll == 20
    )
    
    # 6. Check for reactions (Attack of Opportunity, etc.)
    reactions = ReactionHandler.checkForReactions(
        encounter_id, 
        action={'type': 'strike', 'actor': attacker_id},
        attacker
    )
    
    # 7. If hit, roll damage
    if degree in ['success', 'critical_success']:
        base_damage = Calculator.rollDamage(
            weapon.damage_dice,
            attacker.get_ability_modifier(weapon.ability),
            []
        )
        
        if degree == 'critical_success':
            damage = Calculator.applyCriticalDamage(base_damage, 0)
        else:
            damage = base_damage
        
        # 8. Apply resistances/weaknesses
        final_damage = Calculator.applyResistancesWeaknesses(
            damage,
            weapon.damage_type,
            target.resistances,
            target.weaknesses
        )
        
        # 9. Apply damage
        result = HPManager.applyDamage(
            target_id,
            final_damage,
            weapon.damage_type,
            f"{attacker.name}'s Strike",
            encounter_id
        )
        
        # 10. Check for death
        if result.new_hp <= 0:
            HPManager.checkDeathCondition(target_id, encounter_id)
    
    # 11. Update attacker state
    attacker.attacks_this_turn += 1
    attacker.current_map_penalty = Calculator.calculateMAP(
        attacker.attacks_this_turn,
        weapon.has_agile_trait
    )
    
    # 12. Deduct action
    attacker.actions_remaining -= 1
    
    # 13. Log action
    ActionProcessor.logAction(
        encounter_id,
        attacker_id,
        'strike',
        target_id,
        attack_roll,
        attack_total,
        degree,
        final_damage if hit else 0
    )
    
    # 14. Emit events
    EventDispatcher.emit('action_taken', action_result)
    
    return ActionResult(
        success=True,
        degree=degree,
        damage=final_damage if hit else 0,
        target_state=result
    )
```

---

## Service Communication Patterns

### Event-Driven Architecture

```python
# Services communicate via events

class EventDispatcher:
    """Central event dispatcher for combat events"""
    
    events = {
        'combat_started': [],
        'round_started': [],
        'turn_started': [],
        'action_taken': [],
        'damage_dealt': [],
        'condition_applied': [],
        'reaction_triggered': [],
        'participant_defeated': [],
        'combat_ended': []
    }
    
    def emit(event_name, data):
        """Emit event to all subscribers"""
        for handler in events[event_name]:
            handler(data)
    
    def subscribe(event_name, handler):
        """Subscribe to event"""
        events[event_name].append(handler)
```

### Service Dependencies

```
CombatEngine
  └─> StateManager (state transitions)
  └─> ActionProcessor (action execution)
      └─> RulesEngine (validation)
      └─> Calculator (math)
      └─> ConditionManager (conditions)
      └─> HPManager (HP changes)
      └─> ReactionHandler (reactions)
```

---

## Error Handling

### Service-Level Errors

```python
class CombatServiceError(Exception):
    """Base exception for combat service errors"""
    pass

class InvalidActionError(CombatServiceError):
    """Action cannot be performed"""
    pass

class InvalidStateError(CombatServiceError):
    """Invalid state transition attempted"""
    pass

class ValidationError(CombatServiceError):
    """Validation failed"""
    pass
```

### Error Recovery

```python
def safe_execute_action(encounter_id, participant_id, action):
    """Execute action with error handling"""
    try:
        # Save state before action
        snapshot = StateManager.saveStateSnapshot(encounter_id)
        
        # Execute action
        result = ActionProcessor.executeAction(
            encounter_id,
            participant_id,
            action
        )
        
        return result
        
    except ValidationError as e:
        # Validation error - inform user
        return ActionResult(success=False, error=str(e))
        
    except CombatServiceError as e:
        # Service error - restore state
        StateManager.restoreStateSnapshot(encounter_id, snapshot)
        log_error(e)
        return ActionResult(success=False, error="Action failed")
        
    except Exception as e:
        # Unknown error - pause combat
        StateManager.transitionState(encounter_id, 'PAUSED', 'Error occurred')
        notify_gm(f"Combat paused due to error: {e}")
        raise
```

---

## Performance Optimizations

### Caching Strategy

```python
class CombatCache:
    """Cache for frequently accessed combat data"""
    
    def get_participant_stats(participant_id, encounter_id):
        """Get cached participant stats"""
        cache_key = f"participant:{participant_id}:stats"
        cached = redis.get(cache_key)
        
        if cached:
            return json.loads(cached)
        
        # Load from database
        stats = load_participant_stats(participant_id, encounter_id)
        
        # Cache for 30 seconds
        redis.setex(cache_key, 30, json.dumps(stats))
        
        return stats
    
    def invalidate_participant(participant_id):
        """Invalidate participant cache"""
        cache_key = f"participant:{participant_id}:stats"
        redis.delete(cache_key)
```

### Batch Operations

```python
def process_round_start_bulk(encounter_id):
    """Process round start for all participants efficiently"""
    
    # Load all participants in single query
    participants = load_all_participants(encounter_id)
    
    # Batch update conditions
    condition_updates = []
    for participant in participants:
        for condition in participant.conditions:
            if condition.duration_type == 'rounds':
                condition.duration_remaining -= 1
                condition_updates.append(condition)
    
    # Single bulk update
    bulk_update_conditions(condition_updates)
    
    # Batch emit events
    EventDispatcher.emit_bulk('round_started', participants)
```

---

## Testing Considerations

### Unit Test Examples

```python
def test_calculate_attack_bonus():
    """Test attack bonus calculation"""
    bonus = Calculator.calculateAttackBonus(
        proficiency=10,  # level 5, trained
        ability_mod=4,   # +4 Strength
        item_bonus=1,    # +1 weapon
        map=-5,          # second attack
        bonuses=[2],     # bless spell
        penalties=[1]    # some penalty
    )
    assert bonus == 11  # 10 + 4 + 1 - 5 + 2 - 1

def test_apply_damage_to_dying():
    """Test damage when already dying"""
    participant = create_test_participant(current_hp=0, dying=2)
    
    result = HPManager.applyDamage(
        participant.id,
        damage=10,
        damage_type='slashing',
        source='test',
        encounter_id=1
    )
    
    # Should increase dying by 1
    assert result.new_status == 'dying_3'
    
def test_map_progression():
    """Test Multiple Attack Penalty progression"""
    assert Calculator.calculateMAP(0, False) == 0
    assert Calculator.calculateMAP(1, False) == -5
    assert Calculator.calculateMAP(2, False) == -10
    assert Calculator.calculateMAP(1, True) == -4  # agile
    assert Calculator.calculateMAP(2, True) == -8  # agile
```

---

## Summary

The Combat Engine Service layer provides:

- ✅ Complete orchestration of combat operations
- ✅ Rule-compliant action processing
- ✅ Accurate PF2e calculations
- ✅ Robust state management
- ✅ Comprehensive condition handling
- ✅ Event-driven architecture
- ✅ Error handling and recovery
- ✅ Performance optimizations

This service layer encapsulates all combat business logic, making it:
- **Testable**: Clear separation of concerns
- **Maintainable**: Well-organized service classes
- **Extensible**: Easy to add new actions and abilities
- **Performant**: Caching and batch operations
- **Reliable**: Error handling and state recovery

**Related Documents**:
- [Combat State Machine](./combat-state-machine.md)
- [Combat Database Schema](./combat-database-schema.md)
- [Action Validation Rules](./combat-action-validation.md)
- [Combat API Endpoints](./combat-api-endpoints.md)
