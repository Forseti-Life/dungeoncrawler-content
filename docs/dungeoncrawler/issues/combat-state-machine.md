# Combat State Machine

**Part of**: [Issue #4: Combat & Encounter System Design](./issue-4-combat-encounter-system-design.md)  
**Status**: Design Document  
**Last Updated**: 2026-02-12

## Overview

This document defines the state machine for combat encounters in the Pathfinder 2E system. The state machine ensures proper sequencing of combat phases, validates state transitions, and maintains combat integrity throughout the encounter lifecycle.

## Encounter States

### Primary States

```
┌─────────────────────────────────────────────────────────────┐
│                    ENCOUNTER STATES                          │
└─────────────────────────────────────────────────────────────┘

  [SETUP]
     │
     │ rollInitiative()
     ▼
  [ROLLING_INITIATIVE]
     │
     │ sortInitiativeOrder()
     ▼
  [INITIATIVE_SET]
     │
     │ startCombat()
     ▼
  [ACTIVE] ◄──────┐
     │            │
     │ pause()    │ resume()
     ▼            │
  [PAUSED] ───────┘
     │
     │ endCombat()
     ▼
  [CONCLUDED]
     │
     │ archive()
     ▼
  [ARCHIVED]
```

### State Definitions

#### SETUP
**Description**: Initial state when combat encounter is created but not yet started.

**Available Actions**:
- Add/remove participants
- Set encounter metadata (name, difficulty, notes)
- Configure initial positions
- Delete encounter

**Transitions To**:
- `ROLLING_INITIATIVE` via `rollInitiative()`
- `CANCELLED` via `delete()`

**Data Requirements**:
- Campaign ID
- At least 2 participants (1 PC, 1 enemy minimum)

#### ROLLING_INITIATIVE
**Description**: Transient state during initiative roll processing.

**Processing**:
- Roll initiative for each participant (d20 + Perception modifier)
- Apply initiative bonuses/penalties
- Handle tie-breaking rules (NPCs before PCs)

**Transitions To**:
- `INITIATIVE_SET` automatically after rolling completes
- `SETUP` via `cancelRoll()` (if error occurs)

**Duration**: < 2 seconds (automated)

#### INITIATIVE_SET
**Description**: Initiative order established, ready to begin combat.

**Available Actions**:
- Review initiative order
- Make final participant adjustments
- Start combat
- Re-roll initiative (returns to SETUP)

**Transitions To**:
- `ACTIVE` via `startCombat()`
- `SETUP` via `rerollInitiative()`

**Data Requirements**:
- All participants have valid initiative_total
- Initiative order sorted

#### ACTIVE
**Description**: Combat is in progress, participants taking turns.

**Sub-states**: See [Round States](#round-states) and [Turn States](#turn-states)

**Available Actions**:
- Execute combat actions
- Manage turn order
- Apply conditions
- Update HP
- Use reactions
- Pause combat

**Transitions To**:
- `PAUSED` via `pauseCombat()`
- `CONCLUDED` via `endCombat()`

**Exit Conditions**:
- All enemies defeated/fled
- All PCs defeated/fled
- Combat surrendered
- Manual GM conclusion

#### PAUSED
**Description**: Combat temporarily halted, state preserved.

**Available Actions**:
- Review combat log
- Edit participant stats (GM only)
- Add/remove participants (GM only)
- Resume combat

**Transitions To**:
- `ACTIVE` via `resumeCombat()`
- `CONCLUDED` via `endCombat()` (forfeit)

**Use Cases**:
- Break during long combat
- Resolve rules question
- Technical issues
- Add reinforcements

#### CONCLUDED
**Description**: Combat ended, final processing in progress.

**Processing**:
- Calculate XP awards
- Apply loot distribution
- Update character statistics
- Generate combat report
- Log final state

**Transitions To**:
- `ARCHIVED` automatically after processing

**Data Generated**:
- Combat summary
- XP awards per character
- Damage dealt/taken statistics
- Rounds elapsed
- Actions taken count

#### ARCHIVED
**Description**: Combat completed and archived, read-only state.

**Available Actions**:
- View combat log (read-only)
- Generate reports
- Export combat data

**Transitions To**: None (terminal state)

---

## Round States

Substates of `ACTIVE` encounter state.

```
┌─────────────────────────────────────────────────────────────┐
│                      ROUND STATES                            │
└─────────────────────────────────────────────────────────────┘

     ┌────────────────────────┐
     │   ROUND_STARTING       │
     │   - Increment round    │
     │   - Process round FX   │
     │   - Reset turn order   │
     └───────────┬────────────┘
                 │
                 ▼
     ┌────────────────────────┐
     │   ROUND_IN_PROGRESS    │◄─────┐
     │   - Process turns      │      │
     │   - Handle reactions   │      │
     └───────────┬────────────┘      │
                 │                   │
                 │  [More Turns]     │
                 ├───────────────────┘
                 │
                 │  [All Turns Done]
                 ▼
     ┌────────────────────────┐
     │   ROUND_ENDING         │
     │   - Process round end  │
     │   - Check win/lose     │
     └───────────┬────────────┘
                 │
                 │  [Combat Continues]
                 ├──────► ROUND_STARTING
                 │
                 │  [Combat Ends]
                 └──────► CONCLUDED
```

### Round State Transitions

#### ROUND_STARTING
**Entry Actions**:
```python
def enter_round_starting(encounter, round_number):
    # Increment round counter
    encounter.current_round = round_number
    
    # Decrement round-based condition durations
    for participant in encounter.participants:
        for condition in participant.conditions:
            if condition.duration_type == 'rounds':
                condition.duration_remaining -= 1
                if condition.duration_remaining <= 0:
                    remove_condition(participant, condition)
    
    # Apply round-start effects
    process_round_start_effects(encounter)
    
    # Reset turn order to first participant
    encounter.current_turn_index = 0
    
    # Transition to ROUND_IN_PROGRESS
    return transition_to(ROUND_IN_PROGRESS)
```

#### ROUND_IN_PROGRESS
**Conditions**:
- At least one participant has not taken their turn this round
- Combat has not been paused or ended

**Processing**: See [Turn States](#turn-states)

#### ROUND_ENDING
**Entry Actions**:
```python
def enter_round_ending(encounter):
    # Process end-of-round effects
    for participant in encounter.participants:
        # Regeneration, ongoing effects, etc.
        process_round_end_effects(participant)
    
    # Check combat end conditions
    if check_all_enemies_defeated(encounter):
        return transition_to(CONCLUDED, outcome='victory')
    elif check_all_pcs_defeated(encounter):
        return transition_to(CONCLUDED, outcome='defeat')
    elif check_retreat_condition(encounter):
        return transition_to(CONCLUDED, outcome='retreat')
    else:
        # Start next round
        return transition_to(ROUND_STARTING, encounter.current_round + 1)
```

---

## Turn States

Substates of `ROUND_IN_PROGRESS`.

```
┌─────────────────────────────────────────────────────────────┐
│                      TURN STATES                             │
└─────────────────────────────────────────────────────────────┘

     ┌────────────────────────┐
     │   TURN_STARTING        │
     │   - Grant actions      │
     │   - Grant reaction     │
     │   - Reset MAP          │
     │   - Process start FX   │
     └───────────┬────────────┘
                 │
                 ▼
     ┌────────────────────────┐
     │   AWAITING_ACTION      │◄─────┐
     │   - Display options    │      │
     │   - Validate inputs    │      │
     └───────────┬────────────┘      │
                 │                   │
                 │  executeAction()  │
                 ▼                   │
     ┌────────────────────────┐      │
     │   PROCESSING_ACTION    │      │
     │   - Execute action     │      │
     │   - Update state       │      │
     │   - Log action         │      │
     └───────────┬────────────┘      │
                 │                   │
                 │  [Actions Remain] │
                 ├───────────────────┘
                 │
                 │  [No Actions] OR endTurn()
                 ▼
     ┌────────────────────────┐
     │   TURN_ENDING          │
     │   - Persistent damage  │
     │   - Process end FX     │
     │   - Advance turn       │
     └───────────┬────────────┘
                 │
                 │  [More Participants]
                 ├──────► TURN_STARTING (next participant)
                 │
                 │  [Last Participant]
                 └──────► ROUND_ENDING
```

### Turn State Transitions

#### TURN_STARTING
**Entry Actions**:
```python
def enter_turn_starting(participant, encounter):
    # Grant action economy
    participant.actions_remaining = 3
    participant.reaction_available = True
    
    # Reset Multiple Attack Penalty
    participant.attacks_this_turn = 0
    participant.current_map_penalty = 0
    
    # Check for conditions that modify action economy
    if participant.has_condition('stunned'):
        stunned_value = participant.get_condition_value('stunned')
        participant.actions_remaining -= stunned_value
        remove_condition(participant, 'stunned')
    
    if participant.has_condition('slowed'):
        slowed_value = participant.get_condition_value('slowed')
        participant.actions_remaining -= slowed_value
    
    if participant.has_condition('quickened'):
        # Quickened grants extra action with restrictions
        participant.quickened_action_available = True
    
    # Process start-of-turn effects
    if participant.has_condition('dying'):
        roll_recovery_check(participant)
    
    # Decrease valued conditions (frightened, etc.)
    if participant.has_condition('frightened'):
        current_value = participant.get_condition_value('frightened')
        if current_value > 1:
            participant.set_condition_value('frightened', current_value - 1)
        else:
            remove_condition(participant, 'frightened')
    
    # Transition to awaiting action
    return transition_to(AWAITING_ACTION)
```

#### AWAITING_ACTION
**Valid Actions**:
- Single actions (Strike, Stride, Step, etc.)
- Two-action activities (Cast Spell, Ready, etc.)
- Three-action activities (Special abilities)
- Free actions (with valid triggers)
- End turn early

**Validation**:
```python
def validate_action(participant, action, encounter):
    # Check action economy
    if action.cost > participant.actions_remaining:
        return False, "Not enough actions remaining"
    
    # Check action prerequisites
    if action.requires_weapon and not participant.has_weapon():
        return False, "No weapon equipped"
    
    if action.requires_target and not action.has_valid_target():
        return False, "No valid target"
    
    # Check conditions that prevent action
    if participant.has_condition('paralyzed'):
        return False, "Cannot act while paralyzed"
    
    if participant.has_condition('unconscious'):
        return False, "Cannot act while unconscious"
    
    # Action-specific validation
    if action.type == 'cast_spell':
        if not validate_spell_casting(participant, action.spell):
            return False, "Cannot cast this spell"
    
    return True, "Valid action"
```

#### PROCESSING_ACTION
**Processing Steps**:
```python
def process_action(participant, action, encounter):
    # 1. Deduct action cost
    participant.actions_remaining -= action.cost
    
    # 2. Execute action logic
    result = execute_action_handler(action)
    
    # 3. Apply action effects
    if action.has_attack_trait:
        # Apply Multiple Attack Penalty for next attack
        participant.attacks_this_turn += 1
        participant.current_map_penalty = calculate_map(
            participant.attacks_this_turn,
            action.is_agile
        )
    
    # 4. Update combat state
    update_participant_state(participant)
    update_targets(result.affected_targets)
    
    # 5. Log action
    log_combat_action(encounter, participant, action, result)
    
    # 6. Check for reactions triggered
    check_and_process_reactions(encounter, action, result)
    
    # 7. Return to awaiting action or end turn
    if participant.actions_remaining > 0:
        return transition_to(AWAITING_ACTION)
    else:
        return transition_to(TURN_ENDING)
```

#### TURN_ENDING
**Entry Actions**:
```python
def enter_turn_ending(participant, encounter):
    # 1. Apply persistent damage
    if participant.has_condition('persistent_damage'):
        damage_amount = participant.get_condition_value('persistent_damage')
        damage_type = participant.get_condition_metadata('persistent_damage', 'type')
        
        # Apply damage
        apply_damage(participant, damage_amount, damage_type)
        
        # Roll flat check DC 15 to end persistent damage
        flat_check = roll_d20()
        if flat_check >= 15:
            remove_condition(participant, 'persistent_damage')
    
    # 2. Remove "until end of turn" effects
    remove_end_of_turn_effects(participant)
    
    # 3. Decrement turn-based conditions
    for condition in participant.conditions:
        if condition.duration_type == 'turns':
            condition.duration_remaining -= 1
            if condition.duration_remaining <= 0:
                remove_condition(participant, condition)
    
    # 4. Process end-of-turn abilities (e.g., regeneration)
    process_end_of_turn_effects(participant)
    
    # 5. Log turn end
    log_turn_end(encounter, participant)
    
    # 6. Advance to next participant
    encounter.current_turn_index += 1
    
    if encounter.current_turn_index >= len(encounter.participants):
        # All participants have acted
        return transition_to(ROUND_ENDING)
    else:
        # Next participant's turn
        next_participant = encounter.get_current_turn_participant()
        return transition_to(TURN_STARTING, next_participant)
```

---

## Action States

For actions that involve multiple steps (e.g., attack rolls).

```
┌─────────────────────────────────────────────────────────────┐
│                     ACTION STATES                            │
└─────────────────────────────────────────────────────────────┘

  [ACTION_INITIATED]
         │
         ▼
  [VALIDATING]
         │
         ├─[Invalid]──► [ACTION_FAILED]
         │
         ▼ [Valid]
  [EXECUTING]
         │
         ├── For Attack Actions:
         │   ┌─────────────────┐
         │   │ ROLLING_ATTACK  │
         │   └────────┬────────┘
         │            │
         │            ▼
         │   ┌─────────────────┐
         │   │ DETERMINING_HIT │
         │   └────────┬────────┘
         │            │
         │            ├─[Miss]──► [ACTION_COMPLETED]
         │            │
         │            ▼ [Hit]
         │   ┌─────────────────┐
         │   │ ROLLING_DAMAGE  │
         │   └────────┬────────┘
         │            │
         │            ▼
         │   ┌─────────────────┐
         │   │ APPLYING_DAMAGE │
         │   └────────┬────────┘
         │            │
         ▼            ▼
  [ACTION_COMPLETED]
         │
         │
         ▼
  [LOGGED]
```

### Attack Action Flow

```python
def execute_attack_action(attacker, target, weapon, encounter):
    """Complete attack action state flow"""
    
    # State: ACTION_INITIATED
    action = create_action('strike', attacker, target, weapon)
    
    # State: VALIDATING
    is_valid, message = validate_action(attacker, action, encounter)
    if not is_valid:
        return ActionResult(state='ACTION_FAILED', message=message)
    
    # State: EXECUTING > ROLLING_ATTACK
    attack_bonus = calculate_attack_bonus(
        attacker,
        weapon,
        attacker.current_map_penalty,
        get_circumstance_bonuses(attacker, target, encounter)
    )
    
    attack_roll = roll_d20()
    is_nat_20 = (attack_roll == 20)
    is_nat_1 = (attack_roll == 1)
    attack_total = attack_roll + attack_bonus
    
    # State: DETERMINING_HIT
    target_ac = calculate_ac(target)
    degree = determine_degree_of_success(
        attack_total,
        target_ac,
        is_nat_1,
        is_nat_20
    )
    
    if degree in ['failure', 'critical_failure']:
        # Attack missed
        log_action(encounter, action, attack_roll, attack_total, degree, 0)
        return ActionResult(
            state='ACTION_COMPLETED',
            hit=False,
            degree=degree
        )
    
    # State: ROLLING_DAMAGE
    damage_dice = weapon.damage_dice
    ability_modifier = get_ability_modifier(attacker, weapon)
    
    damage_rolls = roll_damage_dice(damage_dice)
    
    if degree == 'critical_success':
        # Critical hit: double damage dice (not modifiers)
        damage_rolls = [roll * 2 for roll in damage_rolls]
    
    base_damage = sum(damage_rolls) + ability_modifier
    
    # State: APPLYING_DAMAGE
    final_damage = apply_resistances_weaknesses(
        base_damage,
        weapon.damage_type,
        target.resistances,
        target.weaknesses
    )
    
    apply_damage(target, final_damage)
    
    # Check for death/dying
    if target.current_hp <= 0:
        apply_dying_condition(target)
    
    # State: ACTION_COMPLETED
    log_action(encounter, action, attack_roll, attack_total, degree, final_damage)
    
    return ActionResult(
        state='LOGGED',
        hit=True,
        degree=degree,
        damage=final_damage
    )
```

---

## Reaction States

Reactions can interrupt the normal turn flow.

```
┌─────────────────────────────────────────────────────────────┐
│                   REACTION STATES                            │
└─────────────────────────────────────────────────────────────┘

  During ANY action:
         │
         │ [Trigger occurs]
         ▼
  ┌─────────────────┐
  │ REACTION_       │
  │ AVAILABLE       │
  └────────┬────────┘
           │
           │ [Player chooses to react]
           ▼
  ┌─────────────────┐
  │ REACTION_       │
  │ EXECUTING       │
  └────────┬────────┘
           │
           ▼
  ┌─────────────────┐
  │ REACTION_       │
  │ COMPLETED       │
  └────────┬────────┘
           │
           │ Mark reaction as used
           ▼
  Resume interrupted action
```

### Reaction Processing

```python
def check_for_reactions(encounter, action, actor):
    """Check if any participants can react to this action"""
    
    # Get all participants with available reactions
    potential_reactors = [
        p for p in encounter.participants
        if p.reaction_available and p.id != actor.id
    ]
    
    for participant in potential_reactors:
        # Check each reaction the participant has
        for reaction in participant.get_available_reactions():
            # Check if trigger conditions met
            if reaction.check_trigger(action, actor, participant):
                # Pause main action flow
                pause_action_processing()
                
                # Prompt for reaction
                wants_to_react = prompt_reaction_choice(
                    participant,
                    reaction,
                    action,
                    actor
                )
                
                if wants_to_react:
                    # Execute reaction
                    reaction_result = execute_reaction(
                        participant,
                        reaction,
                        action,
                        actor
                    )
                    
                    # Mark reaction as used
                    participant.reaction_available = False
                    
                    # Log reaction
                    log_reaction(encounter, participant, reaction, reaction_result)
                    
                    # Modify main action if needed
                    if reaction.modifies_triggering_action:
                        modify_action(action, reaction_result)
                
                # Resume main action flow
                resume_action_processing()
```

---

## State Validation Rules

### Transition Guards

```python
class CombatStateMachine:
    """State machine for combat encounters"""
    
    def can_transition(self, from_state, to_state, encounter):
        """Validate state transition is allowed"""
        
        rules = {
            ('SETUP', 'ROLLING_INITIATIVE'): self._validate_setup_complete,
            ('ROLLING_INITIATIVE', 'INITIATIVE_SET'): self._validate_initiative_rolled,
            ('INITIATIVE_SET', 'ACTIVE'): self._validate_ready_to_start,
            ('ACTIVE', 'PAUSED'): self._always_allow,
            ('PAUSED', 'ACTIVE'): self._always_allow,
            ('ACTIVE', 'CONCLUDED'): self._validate_combat_end_condition,
            ('CONCLUDED', 'ARCHIVED'): self._validate_processing_complete,
        }
        
        validator = rules.get((from_state, to_state))
        if validator is None:
            return False, f"Invalid transition: {from_state} -> {to_state}"
        
        return validator(encounter)
    
    def _validate_setup_complete(self, encounter):
        """Ensure setup is complete before rolling initiative"""
        if len(encounter.participants) < 2:
            return False, "At least 2 participants required"
        
        if not encounter.has_at_least_one_pc():
            return False, "At least one PC required"
        
        if not encounter.has_at_least_one_enemy():
            return False, "At least one enemy required"
        
        return True, "Setup complete"
    
    def _validate_initiative_rolled(self, encounter):
        """Ensure all participants have initiative"""
        for participant in encounter.participants:
            if participant.initiative_total is None:
                return False, f"Participant {participant.name} missing initiative"
        
        return True, "Initiative rolled for all"
    
    def _validate_ready_to_start(self, encounter):
        """Ensure ready to start active combat"""
        if not encounter.initiative_order_sorted:
            return False, "Initiative order not sorted"
        
        return True, "Ready to start"
    
    def _validate_combat_end_condition(self, encounter):
        """Ensure valid reason to end combat"""
        if encounter.check_victory_condition():
            return True, "All enemies defeated"
        
        if encounter.check_defeat_condition():
            return True, "All PCs defeated"
        
        if encounter.check_retreat_condition():
            return True, "Party retreated"
        
        if encounter.gm_forced_end:
            return True, "GM ended combat"
        
        return False, "No valid end condition met"
    
    def _validate_processing_complete(self, encounter):
        """Ensure final processing complete"""
        if not encounter.xp_calculated:
            return False, "XP not yet calculated"
        
        if not encounter.combat_log_finalized:
            return False, "Combat log not finalized"
        
        return True, "Processing complete"
    
    def _always_allow(self, encounter):
        """Always allow this transition"""
        return True, "Allowed"
```

---

## Error Handling

### State Recovery

```python
def handle_state_error(encounter, error):
    """Handle errors during state transitions"""
    
    # Log error
    log_error(encounter, error)
    
    # Determine recovery strategy
    if error.type == 'INVALID_TRANSITION':
        # Revert to previous valid state
        encounter.state = encounter.previous_state
        notify_gm(f"Invalid transition attempted: {error.message}")
    
    elif error.type == 'DATA_CORRUPTION':
        # Attempt to reconstruct state from log
        reconstructed_state = reconstruct_from_log(encounter)
        if reconstructed_state:
            encounter.state = reconstructed_state
            notify_gm("State recovered from log")
        else:
            # Pause combat for manual intervention
            encounter.state = 'PAUSED'
            notify_gm("Combat paused - manual intervention required")
    
    elif error.type == 'VALIDATION_FAILURE':
        # Allow GM to override or fix
        prompt_gm_override(encounter, error)
    
    else:
        # Unknown error - pause for safety
        encounter.state = 'PAUSED'
        notify_gm(f"Unknown error occurred: {error.message}")
```

---

## State Persistence

### Database Schema

```sql
-- Track encounter state transitions
CREATE TABLE encounter_state_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    encounter_id BIGINT UNSIGNED NOT NULL,
    previous_state ENUM(
        'SETUP', 'ROLLING_INITIATIVE', 'INITIATIVE_SET',
        'ACTIVE', 'PAUSED', 'CONCLUDED', 'ARCHIVED'
    ),
    new_state ENUM(
        'SETUP', 'ROLLING_INITIATIVE', 'INITIATIVE_SET',
        'ACTIVE', 'PAUSED', 'CONCLUDED', 'ARCHIVED'
    ) NOT NULL,
    transition_reason VARCHAR(255),
    transition_data JSON,
    transitioned_by BIGINT UNSIGNED, -- user_id
    transitioned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (transitioned_by) REFERENCES users(id),
    INDEX idx_encounter (encounter_id),
    INDEX idx_timestamp (transitioned_at)
);

-- Track turn state within rounds
CREATE TABLE turn_state_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    encounter_id BIGINT UNSIGNED NOT NULL,
    round_number TINYINT UNSIGNED NOT NULL,
    participant_id BIGINT UNSIGNED NOT NULL,
    turn_state ENUM(
        'TURN_STARTING', 'AWAITING_ACTION', 'PROCESSING_ACTION', 'TURN_ENDING'
    ) NOT NULL,
    actions_remaining TINYINT UNSIGNED,
    state_data JSON,
    state_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (encounter_id) REFERENCES combat_encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES combat_participants(id) ON DELETE CASCADE,
    INDEX idx_encounter_round (encounter_id, round_number)
);
```

---

## Performance Considerations

### State Caching

```python
class CombatStateCache:
    """Cache combat state for fast access"""
    
    def __init__(self, redis_client):
        self.redis = redis_client
        self.ttl = 3600  # 1 hour
    
    def get_encounter_state(self, encounter_id):
        """Get cached encounter state"""
        key = f"encounter:{encounter_id}:state"
        cached = self.redis.get(key)
        
        if cached:
            return json.loads(cached)
        
        # Load from database
        state = self.load_from_db(encounter_id)
        
        # Cache it
        self.redis.setex(key, self.ttl, json.dumps(state))
        
        return state
    
    def update_encounter_state(self, encounter_id, new_state):
        """Update cached state"""
        key = f"encounter:{encounter_id}:state"
        
        # Update cache
        self.redis.setex(key, self.ttl, json.dumps(new_state))
        
        # Update database asynchronously
        self.queue_db_update(encounter_id, new_state)
    
    def invalidate_encounter_cache(self, encounter_id):
        """Clear cached state"""
        key = f"encounter:{encounter_id}:state"
        self.redis.delete(key)
```

---

## Testing State Transitions

### Unit Test Example

```python
def test_combat_state_machine():
    """Test state machine transitions"""
    
    # Setup
    encounter = create_test_encounter()
    state_machine = CombatStateMachine()
    
    # Test: SETUP -> ROLLING_INITIATIVE
    assert encounter.state == 'SETUP'
    
    result = state_machine.transition(encounter, 'ROLLING_INITIATIVE')
    assert result.success == True
    assert encounter.state == 'ROLLING_INITIATIVE'
    
    # Test: ROLLING_INITIATIVE -> INITIATIVE_SET
    roll_initiative_for_all(encounter)
    result = state_machine.transition(encounter, 'INITIATIVE_SET')
    assert result.success == True
    assert encounter.state == 'INITIATIVE_SET'
    
    # Test: INITIATIVE_SET -> ACTIVE
    result = state_machine.transition(encounter, 'ACTIVE')
    assert result.success == True
    assert encounter.state == 'ACTIVE'
    
    # Test: Cannot transition ACTIVE -> SETUP (invalid)
    result = state_machine.transition(encounter, 'SETUP')
    assert result.success == False
    assert encounter.state == 'ACTIVE'  # State unchanged
```

---

## Summary

The combat state machine ensures:

1. **Valid Transitions**: Only allowed state changes can occur
2. **Data Integrity**: State is always consistent and valid
3. **Error Recovery**: Graceful handling of errors with recovery
4. **Auditability**: All state changes logged for debugging
5. **Performance**: Efficient state management with caching
6. **Testability**: Clear state definitions enable thorough testing

This state machine forms the foundation of the combat system, ensuring reliable and predictable combat flow that adheres to Pathfinder 2E rules.

---

**Related Documents**:
- [Combat Database Schema](./combat-database-schema.md)
- [Combat Engine Service](./combat-engine-service.md)
- [Combat API Endpoints](./combat-api-endpoints.md)
