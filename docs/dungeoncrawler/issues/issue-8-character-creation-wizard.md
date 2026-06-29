# Issue #8: Character Creation Wizard

**Status**: Open  
**Type**: Feature Request  
**Priority**: High  
**Source**: PF2E Core Rulebook Chapter 1 — Character Creation  
**Created**: 2026-02-27

## Overview

Character creation is a multi-step process involving ancestry, background, class, ability scores, equipment, and final details. The wizard must support non-linear step completion, allow creation at levels above 1st, and tolerate optional/inapplicable fields gracefully. This issue covers wizard flow mechanics only; individual systems (ability scores, ancestry, class, etc.) are covered in their own issues.

## Requirements

### REQ-8.1 — Non-Linear Step Completion
- The 10 character creation steps may be completed in any order; no step is gated by a prior step being completed first.
- The system must track which steps have been completed and which remain without enforcing a linear sequence.
- The wizard UI may suggest an order but must permit out-of-order completion.

### REQ-8.2 — Higher-Level Character Creation
- Character creation must support starting at any level from 1 to 20.
- For levels above 1st, the wizard applies standard 1st-level creation first, then iterates the leveling-up process (Issue #17) for each level up to the target level.
- Ability boosts granted at levels 5, 10, 15, and 20 must be applied during higher-level creation.

### REQ-8.3 — Null-Safe Character Sheet Fields
- Not all characters have entries for every field (e.g., a non-spellcaster has no spell slots; a non-worshipper has no deity).
- Every character sheet field must be nullable/optional without raising validation errors.
- Inapplicable fields must display as blank, not as a default value that implies false data.

### REQ-8.4 — Required Core Fields
The following fields are required on every character sheet (not nullable):
- Character name
- Class
- Level
- All six ability scores (STR/DEX/CON/INT/WIS/CHA)
- Ancestry
- Max HP
- AC
- Speed
- Proficiency in Perception, Fort, Ref, Will saves

### REQ-8.5 — Free-Text Notes Field
- Character sheet must include a free-text notes field for narrative concept, background description, and player-authored details.
- Notes field has no character limit enforced by the system.

### REQ-8.6 — Character Sheet Field Population by Step
The wizard must track which step populates which field:
- Step 1: Name, Notes
- Step 2: Ability score initialization (all scores = 10)
- Step 3: Ancestry, Heritage, Ancestry HP, Size, Speed, Languages, Special Senses, Ancestry Feat, Ability Score adjustments from ancestry
- Step 4: Background, Skill training (background skill + lore skill), Skill Feat from background, Ability Score adjustments from background
- Step 5: Class, Level (= 1), Key Ability Score boost
- Step 6: Finalized ability scores + all modifiers
- Step 7: Total HP, Initial Proficiency ranks (Perception/saves/skills/weapons/armor), Class feats and features
- Step 8: Equipment, Starting wealth remainder, Melee/Ranged Strikes list
- Step 9: All computed modifiers (Perception total, Save totals, Strike modifiers, Skill totals)
- Step 10: Alignment, Deity, Age, Gender/Pronouns, Class DC, Hero Points, AC, Bulk limits

## Acceptance Criteria
- [ ] Wizard allows completing Step 6 before Step 3 and still produces a valid character.
- [ ] Creating a Level 5 character correctly applies ancestry/background/class at Level 1, then applies 4 ability boosts at Level 5.
- [ ] A Fighter character sheet has no spell slot fields rendered/required.
- [ ] Saving a character with only Name + Class + Level (all other fields null) succeeds without error.
- [ ] Notes field accepts freeform text of at least 5,000 characters.
- [ ] Tests cover: out-of-order step completion, level-5 creation, null-field save, higher-level ability boost count.

## Source Paragraphs
- "The steps of character creation are presented in a suggested order, but you can complete them in whatever order you prefer." (Ch1, Character Creation, §2)
- "If a field on your character sheet is not applicable to your character, just leave that field blank." (Ch1, Character Creation, §3)
- "If you're creating a higher-level character, it's a good idea to begin with the instructions here, then turn to page 29 for instructions on leveling up characters." (Ch1, Character Creation, §5)
