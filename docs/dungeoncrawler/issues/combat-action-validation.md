# Combat Action Validation Rules

**Part of**: [Issue #4: Combat & Encounter System Design](./issue-4-combat-encounter-system-design.md)  
**Status**: Design Document  
**Last Updated**: 2026-02-12

## Overview

This document defines the complete validation rules for all combat actions in Pathfinder 2E. These rules ensure that actions are legal, prerequisites are met, and PF2e rules are enforced before action execution.

## Validation Framework

### Validation Layers

```
┌─────────────────────────────────────────────┐
│         Action Validation Flow               │
└─────────────────────────────────────────────┘

Input: Action Request
       │
       ▼
┌──────────────────────┐
│  1. STATE            │  ← Is combat active? Is it player's turn?
│     VALIDATION       │
└──────────┬───────────┘
           │ ✓
           ▼
┌──────────────────────┐
│  2. ACTION ECONOMY   │  ← Enough actions remaining?
│     VALIDATION       │
└──────────┬───────────┘
           │ ✓
           ▼
┌──────────────────────┐
│  3. CONDITION        │  ← Can act? Not paralyzed/stunned?
│     VALIDATION       │
└──────────┬───────────┘
           │ ✓
           ▼
┌──────────────────────┐
│  4. PREREQUISITE     │  ← Weapon equipped? Target valid?
│     VALIDATION       │
└──────────┬───────────┘
           │ ✓
           ▼
┌──────────────────────┐
│  5. RESOURCE         │  ← Spell slots? Ability uses?
│     VALIDATION       │
└──────────┬───────────┘
           │ ✓
           ▼
┌──────────────────────┐
│  6. TARGET           │  ← Target in range? Line of sight?
│     VALIDATION       │
└──────────┬───────────┘
           │ ✓
           ▼
   Action Executes
```

---

## 1. State Validation Rules

### Combat State Rules

**Rule 1.1: Combat Must Be Active**
```
Combat Status = 'active' OR 'paused' (GM actions only)
Error: "Combat is not currently active"
```

**Rule 1.2: Must Be Actor's Turn**
```
current_turn_participant_id = actor_participant_id
OR action_type = 'reaction' (can occur on any turn)
OR action_type = 'free_action' (with valid trigger)
Error: "It is not your turn"
```

**Rule 1.3: Participant Must Be Active**
```
participant.is_active = TRUE
participant.is_defeated = FALSE
Error: "You cannot act (defeated/inactive)"
```

**Rule 1.4: Round and Turn State**
```
IF starting turn:
  turn_state = 'TURN_STARTING' → transitions to 'AWAITING_ACTION'
IF during turn:
  turn_state = 'AWAITING_ACTION' (can take actions)
IF action processing:
  turn_state = 'PROCESSING_ACTION' (wait for completion)
Error: "Turn is not in valid state for actions"
```

---

## 2. Action Economy Validation

### Action Cost Rules

**Rule 2.1: Sufficient Actions Remaining**
```
FOR single actions (cost = 1):
  actions_remaining >= 1
  
FOR two-action activities (cost = 2):
  actions_remaining >= 2
  
FOR three-action activities (cost = 3):
  actions_remaining = 3 (must be at start of turn)
  
Error: "Not enough actions remaining (need X, have Y)"
```

**Rule 2.2: Multi-Action Activities Cannot Be Split**
```
IF action.cost > 1:
  Must complete entire activity on same turn
  Cannot partially complete then end turn
Error: "Multi-action activities cannot be interrupted"
```

**Rule 2.3: Reaction Availability**
```
IF action_type = 'reaction':
  participant.reaction_available = TRUE
  Valid trigger condition must be met
Error: "Reaction already used this round"
```

**Rule 2.4: Free Action Triggers**
```
IF action_type = 'free_action':
  IF has trigger requirement:
    Trigger condition must be met
  ELSE:
    Can use anytime during own turn
Error: "Free action trigger not met"
```

### Modified Action Economy

**Rule 2.5: Slowed Condition**
```
IF has_condition('slowed', X):
  effective_actions = base_actions - X
  Example: Slowed 1 → 2 actions instead of 3
Error: "You are slowed and have fewer actions"
```

**Rule 2.6: Stunned Condition**
```
IF has_condition('stunned', X):
  Lose X actions at start of turn
  stunned condition is then removed
  actions_remaining = 3 - X
Error: "You are stunned and lose actions"
```

**Rule 2.7: Quickened Condition**
```
IF has_condition('quickened'):
  Gain 1 extra action per turn
  Usually with restrictions (e.g., "Stride or Strike only")
  Check restriction before allowing action
Error: "Quickened action has restrictions"
```

---

## 3. Condition Restriction Rules

### Universal Restrictions

**Rule 3.1: Cannot Act If Unconscious**
```
IF has_condition('unconscious'):
  Cannot take any actions (except recovery checks)
  Cannot take reactions
  Cannot use free actions
Error: "You are unconscious and cannot act"
```

**Rule 3.2: Cannot Act If Paralyzed**
```
IF has_condition('paralyzed'):
  Cannot take actions
  Cannot take reactions
  Automatically flat-footed
  Auto-fail Reflex saves
Error: "You are paralyzed and cannot act"
```

**Rule 3.3: Cannot Act If Petrified**
```
IF has_condition('petrified'):
  Cannot take any actions
  Immune to damage but can be broken
Error: "You are petrified and cannot act"
```

**Rule 3.4: Dying Recovery Only**
```
IF has_condition('dying'):
  Can only make recovery checks
  Cannot take other actions
  Are unconscious
Error: "You are dying and must make recovery checks"
```

### Movement Restrictions

**Rule 3.5: Cannot Move If Immobilized**
```
IF has_condition('immobilized'):
  Cannot use actions with 'move' trait
  Can still act otherwise (Strike, Cast Spell, etc.)
  Exceptions: Step (5 feet), teleportation
Error: "You are immobilized and cannot move"
```

**Rule 3.6: Cannot Move If Grabbed**
```
IF has_condition('grabbed'):
  Cannot move unless Escape first
  Are flat-footed
  Can still act with other actions
Error: "You are grabbed. Use Escape action first"
```

**Rule 3.7: Cannot Move If Restrained**
```
IF has_condition('restrained'):
  Cannot move or use actions with 'move' trait
  Flat-footed, -2 to attack rolls
  -2 to Reflex saves
Error: "You are restrained and cannot move"
```

**Rule 3.8: Prone Movement**
```
IF has_condition('prone'):
  Stride actions become Crawl (5 feet per action)
  Must use Stand action (1 action) to stand up
  -2 to attack rolls while prone
Error: "You are prone. Stand up first or crawl"
```

### Action Type Restrictions

**Rule 3.9: Cannot Use Manipulate Actions While Grabbed**
```
IF has_condition('grabbed'):
  IF action has 'manipulate' trait:
    Must Escape first
Error: "Cannot use manipulate actions while grabbed"
```

**Rule 3.10: Concentrate Actions While Confused**
```
IF has_condition('confused'):
  IF action has 'concentrate' trait:
    Must succeed DC 5 flat check
Error: "Confused: succeed DC 5 flat check to concentrate"
```

**Rule 3.11: Cannot Use Visual Actions While Blinded**
```
IF has_condition('blinded'):
  IF action requires sight:
    Cannot use action
  Seek action auto-fails for visual
Error: "You are blinded and cannot see"
```

**Rule 3.12: Cannot Use Auditory Actions While Deafened**
```
IF has_condition('deafened'):
  IF action has 'auditory' trait:
    Cannot use action
Error: "You are deafened and cannot hear"
```

---

## 4. Action Prerequisite Rules

### Strike Action Prerequisites

**Rule 4.1: Must Have Weapon or Unarmed Attack**
```
IF action = 'strike':
  Has weapon equipped (main hand, off hand, or two-handed)
  OR has unarmed attack capability
Error: "No weapon equipped or unarmed attack available"
```

**Rule 4.2: Weapon Must Be Drawn**
```
IF using weapon:
  Weapon must be in hands (not sheathed)
  Use Interact action to draw first
Error: "Weapon not drawn. Use Interact to draw weapon"
```

**Rule 4.3: Ranged Weapon Ammunition**
```
IF weapon is ranged:
  IF weapon requires reload:
    Must have loaded ammunition
    Must use Reload action between shots
Error: "Weapon not loaded. Use Reload action"
```

**Rule 4.4: Two-Handed Weapon Requirement**
```
IF weapon has 'two-hand' trait:
  Must have both hands free
Error: "Two-handed weapon requires both hands"
```

### Cast Spell Prerequisites

**Rule 4.5: Must Have Spell Slots**
```
IF action = 'cast_spell':
  IF prepared caster:
    Must have spell prepared at chosen level
  IF spontaneous caster:
    Must have spell known
    Must have spell slot at chosen level
  IF innate spell:
    Must have uses remaining
Error: "No spell slots available at this level"
```

**Rule 4.6: Must Have Spell Components**
```
IF spell requires components:
  Verbal: Not silenced
  Somatic: At least one hand free
  Material: Has component pouch or focuses
Error: "Cannot provide required spell components"
```

**Rule 4.7: Cannot Cast While Silenced**
```
IF spell has 'verbal' component:
  NOT has_condition('silence')
Error: "Cannot speak to cast spell (silenced)"
```

**Rule 4.8: Concentration Check**
```
IF already concentrating on another spell:
  Must Dismiss or let expire
  OR spell must not require concentration
Error: "Already concentrating on another spell"
```

### Skill Action Prerequisites

**Rule 4.9: Must Be Trained In Skill**
```
IF action requires trained proficiency:
  skill_proficiency >= 'trained'
  Example: Recall Knowledge (varies), Treat Wounds (trained Medicine)
Error: "Not trained in required skill"
```

**Rule 4.10: Tool Requirement**
```
IF skill action requires tools:
  Must have tools in inventory
  Example: Thieves' tools for Pick Lock
Error: "Required tools not available"
```

### Combat Maneuver Prerequisites

**Rule 4.11: Grapple Requirements**
```
IF action = 'grapple':
  Target within reach (usually 5 feet)
  At least one hand free
  Target size <= your size + 1 category
Error: "Cannot grapple: [reason]"
```

**Rule 4.12: Disarm Requirements**
```
IF action = 'disarm':
  Target wielding weapon or object
  You must have weapon or be unarmed
  Target within reach
Error: "Cannot disarm target"
```

**Rule 4.13: Trip Requirements**
```
IF action = 'trip':
  Target not more than 1 size larger
  Target is not flying
  You have weapon with trip trait OR unarmed
Error: "Cannot trip target"
```

### Shield Actions

**Rule 4.14: Shield Must Be Equipped**
```
IF action IN ['raise_shield', 'shield_block']:
  Shield equipped in hand or arm
  Shield not broken
Error: "No shield equipped or shield is broken"
```

**Rule 4.15: Shield Must Be Raised for Shield Block**
```
IF reaction = 'shield_block':
  Shield raised (used Raise Shield on previous turn)
Error: "Shield not raised. Use Raise Shield action first"
```

---

## 5. Resource Validation Rules

### Spell Slots

**Rule 5.1: Track Spell Slot Usage**
```
IF casting spell at level X:
  spell_slots[X].used < spell_slots[X].total
  Deduct slot after successful cast
Error: "No spell slots remaining at level X"
```

**Rule 5.2: Focus Points**
```
IF casting focus spell:
  focus_points >= 1
  Max focus points = 3
  Regain 1 per 10-minute rest
Error: "No focus points remaining"
```

**Rule 5.3: Innate Spell Uses**
```
IF using innate spell:
  uses_remaining > 0
  Recharge varies by source
Error: "Innate spell uses exhausted"
```

### Class Resources

**Rule 5.4: Rage Rounds (Barbarian)**
```
IF using Rage:
  rage_rounds_remaining > 0
  Duration: typically 3 rounds
Error: "No rage rounds remaining"
```

**Rule 5.5: Ki Points (Monk)**
```
IF using Ki ability:
  ki_points >= ability_cost
Error: "Insufficient Ki points"
```

**Rule 5.6: Channel Energy (Cleric)**
```
IF using Channel Energy:
  channel_energy_uses > 0
Error: "No channel energy uses remaining"
```

### Item Resources

**Rule 5.7: Consumable Items**
```
IF using consumable:
  item_quantity > 0
  Deduct after use
Error: "No items of this type remaining"
```

**Rule 5.8: Charged Items**
```
IF using charged item:
  item_charges >= charges_required
Error: "Item has insufficient charges"
```

**Rule 5.9: Daily Item Uses**
```
IF item has daily use limit:
  item_uses_today < max_daily_uses
  Resets at daily preparation
Error: "Item daily uses exhausted"
```

---

## 6. Target Validation Rules

### Target Existence and State

**Rule 6.1: Target Must Exist**
```
IF action requires target:
  target_id IS NOT NULL
  target is participant in encounter
Error: "No valid target selected"
```

**Rule 6.2: Target Must Be Active**
```
IF targeting participant:
  target.is_active = TRUE
  target.is_defeated = FALSE
Error: "Target is no longer in combat"
```

**Rule 6.3: Cannot Target Self (Unless Allowed)**
```
IF action.cannot_target_self:
  target_id != actor_id
  Exceptions: healing spells, buffs, etc.
Error: "Cannot target yourself with this action"
```

**Rule 6.4: Must Target Enemy/Ally As Required**
```
IF action.requires_enemy_target:
  target.team != actor.team
  
IF action.requires_ally_target:
  target.team = actor.team
  
Error: "Invalid target type for this action"
```

### Range Validation

**Rule 6.5: Target Within Reach (Melee)**
```
IF melee attack OR melee spell:
  distance_to_target <= actor.reach
  Standard reach = 5 feet
  Some weapons/creatures have extended reach
Error: "Target not within reach"
```

**Rule 6.6: Target Within Range (Ranged)**
```
IF ranged attack OR ranged spell:
  distance_to_target <= weapon.range OR spell.range
  Beyond first range increment: penalty applies
Error: "Target out of range"
```

**Rule 6.7: Range Increment Penalties**
```
FOR ranged attacks:
  FOR each range increment beyond first:
    Apply -2 penalty per increment
  Beyond max range (usually 6 increments): cannot attack
Error: "Target beyond maximum range"
```

**Rule 6.8: Touch Spell Reach**
```
IF spell has range 'touch':
  Must be adjacent (5 feet)
  OR use Reach Spell metamagic
Error: "Must be adjacent to target"
```

### Line of Sight and Line of Effect

**Rule 6.9: Must Have Line of Sight**
```
IF action requires sight:
  Unblocked line from actor to target
  Not blocked by solid walls
  Target not in complete darkness (if no darkvision)
Error: "No line of sight to target"
```

**Rule 6.10: Must Have Line of Effect**
```
FOR all targeted effects:
  Unblocked line from origin to target
  Cannot shoot through walls
  Can shoot over low obstacles
Error: "No line of effect to target"
```

**Rule 6.11: Cover Applies But Doesn't Block**
```
IF target has cover:
  Apply cover bonus to AC (+2 standard, +4 greater)
  Does not prevent targeting
Note: Cover bonuses applied automatically
```

### Area Effect Validation

**Rule 6.12: Area Must Fit On Map**
```
IF spell/effect has area:
  Area must fit within combat space
  Example: 20-foot burst within map bounds
Error: "Area does not fit in combat space"
```

**Rule 6.13: Area Targets**
```
FOR area effects:
  All creatures in area are potential targets
  Apply saves individually
  Allies may be affected (unless spell excludes)
Note: No specific validation error, just calculate affected targets
```

---

## 7. Special Action Rules

### Multiple Attack Penalty

**Rule 7.1: MAP Applies to Actions with Attack Trait**
```
IF action has 'attack' trait:
  Apply MAP based on attacks_this_turn
  First attack: -0
  Second attack: -5 (or -4 agile)
  Third+ attack: -10 (or -8 agile)
Note: Applied as penalty, not validation error
```

**Rule 7.2: MAP Resets Each Turn**
```
AT start_of_turn:
  attacks_this_turn = 0
  current_map_penalty = 0
```

### Ready Action Rules

**Rule 7.3: Ready Action Requirements**
```
IF action = 'ready':
  Cost 2 actions
  Must specify:
    - Action to ready (1 action only)
    - Trigger condition
  Can be used as reaction when trigger occurs
Error: "Ready action requires 2 actions and valid trigger"
```

**Rule 7.4: Readied Action Execution**
```
IF executing readied action:
  Trigger condition must be met
  Use as reaction
  Only 1-action actions can be readied
Error: "Trigger not met or action invalid"
```

### Delay Action Rules

**Rule 7.5: Delay Turn**
```
IF action = 'delay':
  Free action at any point during turn
  Remove from initiative order
  Can rejoin at any later initiative
  New initiative becomes permanent
Error: None (always allowed)
```

**Rule 7.6: Resume from Delay**
```
IF resuming from delay:
  Choose initiative value lower than current
  Cannot interrupt another creature's turn
Error: "Cannot rejoin at invalid initiative"
```

### Aid Action Rules

**Rule 7.7: Aid Prerequisites**
```
IF action = 'aid':
  Must be within 30 feet of ally
  Must prepare as reaction (when ally acts)
  Roll appropriate skill/save
  On success: +1 circumstance bonus to ally
Error: "Cannot aid from current position"
```

---

## 8. Environmental and Situational Rules

### Terrain Effects

**Rule 8.1: Difficult Terrain Movement Cost**
```
IF moving through difficult terrain:
  Each 5 feet costs 10 feet of movement
  May require Acrobatics or Athletics check
Note: Applied as increased cost, not error
```

**Rule 8.2: Greater Difficult Terrain**
```
IF in greater difficult terrain:
  Each 5 feet costs 15 feet of movement
  Usually requires successful check to move
```

**Rule 8.3: Cannot Move Through Solid Obstacles**
```
IF path blocked by wall/solid object:
  Cannot move through
  Must go around
Error: "Path blocked by obstacle"
```

### Lighting and Visibility

**Rule 8.4: Concealment from Darkness**
```
IF target in darkness:
  IF actor has no darkvision:
    Target is concealed (20% miss chance)
    OR hidden (must Seek to locate)
Note: Applied as concealment, not blocked
```

**Rule 8.5: Hidden and Undetected Targets**
```
IF target is hidden:
  Attacker must Seek to locate
  IF located: concealed (flat check DC 5 to avoid miss)
  
IF target is undetected:
  Cannot target until located
Error: "Cannot target undetected creature"
```

### Flanking

**Rule 8.6: Flanking Bonus**
```
IF attacker and ally flank target:
  Both on opposite sides of target
  Both threaten target (within reach)
  Grant +2 circumstance bonus to melee attacks
Note: Bonus applied automatically, not a rule check
```

---

## 9. Item and Equipment Rules

### Wielding Items

**Rule 9.1: Hands Available**
```
FOR actions requiring hands:
  IF requires 1 hand: 1 hand free
  IF requires 2 hands: 2 hands free
  IF dual-wielding: separate one-handed weapons
Error: "Not enough hands available"
```

**Rule 9.2: Drawing/Stowing Items**
```
IF drawing or stowing item:
  Interact action (1 action)
  Can draw as part of Strike (if have Quick Draw feat)
Error: "Must use Interact action to draw/stow"
```

**Rule 9.3: Switching Weapons**
```
IF switching weapons:
  Release old weapon (free action)
  Draw new weapon (Interact action)
  OR drop old weapon (free) + draw (Interact)
```

### Armor and Encumbrance

**Rule 9.4: Armor Check Penalty**
```
IF wearing armor with check penalty:
  Apply penalty to Strength/Dex checks
  Apply to skills with armor check penalty
Note: Automatic penalty, not validation error
```

**Rule 9.5: Encumbrance**
```
Total Bulk Carried vs Carry Capacity:
  IF bulk <= 5 + Str mod: Unencumbered
  IF bulk <= 10 + Str mod: Encumbered (-10 ft speed, -5 AC/saves)
  IF bulk > 10 + Str mod: Cannot carry more
Error: "Cannot carry more items (over capacity)"
```

---

## 10. Edge Cases and Special Situations

### Death and Dying

**Rule 10.1: Actions While Dying**
```
IF dying:
  Can only make recovery checks
  Cannot take normal actions
  Recovery check at start of turn
Error: "You are dying and must make recovery check"
```

**Rule 10.2: Unconscious from HP**
```
IF current_hp = 0:
  Become unconscious
  Gain dying 1 (or wounded value)
  Fall prone
  Drop all held items
Error: "You fall unconscious"
```

**Rule 10.3: Stabilized but Unconscious**
```
IF dying condition removed but still 0 HP:
  Remain unconscious
  Gain wounded condition
  Require healing to wake
```

### Unusual Actions

**Rule 10.4: Dropping Items**
```
IF action = 'drop':
  Free action
  Always available (even if no actions left)
  Items fall in your square
Note: Always succeeds
```

**Rule 10.5: Speaking**
```
Speaking is free action:
  Can speak on any turn (yours or others)
  Reasonable amount only
  Cannot convey complex info mid-combat
Note: GM discretion
```

**Rule 10.6: Holding Breath**
```
IF underwater or in hazard:
  Can hold breath (Constitution × 1 minute)
  After that: suffocation rules apply
```

---

## Validation Implementation Example

### Pseudocode for Validation

```python
class ActionValidator:
    """Validates combat actions against all rules"""
    
    def validate(action, actor, encounter):
        """Master validation function"""
        
        # Layer 1: State Validation
        result = validate_state(encounter, actor)
        if not result.valid:
            return result
        
        # Layer 2: Action Economy
        result = validate_action_economy(actor, action)
        if not result.valid:
            return result
        
        # Layer 3: Conditions
        result = validate_conditions(actor, action)
        if not result.valid:
            return result
        
        # Layer 4: Prerequisites
        result = validate_prerequisites(actor, action)
        if not result.valid:
            return result
        
        # Layer 5: Resources
        result = validate_resources(actor, action)
        if not result.valid:
            return result
        
        # Layer 6: Targets
        if action.has_target:
            result = validate_targets(actor, action.targets, encounter)
            if not result.valid:
                return result
        
        # All validations passed
        return ValidationResult(valid=True)
    
    def validate_state(encounter, actor):
        """Check combat and turn state"""
        if encounter.status != 'active':
            return ValidationResult(False, "Combat not active")
        
        if not actor.is_active:
            return ValidationResult(False, "You are not active")
        
        if encounter.current_turn != actor.id:
            if action.type not in ['reaction', 'free_action']:
                return ValidationResult(False, "Not your turn")
        
        return ValidationResult(True)
    
    def validate_action_economy(actor, action):
        """Check action cost"""
        if action.cost > actor.actions_remaining:
            return ValidationResult(
                False, 
                f"Need {action.cost} actions, have {actor.actions_remaining}"
            )
        
        if action.type == 'reaction' and not actor.reaction_available:
            return ValidationResult(False, "Reaction already used")
        
        return ValidationResult(True)
    
    def validate_conditions(actor, action):
        """Check condition restrictions"""
        # Universal restrictions
        if actor.has_condition('unconscious'):
            return ValidationResult(False, "Cannot act while unconscious")
        
        if actor.has_condition('paralyzed'):
            return ValidationResult(False, "Cannot act while paralyzed")
        
        # Movement restrictions
        if action.has_trait('move'):
            if actor.has_condition('immobilized'):
                return ValidationResult(False, "Cannot move while immobilized")
            
            if actor.has_condition('grabbed'):
                return ValidationResult(False, "Cannot move while grabbed")
        
        # Manipulate restrictions
        if action.has_trait('manipulate'):
            if actor.has_condition('grabbed'):
                return ValidationResult(False, "Cannot use manipulate actions while grabbed")
        
        return ValidationResult(True)
    
    def validate_prerequisites(actor, action):
        """Check action-specific prerequisites"""
        if action.type == 'strike':
            if not actor.has_weapon() and not actor.has_unarmed_attack():
                return ValidationResult(False, "No weapon or unarmed attack")
        
        if action.type == 'cast_spell':
            if not actor.has_spell_slot(action.spell_level):
                return ValidationResult(False, "No spell slots at this level")
            
            if action.spell.has_verbal and actor.has_condition('silenced'):
                return ValidationResult(False, "Cannot speak while silenced")
        
        return ValidationResult(True)
    
    def validate_targets(actor, targets, encounter):
        """Check target validity"""
        for target in targets:
            # Target exists and active
            if not target.is_active:
                return ValidationResult(False, f"Target {target.name} not active")
            
            # Range check
            distance = calculate_distance(actor.position, target.position)
            if action.is_melee and distance > actor.reach:
                return ValidationResult(False, "Target not in reach")
            
            if action.is_ranged and distance > action.range:
                return ValidationResult(False, "Target out of range")
            
            # Line of sight
            if not has_line_of_sight(actor, target, encounter.map):
                return ValidationResult(False, "No line of sight to target")
        
        return ValidationResult(True)
```

---

## Summary

This validation framework ensures:

- ✅ **Complete Rule Compliance**: All PF2e rules enforced
- ✅ **Clear Error Messages**: Users understand why action failed
- ✅ **Layered Validation**: Efficient early-exit on first failure
- ✅ **Comprehensive Coverage**: All action types validated
- ✅ **Edge Case Handling**: Unusual situations covered
- ✅ **Maintainable**: Rules organized by category
- ✅ **Extensible**: Easy to add new actions and rules

The validation system prevents illegal actions before execution, maintaining combat integrity and ensuring a fair, rule-compliant experience.

**Related Documents**:
- [Combat Engine Service](./combat-engine-service.md)
- [Combat State Machine](./combat-state-machine.md)
- [Combat API Endpoints](./combat-api-endpoints.md)
