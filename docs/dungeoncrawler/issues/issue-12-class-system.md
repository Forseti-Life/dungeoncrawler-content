# Issue #12: Class System

**Status**: Open  
**Type**: Feature Request  
**Priority**: Critical  
**Source**: PF2E Core Rulebook Chapter 1 — Steps 5, 7; Sample Character  
**Created**: 2026-02-27

## Overview

A character's class is the primary source of their combat identity, heroic abilities, and advancement path. This issue defines the class data model, the proficiency rank system, the class advancement table, spellcasting integration, and class-specific constructs such as sub-class (Order) selection, anathema, and class-granted languages.

## Requirements

### REQ-12.1 — Class Entity Data Model

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | string | Yes | Class name (e.g., "Druid", "Fighter") |
| `key_ability` | string[] | Yes | One or two ability score names for key ability boost |
| `secondary_abilities` | string[] | No | Recommended secondary ability scores (advisory only) |
| `hit_points_per_level` | integer | Yes | HP gained per level (before CON modifier) |
| `initial_proficiencies` | Proficiencies | Yes | Starting proficiency ranks (see REQ-12.2) |
| `class_dc_key_ability` | string | Yes | Ability modifier used for Class DC |
| `advancement_table` | LevelEntry[] | Yes | Level 1–20 class features per level |
| `sub_selections` | SubSelection[] | No | E.g., Druid Order; required sub-choices at certain levels |
| `anathema` | string | No | Behavioral restrictions (Druid, Champion, Cleric) |
| `special_languages` | string[] | No | Languages granted by class (e.g., "Druidic") |
| `spellcasting` | SpellcastingConfig | No | Present only for spellcasting classes |

### REQ-12.2 — Proficiency Rank System
Four proficiency ranks must be modeled as an enum:
- `UNTRAINED` — proficiency bonus = 0 (no level added)
- `TRAINED` — proficiency bonus = Level + 2
- `EXPERT` — proficiency bonus = Level + 4
- `MASTER` — proficiency bonus = Level + 6
- `LEGENDARY` — proficiency bonus = Level + 8

Proficiency ranks apply to: Perception, saving throws (Fort/Ref/Will), skills, weapon groups, armor types, spell attack rolls, and class DC.

Initial proficiencies provided by the class at 1st level must specify a rank for each applicable area.

### REQ-12.3 — HP Calculation
- Total HP = Ancestry HP + (Class HP per level + CON modifier) × character level.
- At 1st level: Total HP = Ancestry HP + Class HP per level + CON modifier.
- Class HP per level is `hit_points_per_level` (e.g., 8 for Druid, 10 for Fighter, 6 for Wizard).
- Verified by sample character: Dwarf Druid = 10 (ancestry) + 8 (druid) + 3 (CON mod) = 21 HP.

### REQ-12.4 — Key Ability Score Boost
- At Step 5 (class selection), the character receives an ability boost to their class's key ability score.
- This boost is part of the Step 5 batch.
- For classes with a choice of key ability (e.g., Fighter: STR or DEX), the player must select one.

### REQ-12.5 — Class Advancement Table
- Each class must have an advancement table mapping level (1–20) to a list of class features/feats gained at that level.
- The table drives: class feat availability, class feature grants, ability boost levels (5/10/15/20), skill increase levels.
- At level-up, the system reads the advancement table for the new level and applies all listed entries.

### REQ-12.6 — Skill Training from Class
- The class specifies: (a) a set of fixed trained skills, and (b) a number of player-chosen trained skills.
- Number of player-chosen trained skills = class base count + INT modifier (minimum 0).
- If a class-granted skill training duplicates a background-trained skill, the player substitutes a different skill.

### REQ-12.7 — Sub-Selection (e.g., Druid Order)
- Some classes have a required sub-selection at 1st level (or at other levels per advancement table).
- Sub-selection is modeled as a `SubSelection` entity with: name, additional trained skill, granted feats, granted spells, and any special rules.
- Druid Order is the canonical example: Wild Order grants Intimidation trained, wild morph ability, Wild Shape feat, and 1 Focus Point.
- Sub-selection must be recorded on the character sheet.

### REQ-12.8 — Anathema
- Some classes (Druid, Champion, Cleric) carry behavioral restrictions called anathema.
- Anathema is a free-text field recorded on the character sheet (informational; enforcement is GM-ruled, not automated).
- Example: Druids cannot wear metal armor.

### REQ-12.9 — Class-Granted Languages
- Some classes grant access to a special language unavailable through ancestry or background.
- Example: Druid gains "Druidic" as a language.
- Class-granted languages are added to the character's language list at 1st level.

### REQ-12.10 — Spellcasting Configuration
Full spellcasting classes (Bard, Cleric, Druid, Sorcerer, Wizard) require a `SpellcastingConfig`:

| Field | Description |
|---|---|
| `tradition` | Magic tradition: arcane, divine, primal, or occult |
| `casting_type` | `prepared` (choose spells each day) or `spontaneous` (spell repertoire) |
| `spell_attack_rank` | Initial proficiency rank for spell attack rolls |
| `spell_dc_rank` | Initial proficiency rank for spell DC |
| `cantrips_per_day` | Number of cantrips (unlimited uses; typically 5 at 1st level) |
| `spell_slots` | Array of `{ level: N, slots: M }` entries per spell level per character level |
| `focus_points` | Initial focus point pool (if class has focus spells) |

- Cantrips are not expended on casting; they are always available.
- Prepared casters choose which spells to prepare each day; spontaneous casters have a fixed repertoire.

### REQ-12.11 — Minimum Core Classes (Data)
The following 12 core classes must be present in the game content database with accurate HP, key ability, and initial proficiency data:

| Class | HP/Level | Key Ability |
|---|---|---|
| Alchemist | 8 | INT |
| Barbarian | 12 | STR |
| Bard | 8 | CHA |
| Champion | 10 | STR or DEX |
| Cleric | 8 | WIS |
| Druid | 8 | WIS |
| Fighter | 10 | STR or DEX |
| Monk | 10 | STR or DEX |
| Ranger | 10 | STR or DEX |
| Rogue | 8 | DEX or other |
| Sorcerer | 6 | CHA |
| Wizard | 6 | INT |

## Acceptance Criteria
- [ ] All 12 core classes exist with correct HP and key ability.
- [ ] Druid: 8 HP/level; key ability WIS; requires Order sub-selection; grants Druidic language; anathema field set.
- [ ] Wild Order sub-selection grants: Intimidation trained, wild morph, Wild Shape feat, 1 Focus Point — matches sample character.
- [ ] Druid spellcasting config: primal tradition, prepared casting, 5 cantrips + 2 1st-level slots at 1st level.
- [ ] Proficiency bonus formula verified: Trained at Level 1 = +3; Expert at Level 1 = +5.
- [ ] Class skill overlap substitution works correctly.
- [ ] Advancement table drives level-up grants; tested at Level 1 and Level 2.

## Source Paragraphs
- "A class gives your character access to a suite of heroic abilities, determines how effectively they fight." (Ch1, Step 5, §1)
- "To determine your character's total starting Hit Points, add together the number of Hit Points your character gains from their ancestry and the number of Hit Points they gain from their class." (Ch1, Step 7, Bullet 1)
- Sample character Step 7: 10 (ancestry) + 8 (druid) + 3 (CON mod) = 21 HP; Wild Order grants Intimidation, wild morph, Wild Shape, Druidic.
