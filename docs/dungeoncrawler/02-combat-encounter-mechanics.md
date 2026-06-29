# Pathfinder 2E: Combat and Encounter Mechanics

## Overview

Combat in Pathfinder 2E uses an encounter structure where time is divided into rounds and turns, with each participant acting in initiative order.

## Encounter Structure

An encounter follows these four main steps:

### Step 1: Roll Initiative

When combat begins, all participants roll for initiative:

1. **GM calls for initiative** when encounter starts
2. **Each participant rolls** a Perception check (or other skill if appropriate)
3. **Results ranked** from highest to lowest
4. **Initiative order** determines turn sequence for entire encounter

**Tie Resolution**:
- NPCs/monsters go before PCs
- PCs tied with each other decide amongst themselves

**Alternative Initiative Skills**:
- Stealth (if sneaking)
- Deception (if tricking opponents)
- Other skills as GM determines appropriate

### Step 2: Play a Round

A **round** represents approximately **6 seconds** of game time.

During a round:
1. Each participant takes one **turn** in initiative order
2. After all turns complete, round ends
3. Any participant can use **reactions** or **free actions** during others' turns (if triggered)

### Step 3: Begin the Next Round

- Initiative order continues from previous round
- **No re-rolling** initiative (keeps same order)
- Start new round at top of initiative order
- Repeat Step 2

### Step 4: End the Encounter

Combat ends when:
- All foes are defeated or flee
- A truce or surrender occurs
- Party retreats or escapes
- GM determines encounter concludes

After encounter ends, play returns to **exploration mode**.

## Turn Structure

Each creature's turn consists of three steps:

### Step 1: Start Your Turn

At the start of your turn:
1. **Reduce round counters** on effects by 1 (durations measured in rounds)
2. **Use free actions/reactions** with "turn begins" trigger
3. **Roll recovery check** if you have the dying condition
4. **Regain resources**:
   - Regain **3 actions**
   - Regain **1 reaction**
5. **Lose unused reactions** from previous round

### Step 2: Act

This is the main phase where you take actions:

- Use up to **3 actions** in any order you choose
- Can use your **1 reaction** if appropriate trigger occurs
- Can use **free actions** if appropriate circumstances occur
- Must complete **multi-action activities** on same turn
- Your turn can end early (losing remaining actions if you choose)

### Step 3: End Your Turn

At the end of your turn:
1. **End ongoing effects** that last "until end of your turn"
2. **Take persistent damage** (if you have any)
3. **Apply other end-of-turn effects** as specified

After your turn ends, the next creature in initiative order begins their turn.

## Key Combat Concepts

### Time Measurement
- **1 round** = 6 seconds of game time
- **10 rounds** = 1 minute
- **100 rounds** = 10 minutes

### Actions Per Turn
- **3 actions** (can be any combination)
- **1 reaction** (usable during any turn, including yours, if triggered)
- **Unlimited free actions** (within reason, subject to triggers)

### Movement
- **Speed**: Movement rate in feet per action spent on Stride
- **Typical Speed**: 25-30 feet for most Medium creatures
- **Difficult Terrain**: Each square costs 5 extra feet
- **Move Actions**: Stride, Step, Leap, Crawl, Climb, Swim, Fly

### Reach
- **Standard Reach**: 5 feet for Medium creatures
- **Long Reach**: Some weapons and large creatures have greater reach
- Adjacent squares are within reach

### Flanking
- Two characters **flank** an enemy if they are on opposite sides
- Must both threaten the enemy (be within reach)
- Grants **+2 circumstance bonus** to melee attack rolls against flanked creature

## Attack Mechanics

### Making an Attack Roll

**Attack Roll** = d20 + ability modifier + proficiency bonus + other bonuses - penalties

Compare result to target's **Armor Class (AC)**:
- **Success**: Attack hits (roll damage)
- **Failure**: Attack misses

Apply [Degree of Success rules](./04-skill-checks.md#degrees-of-success):
- **Critical Hit**: Attack roll exceeds AC by 10+ OR natural 20
- **Critical Miss**: Attack roll fails by 10+ OR natural 1

### Multiple Attack Penalty (MAP)

Each subsequent attack on your turn takes an increasing penalty:

| Attack | Normal Weapons | Agile Weapons |
|--------|----------------|---------------|
| 1st Attack | No penalty | No penalty |
| 2nd Attack | -5 penalty | -4 penalty |
| 3rd+ Attack | -10 penalty | -8 penalty |

**Key Points**:
- Applies to ANY action/activity with the **Attack** trait
- Resets at start of your next turn
- Applies even if first attack misses
- Agile weapons have reduced MAP

**Strategy Tip**: Use your most important attack first, or use non-attack actions between attacks.

### Damage

When an attack hits:
1. **Roll damage dice** as specified by weapon/spell
2. **Add modifiers** (Strength for melee, Dexterity for ranged, etc.)
3. **Apply resistances/weaknesses** of target
4. **Subtract from target's Hit Points**

### Armor Class (AC)

**AC** = 10 + Dexterity modifier + proficiency bonus + armor bonus + shield bonus + other bonuses

- Higher AC is harder to hit
- Wearing armor may cap Dexterity bonus
- Shields add bonus when raised (+2 for most shields)

## Conditions in Combat

Common combat conditions:

| Condition | Effect Summary |
|-----------|----------------|
| **Dying** | Unconscious and close to death; must make recovery checks |
| **Unconscious** | Can't act, flat-footed, take -4 AC penalty |
| **Prone** | Lying on ground; -2 AC penalty vs melee, +2 AC vs ranged; standing costs action |
| **Flat-Footed** | -2 AC penalty (from flanking, surprise, etc.) |
| **Grabbed** | Can't move; can try to Escape |
| **Restrained** | Can't move or take move actions; flat-footed; penalties to attacks and Reflex |
| **Stunned** | Lose actions (stunned number of actions) |
| **Slowed** | Lose actions (reduce actions by slowed value) |
| **Quickened** | Gain extra action with restrictions |
| **Frightened** | Penalty to most checks (reduces by 1 each turn) |
| **Blinded** | Can't see; flat-footed automatically fail Perception checks |
| **Confused** | Attack random creatures or self |
| **Paralyzed** | Can't act; flat-footed; automatically fail Reflex saves |

## Basic Combat Actions

### Offensive Actions
- **Strike** [one-action]: Make an attack roll with weapon/unarmed
- **Cast a Spell** [varies]: Cast a spell (usually 2-action activity)

### Defensive Actions
- **Raise a Shield** [one-action]: Gain shield's AC bonus until start of next turn
- **Take Cover** [one-action]: Gain cover bonus to AC and Reflex saves
- **Dodge** [one-action]: Gain +1 circumstance bonus to AC (rare, from feats)

### Movement Actions
- **Stride** [one-action]: Move up to your Speed
- **Step** [one-action]: Move 5 feet without triggering reactions
- **Leap** [one-action]: Jump vertically or horizontally
- **Crawl** [one-action]: Move 5 feet while prone

### Utility Actions
- **Seek** [one-action]: Make Perception check to find hidden creatures
- **Ready** [two-actions]: Prepare to use action/reaction when trigger occurs
- **Delay** [free-action]: Move later in initiative order
- **Aid** [reaction]: Help ally by granting +1 circumstance bonus

### Interaction Actions
- **Interact** [one-action]: Manipulate object (draw weapon, open door, etc.)
- **Release** [free-action]: Let go of something

## Tactical Considerations

### Positioning
- Use flanking to gain +2 bonus (+10% hit chance)
- Stay mobile to avoid being surrounded
- Use terrain for cover
- Control chokepoints

### Action Economy
- Sometimes skipping third attack (high MAP) for defensive/buff action is better
- Use 3-action or 2-action abilities when  they're more effective than multiple Strikes
- Save reactions for important moments (Attack of Opportunity, Shield Block)

### Focus Fire
- Eliminating one enemy reduces incoming damage
- Target enemies with lower AC or vulnerabilities
- Coordinate with party on priority targets

## Example Combat Round

**Initiative Order**: Fighter (20), Goblin Warrior (15), Wizard (12), Goblin Commando (8)

**Fighter's Turn** (3 actions):
1. [one-action] Stride: Move 25 feet toward Goblin Warrior
2. [one-action] Strike: Attack goblin (no MAP)
3. [one-action] Raise Shield: Gain +2 AC until next turn

**Goblin Warrior's Turn** (3 actions):
1. [one-action] Stride: Move toward Fighter
2. [one-action] Strike: Attack Fighter at -2 AC (shield raised) - First attack, no MAP
3. [one-action] Strike: Second attack at -5 MAP

**Wizard's Turn** (3 actions):
1. [two-actions] Cast Fireball: Target both goblins
2. [one-action] Stride: Move to better position

**Goblin Commando's Turn** (3 actions):
1. [one-action] Stride: Move toward party
2. [two-actions] Attack action that uses 2 actions

Round ends. All effects counting in rounds decrease by 1. New round begins with Fighter.

## Related Mechanics

- [Action System](./03-action-system.md) - Detailed action rules
- [Skill Checks](./04-skill-checks.md) - How to resolve checks in combat
- [Spellcasting](./05-spellcasting-process.md) - Casting spells in combat
