# Pathfinder 2E: Skill Checks

## Overview

Skill checks determine success or failure when attempting tasks that have uncertain outcomes. They use the core check mechanic with four degrees of success.

## Making a Skill Check

Follow these four steps for every skill check:

### Step 1: Roll d20 and Identify Modifiers

**Roll**: Roll a 20-sided die (d20)

**Add**:
- Ability modifier (Strength, Dexterity, etc.)
- Proficiency bonus
- Item bonuses
- Status bonuses
- Circumstance bonuses

**Subtract**:
- Circumstance penalties
- Status penalties
- Item penalties
- Untyped penalties

**Important Bonus/Penalty Rules**:
- Only the **highest bonus of each type** applies
- All **penalties stack** except when they're the same type (then highest applies)
- **Untyped penalties stack** with everything

### Step 2: Calculate Result

**Result** = d20 + ability modifier + proficiency + bonuses - penalties

### Step 3: Compare to DC

- **Success**: Result **≥** DC
- **Failure**: Result **<** DC

### Step 4: Determine Degree of Success

Calculate how well you succeeded or failed:

#### Four Degrees

**Critical Success**
- Result **exceeds DC by 10 or more**, OR
- You rolled a **natural 20** (improves degree by one step)
- Best possible outcome

**Success**
- Result **meets or exceeds DC**
- Task accomplished

**Failure**
- Result is **less than DC**
- Task not accomplished

**Critical Failure**
- Result is **lower than DC by 10 or more**, OR
- You rolled a **natural 1** (worsens degree by one step)
- Worst possible outcome, often with additional consequences

#### Natural 20 and Natural 1

- **Natural 20**: Improves degree of success by one step
  - Failure → Success
  - Success → Critical Success
- **Natural 1**: Worsens degree of success by one step
  - Success → Failure
  - Failure → Critical Failure

**Important**: Natural 20/1 only adjusts by **one step**, so:
- Natural 20 that fails by 10+ = Failure (not success)
- Natural 1 that succeeds by 10+ = Success (not failure)

## Proficiency Calculation

**Proficiency Bonus** = proficiency rank value + character level

| Proficiency Rank | Value | Total Bonus |
|------------------|-------|-------------|
| **Untrained** | +0 | +0 + level |
| **Trained** | +2 | +2 + level |
| **Expert** | +4 | +4 + level |
| **Master** | +6 | +6 + level |
| **Legendary** | +8 | +8 + level |

**Example**: 5th-level character with Expert proficiency = +4 + 5 = **+9 proficiency bonus**

## Difficulty Classes (DCs)

The GM sets DCs based on task difficulty.

### Simple DCs

Use when difficulty abstract or doesn't relate to specific level:

| Task Difficulty | DC |
|----------------|-----|
| **Untrained** | 10 |
| **Trained** | 15 |
| **Expert** | 20 |
| **Master** | 30 |
| **Legendary** | 40 |

**Example**: Climbing a rough wall with handholds = Trained DC (15)

### Level-Based DCs

Use for tasks tied to specific creature, object, or challenge level:

| Level | DC | Level | DC |
|-------|-----|-------|-----|
| 0 | 14 | 11 | 28 |
| 1 | 15 | 12 | 29 |
| 2 | 16 | 13 | 30 |
| 3 | 18 | 14 | 31 |
| 4 | 19 | 15 | 34 |
| 5 | 20 | 16 | 35 |
| 6 | 22 | 17 | 36 |
| 7 | 23 | 18 | 38 |
| 8 | 24 | 19 | 39 |
| 9 | 26 | 20 | 40 |
| 10 | 27 | 21+ | 40 + (level-20)×2 |

**Example**: Recalling knowledge about 7th-level monster = DC 23

### DC Adjustments

Modify DCs based on specific circumstances:

| Adjustment | DC Modifier | Rarity |
|------------|-------------|---------|
| **Incredibly Easy** | -10 | — |
| **Very Easy** | -5 | — |
| **Easy** | -2 | — |
| **Standard** | +0 | Common |
| **Hard** | +2 | Uncommon |
| **Very Hard** | +5 | Rare |
| **Incredibly Hard** | +10 | Unique |

**Example**: Identifying common potion (1st level) = DC 15. Identifying rare potion (1st level) = DC 15 + 5 = **DC 20**

## Core Skills

Pathfinder 2E has 17 core skills:

### Physical Skills
- **Acrobatics** (Dex): Balance, tumble, maneuver in air
- **Athletics** (Str): Climb, swim, leap, grapple, shove

### Social Skills
- **Deception** (Cha): Lie, feint, create diversion
- **Diplomacy** (Cha): Make requests, gather information, negotiate
- **Intimidation** (Cha): Coerce, demoralize, threaten

### Mental Skills
- **Arcana** (Int): Recall knowledge about magic, spells, creatures
- **Nature** (Wis): Recall knowledge about animals, plants, weather, terrain
- **Occultism** (Int): Recall knowledge about occult mysteries, creatures
- **Religion** (Wis): Recall knowledge about gods, undead, planes
- **Society** (Int): Recall knowledge about civilization, culture, history

### Perceptive Skills
- **Medicine** (Wis): Treat wounds, treat disease/poison, identify cause of death
- **Perception** (Wis): Notice things, search, sense danger
- **Survival** (Wis): Track, subsist, cover tracks, sense direction

### Technical Skills
- **Crafting** (Int): Create items, repair, identify items
- **Performance** (Cha): Act, play music, tell stories
- **Stealth** (Dex): Hide, sneak, conceal object
- **Thievery** (Dex): Pick locks, disable traps, palm objects, steal

### Special Skills
- **Lore** (Int): Specialized knowledge in specific subject

## Common Skill Actions

### Exploration Activities (Continuous)
These take extended time and are done during exploration:

| Skill | Action | Summary |
|-------|--------|---------|
| Arcana/Nature/Occultism/Religion | Identify Magic | Recognize magic effects |
| Crafting | Craft | Create or repair items |
| Medicine | Treat Wounds | Heal Hit Points |
| Perception | Search | Look for hidden things |
| Society | Decipher Writing | Understand obscure text |
| Stealth | Avoid Notice | Stay hidden while traveling |

### Single Actions [one-action]
Quick actions usable in encounters:

| Skill | Action | Summary | DC |
|-------|--------|---------|-----|
| Acrobatics | Balance | Cross narrow surface | Varies by surface |
| Acrobatics | Tumble Through | Move through enemy's space | Target's Reflex DC |
| Athletics | Climb | Scale wall or obstacle | Varies by surface |
| Athletics | Grapple | Grab and restrain foe | Target's Fortitude DC |
| Athletics | Shove | Push foe away | Target's Fortitude DC |
| Athletics | Trip | Knock foe prone | Target's Reflex DC |
| Deception | Create Diversion | Hide or Sneak as follow-up | Perception DC |
| Deception | Feint | Make foe flat-footed | Perception DC |
| Intimidation | Demoralize | Frighten foe | Will DC |
| Medicine | Administer First Aid | Stabilize dying creature | DC 15 |
| Stealth | Hide | Become hidden/undetected | Perception DC |
| Stealth | Sneak | Move while hidden | Perception DC |
| Thievery | Palm Object | Conceal small item | Perception DC |
| Thievery | Steal | Take item from creature | Perception DC |

### Two-Action Activities [two-actions]

| Skill | Action | Summary |
|-------|--------|---------|
| Athletics | Disarm | Remove item from foe's grasp |
| Athletics | High Jump | Jump vertically |
| Athletics | Long Jump | Jump horizontally |
| Perception | Seek | Search for hidden creatures/objects |

## Secret Checks

Some checks are made secretly by the GM:

**Secret Check Skills**:
- Seek (Perception)
- Sense Motive (Perception)
- Identify Magic (Various)
- Recall Knowledge (Various)

**Why Secret**: Players shouldn't know whether they succeeded or failed, as the character might not realize their mistake.

## Recall Knowledge

One of the most common skill uses:

**Action**: Single action [one-action] or free action (GM's choice)

**Skills by Topic**:
- **Arcana**: Arcane magic, constructs, dragons, magical beasts
- **Nature**: Animals, fey, plants, primal magic, weather
- **Occultism**: Aberrations, occult magic, mental magic
- **Religion**: Divine magic, clerics, undead, celestials, fiends, monitors

**Success**: Learn something about the creature or topic
**Critical Success**: Learn substantial information, including unusual weaknesses or abilities

**DC**: Use creature's level as base DC

## Skill Feats

Characters gain skill feats as they level up, unlocking new uses for skills:

**Examples**:
- **Assurance**: Take 10 on check instead of rolling
- **Cat Fall** (Acrobatics): Take less damage from falls
- **Intimidating Glare** (Intimidation): Demoralize without speaking
- **Quick Jump** (Athletics): Jump without needing 2 actions
- **Trick Magic Item** (Arcana/Nature/Occultism/Religion): Activate magic items

## Opposed Checks

When two creatures directly oppose each other:

**Both roll**: Each makes appropriate skill check
**Higher result wins**: Ties go to character with higher modifier
**Degrees apply**: Can critically succeed if you beat opponent by 10+

**Examples**:
- Stealth vs Perception (hiding vs noticing)
- Deception vs Perception (lying vs spotting lie)
- Athletics vs Fortitude DC or Reflex DC (grapple, shove, trip)

## Taking 10 and Taking 20

**Taking 10**: Not standard in PF2E, but Assurance feat allows automatic result of 10

**Taking 20**: Not in core rules as formal mechanic, but GM might allow repeated attempts in low-pressure situations

## Circumstance Modifiers

GMs can apply situational bonuses or penalties:

**Common Circumstance Bonuses (+1 to +2)**:
- Using proper tools
- Favorable environment
- Assistance from others

**Common Circumstance Penalties (-1 to -4)**:
- Poor conditions
- Distraction
- Lacking proper tools
- Improvising

## Examples

### Example 1: Climbing

**Serena** (3rd-level rogue) climbs a stone wall
- **Proficiency**: Trained in Athletics (+2 + 3 = +5)
- **Ability**: Strength +1
- **Total**: +6
- **DC**: 15 (Trained difficulty)
- **Roll**: 13 + 6 = **19** → **Success**

### Example 2: Demoralize

**Valeros** (5th-level fighter) demoralizes goblin
- **Proficiency**: Trained in Intimidation (+2 + 5 = +7)
- **Ability**: Charisma +2
- **Total**: +9
- **DC**: Goblin's Will DC 14
- **Roll**: Natural 20! (11 + 9 = 20, already success)
- **Result**: **Critical Success** (natural 20 improves degree)
- Effect: Goblin frightened 2 instead of frightened 1

### Example 3: Critical Failure

**Kyra** (1st-level cleric) tries to identify rare poison
- **Proficiency**: Trained in Crafting (+2 + 1 = +3)
- **Ability**: Intelligence +1
- **Total**: +4
- **DC**: DC 20 (5th level poison, rare = +5)
- **Roll**: 5 + 4 = **9** → Fails by 11
- **Result**: **Critical Failure**
- Effect: GM gives false information about the poison

### Example 4: Secret Check

**Merisiel** searches a room for hidden compartments
- **GM rolls secretly**: d20 for player's Perception
- Result determines what Merisiel finds
- Player doesn't know if they failed or if nothing is there

## Tips for Skill Checks

1. **Describe your approach**: Tell GM how you'reattempting task
2. **Use skills creatively**: Find ways to apply trained skills
3. **Aid allies**: Use Aid action to grant +1 bonus to ally's check
4. **Consider proficiency**: Higher proficiency = more reliable
5. **Watch for critical opportunities**: High bonuses can critically succeed on lower DCs
6. **Don't forget item bonuses**: Tools and magic items can help

## Related Mechanics

- [Character Creation](./01-character-creation-process.md) - Selecting skills
- [Action System](./03-action-system.md) - Using skills as actions
- [Combat Mechanics](./02-combat-encounter-mechanics.md) - Skills in combat
