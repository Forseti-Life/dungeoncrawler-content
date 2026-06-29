# Pathfinder 2E: Action System

## Overview

Pathfinder 2E uses a flexible three-action economy system where you get 3 actions and 1 reaction per turn. This system allows for dynamic tactical choices and creative problem-solving.

## Action Types

### Single Actions [one-action]

The most common type of action. You can use up to 3 per turn in any order.

**Common Single Actions**:
- **Strike**: Make a melee or ranged attack
- **Stride**: Move up to your Speed
- **Step**: Move 5 feet without triggering reactions
- **Interact**: Manipulate an object, open a door, draw a weapon
- **Seek**: Search for hidden creatures or objects
- **Raise a Shield**: Gain AC bonus from shield
- **Take Cover**: Gain cover bonus
- **Cast a Spell**: Some spells require only 1 action
- **Demoralize**: Intimidate a foe (Intimidation skill)
- **Feint**: Mislead opponent (Deception skill)

**Representation**: [one-action] symbol

### Activities (Multi-Action Actions)

Activities require 2 or 3 actions and must be performed together on the same turn.

#### Two-Action Activities [two-actions]

**Common Two-Action Activities**:
- **Cast a Spell**: Most spells require 2 actions
- **Ready**: Prepare a triggered action
- **Long Jump**: Jump a long distance
- **High Jump**: Jump vertically
- **Administer First Aid**: Treat wounds (Medicine skill)
- **Hide**: Attempt to become hidden (Stealth skill)
- **Sneak**: Move while hidden (Stealth skill)

**Representation**: [two-actions] symbol

#### Three-Action Activities [three-actions]

Uses your entire turn's action allotment.

**Common Three-Action Activities**:
- **Cast a Spell**: Some powerful spells require 3 actions
- **Sudden Charge**: Stride twice, then Strike (Barbarian/Fighter feat)
- **Many class-specific abilities**

**Representation**: [three-actions] symbol

**Important**: You cannot split an activity across turns. If you can't complete all required actions, you can't use the activity.

### Reactions [reaction]

Special actions you can use when it's NOT your turn.

**Key Features**:
- Can use **1 reaction** per round
- Requires a specific **trigger**
- Only usable when trigger occurs
- Regain your reaction at start of your turn
- Unused reactions from previous turn are lost

**Common Reactions**:
- **Attack of Opportunity**: Strike creature that moves or acts carelessly within your reach
- **Shield Block**: Use shield to reduce damage from attack
- **Aid**: Grant +1 bonus to ally's check
- **Retributive Strike**: Champion ability to protect ally
- **Nimble Dodge**: Ranger ability to gain +2 AC against attack

**Representation**: [reaction] symbol

**Trigger Example**: "Trigger: A creature within your reach uses a manipulate action or move action..."

### Free Actions [free-action]

Actions that don't cost any of your 3 actions or your reaction.

**Key Features**:
- Don't count toward your 3-action limit
- Some have triggers (like reactions)
- Without trigger, use like a single action
- Can only use 1 free action per trigger
- GM can limit excessive use

**Common Free Actions**:
- **Drop**: Let go of item you're holding
- **Delay**: Move later in initiative
- **Release**: Let go of something you're holding/grabbing
- **Drop Prone**: Fall prone deliberately

**Representation**: [free-action] symbol

## Action Economy

### Actions Per Turn

On your turn, you have:
- **3 actions** to spend
- **1 reaction** (can use on any turn if triggered)
- **Free actions** as appropriate

### Mixing Action Types

You can combine actions flexibly:

**Example Turn 1**:
- [one-action] Stride
- [one-action] Stride
- [one-action] Strike

**Example Turn 2**:
- [two-actions] Cast a Spell
- [one-action] Stride

**Example Turn 3**:
- [three-actions] Sudden Charge (if you have the feat)

**Example Turn 4**:
- [one-action] Strike
- [one-action] Raise Shield
- [one-action] Demoralize

### Ending Your Turn Early

You can choose to end your turn with unused actions:
- To avoid Multiple Attack Penalty
- When you've accomplished your goal
- To save time
- You lose any unspent actions

## Multiple Attack Penalty (MAP)

The main restriction on actions with the **Attack trait**.

### How MAP Works

| Attack Number | Normal Weapons | Agile Weapons |
|---------------|----------------|---------------|
| 1st attack | No penalty | No penalty |
| 2nd attack | -5 penalty | -4 penalty |
| 3rd+ attack | -10 penalty | -8 penalty |

**Important Rules**:
- **Applies per turn**: Resets at start of your next turn
- **Attack trait**: Applies to ANY action with the Attack trait
- **Agile weapons**: Have reduced MAP (-4/-8 instead of -5/-10)
- **Applies even on miss**: Penalty applies whether you hit or miss
- **Cumulative**: Each attack increases the penalty

### Attack Trait Actions

Actions with the Attack trait include:
- Strike
- Grapple
- Shove
- Trip
- Disarm
- Many combat maneuvers

### Managing MAP

**Strategic Options**:
1. **Most accurate first**: Use your most important attack when you have no penalty
2. **Two-attack turns**: Sometimes 2 attacks + utility action beats 3 attacks
3. **Non-attack actions**: Intersperse non-attack actions (don't reduce MAP)
4. **Agile weapons**: Rogues and Dexterity builds benefit from agile weapons
5. **Action abilities**: Use 2-action+ abilities that count as single attack

**Example: Smart Action Use**
```
Turn without considering MAP:
- Strike (-0): d20+10 vs AC 18
- Strike (-5): d20+5 vs AC 18
- Strike (-10): d20+0 vs AC 18 (only 10% chance to hit)

Better turn:
- Strike (-0): d20+10 vs AC 18 (still 65% chance)
- Raise Shield (no penalty)
- Demoralize (no penalty, helps party)
```

## Situational Actions

### Movement and Position

**Stride** [one-action]
- Move up to your Speed
- Can move through allies' spaces
- Cannot move through enemies' spaces (unless specific ability)
- Triggers Attack of Opportunity if you leave threatened square

**Step** [one-action]
- Move 5 feet
- Does NOT trigger reactions
- Cannot Step into difficult terrain
- Good for repositioning safely

**Leap** [one-action]
- Jump vertically or horizontally
- Long Jump (horizontal): Requires 2 actions
- High Jump (vertical): Requires 2 actions

**Crawl** [one-action]
- Move 5 feet while prone
- Difficult terrain penalties still apply

### Interaction

**Interact** [one-action]
- Manipulate an object
- Common uses:
  - Draw or sheathe weapon
  - Open or close door
  - Pick up item
  - Pull lever
  - Pick up dropped shield

**Manipulate Trait**: Many Interact actions have the manipulate trait, which can trigger reactions like Attack of Opportunity.

### Preparation Actions

**Ready** [two-actions]
- Prepare an action to use later with specific trigger
- Choose action and specify trigger
- When trigger occurs, can use reaction to perform action
- If trigger doesn't occur before your next turn, the prepared action is lost

**Example**: 
```
Ranger Readies an action:
"When the goblin comes around the corner [trigger],
I'll make a Strike with my bow [action]"
```

**Delay** [free-action]
- Move to later position in initiative order
- Wait for better moment to act
- Can't interrupt another creature's turn
- Once you act, new initiative position becomes permanent

## Special Action Mechanics

### Concentrate Trait

Actions with concentrate trait:
- Can be disrupted by damage or environmental effects
- Usually mental activities
- Common on spellcasting and skill actions

### Manipulate Trait

Actions with manipulate trait:
- Involve hand movements
- Trigger Attack of Opportunity
- Common on Interact, combat maneuvers, item use

### Move Trait

Actions with move trait:
- Involve movement
- Trigger Attack of Opportunity (usually)
- Common on Stride, Step, Leap, etc.

## Basic Action List

### Combat Actions

| Action | Actions | Traits | Summary |
|--------|---------|--------|---------|
| Strike | [one-action] | Attack | Attack with weapon or unarmed |
| Raise Shield | [one-action] | — | Gain shield's AC bonus |
| Take Cover | [one-action] | — | Benefit from cover |
| Ready | [two-actions] | Concentrate | Prepare action with trigger |
| Aid | [reaction] | — | Help ally (+1 bonus) |
| Attack of Opportunity | [reaction] | — | Strike when foe in reach uses move/manipulate |
| Shield Block | [reaction] | — | Shield absorbs damage |

### Movement Actions

| Action | Actions | Traits | Summary |
|--------|---------|--------|---------|
| Stride | [one-action] | Move | Move up to Speed |
| Step | [one-action] | Move | Move 5 feet, no reactions |
| Leap | [one-action] | Move | Jump (horizontal or vertical) |
| Crawl | [one-action] | Move | Move 5 feet while prone |
| Stand | [one-action] | Move | Stand up from prone |
| Drop Prone | [free-action] | Move | Fall prone |

### Skill Actions

| Action | Actions | Traits | Summary |
|--------|---------|--------|---------|
| Demoralize | [one-action] | Auditory, Emotion, Mental | Frighten foe |
| Feint | [one-action] | Mental | Make foe flat-footed |
| Trip | [one-action] | Attack | Knock foe prone |
| Grapple | [one-action] | Attack | Grab and hold foe |
| Shove | [one-action] | Attack | Push foe away |
| Disarm | [one-action] | Attack | Remove foe's weapon |
| Hide | [one-action] | Secret | Become hidden |
| Sneak | [one-action] | Move, Secret | Move while hidden |
| Seek | [one-action] | Secret | Search for hidden things |

### Utility Actions

| Action | Actions | Traits | Summary |
|--------|---------|--------|---------|
| Interact | [one-action] | Manipulate | Use object, draw weapon, etc. |
| Release | [free-action] | — | Let go of object |
| Delay | [free-action] | — | Act later in initiative |

## Practical Examples

### Example 1: Standard Combat Turn
**Fighter (level 3) fighting orc**
- [one-action] Stride: Close distance to orc
- [one-action] Strike: Attack at +11 (no MAP)
- [one-action] Strike: Second attack at +6 (MAP -5)

### Example 2: Defensive Turn
**Cleric in trouble**
- [two-actions] Cast Shield spell (gain AC bonus)
- [one-action] Stride: Move away from enemy

### Example 3: Control Turn
**Rogue using tricks**
- [one-action] Feint: Make enemy flat-footed
- [one-action] Strike: Attack flat-footed enemy (flanking + sneak attack)
- [one-action] Step: Move 5 feet away without triggering Attack of Opportunity

### Example 4: Using Reactions
**Fighter with Attack of Opportunity**
- Uses 3 actions on own turn
- Enemy wizard within reach casts spell (manipulate trait)
- **[reaction] Attack of Opportunity**: Strike the wizard
- Next enemy moves away from Fighter
- No reaction to use (already used it this round)

## Tips for Action Economy

1. **Plan your turn**: Know what you want to accomplish
2. **Watch MAP**: Third attack often has poor hit chance
3. **Use reactions**: Don't waste your reaction each round
4. **Positioning matters**: Step instead of Stride when enemies threaten you
5. **Buff smartly**: Duration buffs early, immediate buffs right before attacking
6. **Save actions**: Sometimes 2 actions + defense beats 3 offensive actions

## Related Mechanics

- [Combat and Encounter Mechanics](./02-combat-encounter-mechanics.md) - How combat flows
- [Skill Checks](./04-skill-checks.md) - Using skills as actions
- [Spellcasting](./05-spellcasting-process.md) - Casting spells as actions
