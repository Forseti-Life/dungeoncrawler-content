# Issue #14: Derived Statistics Calculator

**Status**: Open  
**Type**: Feature Request  
**Priority**: Critical  
**Source**: PF2E Core Rulebook Chapter 1 — Step 7 (Fill in Numbers)  
**Created**: 2026-02-27

## Overview

Multiple character statistics are derived mechanically from ability scores, proficiency ranks, level, and equipment. This issue defines the auto-calculation engine for all derived statistics, the modifier stacking model, and the recalculation trigger points.

## Requirements

### REQ-14.1 — Proficiency Bonus Formula
The core formula used throughout all derived stat calculations:

| Rank | Proficiency Bonus |
|---|---|
| UNTRAINED | +0 (level is NOT added) |
| TRAINED | Level + 2 |
| EXPERT | Level + 4 |
| MASTER | Level + 6 |
| LEGENDARY | Level + 8 |

This formula must be implemented as a shared utility function referenced by all other calculations in this issue.

### REQ-14.2 — Armor Class (AC)
```
AC = 10 + DEX modifier (capped by armor's dex_cap) + proficiency bonus (armor rank) + item bonus (armor's ac_bonus)
```
- DEX modifier applied is `min(DEX_modifier, armor.dex_cap)`.
- Proficiency rank used is the character's proficiency rank in the worn armor's category (unarmored/light/medium/heavy).
- At 1st level with no armor proficiency above Untrained: armor rank is Trained if the class grants it.
- Proficiency bonus for AC uses the armor proficiency rank, not a weapon or skill rank.

### REQ-14.3 — Saving Throws
Three saving throws; each uses a specific ability modifier:

| Save | Ability |
|---|---|
| Fortitude | CON modifier |
| Reflex | DEX modifier |
| Will | WIS modifier |

```
Save bonus = ability modifier + proficiency bonus (save rank)
```
Proficiency rank for each save is set by the class's initial proficiencies and advancement table.

### REQ-14.4 — Perception
```
Perception = WIS modifier + proficiency bonus (Perception rank)
```
Perception rank is set by the class's initial proficiencies.

### REQ-14.5 — Skill Bonuses
For each skill:
```
Skill bonus = governing ability modifier + proficiency bonus (skill rank)
```
Governing ability modifiers per skill (all must be defined in data):

| Skill | Ability | Skill | Ability |
|---|---|---|---|
| Acrobatics | DEX | Medicine | WIS |
| Arcana | INT | Nature | WIS |
| Athletics | STR | Occultism | INT |
| Crafting | INT | Performance | CHA |
| Deception | CHA | Religion | WIS |
| Diplomacy | CHA | Society | INT |
| Intimidation | CHA | Stealth | DEX |
| Lore (any) | INT | Survival | WIS |
| | | Thievery | DEX |

### REQ-14.6 — Strike Attack Roll
For each weapon a character has equipped:
```
Strike bonus = ability modifier + proficiency bonus (weapon proficiency rank)
```
- Melee strikes typically use STR modifier; finesse weapons may use DEX (whichever is higher).
- Ranged strikes typically use DEX modifier.
- Weapon proficiency rank is based on weapon category (Simple/Martial/Advanced) and class proficiencies.
- Damage formula is separate: `weapon.damage_dice + ability modifier` (typically STR for melee, none for ranged unless special).

### REQ-14.7 — Spell Attack Roll & Spell DC
For spellcasting characters:
```
Spell attack roll = key ability modifier + proficiency bonus (spell attack rank)
Spell DC = 10 + key ability modifier + proficiency bonus (spell DC rank)
```
Spell attack rank and spell DC rank are set by class's `SpellcastingConfig` (see Issue #12).

### REQ-14.8 — Class DC
```
Class DC = 10 + key ability modifier + proficiency bonus (class DC rank)
```
Class DC rank is typically Trained at 1st level for most classes.

### REQ-14.9 — Hit Points
```
Total HP = Ancestry HP + (Class HP per level + CON modifier) × character level
```
- At character creation (level 1): `HP = ancestry_hp + class_hp_per_level + CON_modifier`
- CON modifier is added once per level; when CON modifier increases (e.g., due to ability boost), retroactively add the difference × all previous levels.

### REQ-14.10 — Speed
- Base Speed is granted by ancestry (e.g., 25 feet for human, 20 feet for dwarf).
- Speed may be modified by: armor speed penalty, encumbrance (–10 feet).
- Final Speed = base Speed + all speed modifiers.

### REQ-14.11 — Modifier Stacking Rules
The modifier type system determines which bonuses stack:

| Modifier Type | Stacks? |
|---|---|
| Untyped (circumstance unmarked) | Yes (all stack) |
| Circumstance | No (take highest) |
| Item | No (take highest) |
| Status | No (take highest) |
| Proficiency bonus | Exactly one per statistic; always applied |

- Bonuses of different types always stack with each other.
- Penalties of the same type stack (unless the rules say otherwise).
- The system must track modifier type for all bonuses/penalties to enforce stacking rules.

### REQ-14.12 — Recalculation Triggers
The derived stat engine must recalculate all affected statistics whenever:
1. Any ability score changes (ability boost, flaw, item, or spell effect).
2. Character level increases.
3. Proficiency rank changes for any statistic.
4. Equipped armor changes.
5. Any applied bonus or penalty is added, removed, or changed.

Recalculation must be consistent: changing one score must not leave any dependent stat stale.

### REQ-14.13 — Situational vs. Permanent Modifiers
- Permanent modifiers (level, proficiency, ability scores) are always included in base stat values.
- Situational modifiers (flanking, frightened, etc.) are tracked separately and displayed as conditional notes.
- The character sheet shows base values; a "situational modifiers" section is available for active conditions.

## Acceptance Criteria
- [ ] Proficiency formula verified: Trained at Level 1 = +3; Expert at Level 1 = +5; Untrained at Level 1 = +0.
- [ ] Sample character (Gar, Level 1 Dwarf Druid): AC = 14 (explorer's clothing DEX cap +5, Trained unarmored, DEX +3).
- [ ] Sample character: Fort +5, Ref +3, Will +6 match book values.
- [ ] Sample character: Perception +6 (WIS +3, Expert Perception = 1+4=5… or Trained +3=4+3=6, yes).
- [ ] Skill list correctly calculates all 17 skills + any Lore skills.
- [ ] Strike bonus calculated for staff (melee, STR) and shortbow (ranged, DEX).
- [ ] HP = 21 for sample character (10 ancestry + 8 class + 3 CON).
- [ ] Speed = 20 feet for dwarf base.
- [ ] Modifier stacking: two circumstance bonuses — only highest applies.
- [ ] CON boost at level-up retroactively adds 1 HP per prior level.

## Source Paragraphs
- "Step 7: Fill in Numbers — Now that you've determined your ability scores, it's time to calculate the numbers you'll use most often in play." (Ch1, Step 7, §1)
- "Proficiency bonus adds your level + 2 (trained), +4 (expert), +6 (master), or +8 (legendary). Untrained adds nothing." (Ch1, Reading Rules)
- Sample character Step 7: full stat block for Gar the Dwarf Druid, all derived stats shown.
