# Issue #17: Leveling Up System

**Status**: Open  
**Type**: Feature Request  
**Priority**: High  
**Source**: PF2E Core Rulebook Chapter 1 — Leveling Up section  
**Created**: 2026-02-27

## Overview

Characters advance by accumulating Experience Points (XP) and gaining levels. Each level-up applies a structured set of improvements from the class advancement table. This issue defines the XP system, level-up transaction mechanics, HP recalculation, and the milestone-level ability boost grants.

## Requirements

### REQ-17.1 — XP Accumulation
- Characters gain XP by completing encounters, quests, and achieving milestones.
- XP threshold per level: **1,000 XP** required to advance from level N to level N+1.
- XP carries over: if a character reaches 1,000 XP and has 150 excess, they begin the new level at 150 XP (not 0).
- Maximum character level: 20.
- XP beyond Level 20 has no mechanical effect (or may be tracked but unused).
- XP is stored as an integer on the character entity.

### REQ-17.2 — Level-Up Trigger
- When XP reaches or exceeds 1,000, a level-up is available.
- The system should present a level-up prompt or indicator to the player.
- The level-up process is a multi-step transaction; XP is decremented by 1,000 and level is incremented when the transaction is committed.
- If the transaction is cancelled or fails validation, no changes are persisted.

### REQ-17.3 — Level-Up Transaction (Steps May Be Done in Any Order)
A level-up transaction applies the following changes; steps may be completed in any order during the UI flow:

1. **Increment level** by 1.
2. **Apply class advancement table entries** for the new level (class features, feats unlocked).
3. **Add HP**: `+Class_HP_per_level + CON_modifier` HP to maximum HP.
4. **Class feat** (if the new level's advancement table grants one): player selects a feat from the class feat list, filtered by prerequisites.
5. **Skill increase** (if the new level grants one): player may increase one skill's proficiency rank by one step (UNTRAINED → TRAINED → EXPERT → MASTER → LEGENDARY; must meet level prerequisites for each rank).
6. **Ability boosts** (at levels 5, 10, 15, 20 only): player selects 4 free ability boosts with uniqueness constraints (see REQ-17.5).
7. **INT-driven gains** (if INT modifier increased due to ability boost at step 6): +1 trained skill + 1 additional language per point of INT modifier gained.
8. **General feat** (if the new level grants one): player selects a feat from the general feat list.
9. **Ancestry feat** (at specific levels per ancestry): player selects from ancestry feat list.
10. **Validate prerequisites**: at transaction commit, verify all selected feats meet their prerequisites. Block commit if any fail.

### REQ-17.4 — HP Recalculation on CON Boost
- When a CON ability boost during level-up raises the CON modifier (e.g., CON 18 → 19 = no change; CON 17 → 19 = +1 modifier), HP maximum is retroactively increased.
- Retroactive increase = 1 HP × number of levels the character has attained (including the level they are completing).
- This ensures the CON modifier contribution is consistent across all prior levels.

### REQ-17.5 — Ability Boosts at Levels 5/10/15/20
- At levels 5, 10, 15, and 20, the character receives **4 free ability boosts**.
- Each boost increases one ability score by 2 (or by 1 if the score is already 18+).
- Each boost must target a different ability score (cannot apply two boosts from this grant to the same score).
- Any ability score may be targeted (no restrictions from class or ancestry for these level boosts).
- These boosts are applied as part of the level-up transaction.

### REQ-17.6 — Proficiency Advancement
- At certain levels (per class advancement table), proficiency ranks for skills, saving throws, and attacks increase.
- The system must enforce rank ordering: a rank cannot skip a step (TRAINED → EXPERT → MASTER → LEGENDARY; cannot jump TRAINED → MASTER).
- Some ranks have level prerequisites: MASTER requires level 7+; LEGENDARY requires level 15+ (class and skill-specific thresholds vary).

### REQ-17.7 — Skill Increases
- Most classes gain skill increases at levels 3, 5, 7, 9, 11, 13, 15, 17, and 19 (Rogue gains them more frequently).
- A skill increase allows the player to raise one skill's proficiency rank by one step.
- The player selects which skill; the system must present all skills eligible for increase (i.e., skills not yet at the maximum rank achievable at that level).

### REQ-17.8 — Level-Up Summary
- At the end of a level-up transaction, display a summary of all changes made:
  - New level
  - New HP maximum
  - New or changed proficiency ranks
  - Feats selected
  - Ability score changes (if any)
  - Other unlocked features

### REQ-17.9 — Derived Stat Recalculation
- After committing a level-up, all derived statistics (Issue #14) must be recalculated.
- Proficiency bonuses increase by 1 automatically (since the level component of the formula increases).
- Any stat that depends on a changed ability score must also recalculate.

## Acceptance Criteria
- [ ] XP threshold is exactly 1,000; excess XP carries over correctly.
- [ ] Level-up prompt appears when XP ≥ 1,000.
- [ ] Transaction: can complete steps in any order; commit validates all prerequisites.
- [ ] HP increases by `class_hp_per_level + CON_modifier` each level.
- [ ] CON boost retroactive HP recalc: +1 HP × current level applied when CON modifier increases.
- [ ] At level 5: exactly 4 free ability boosts offered; each must target different score.
- [ ] Boost at 18+: score increases by 1 (not 2).
- [ ] Skill increase presents eligible skills (not already at rank cap).
- [ ] Proficiency rank cannot skip steps.
- [ ] Level-up summary shown before committing.
- [ ] All derived stats recalculated post-commit; proficiency bonuses reflect new level.
- [ ] Tests cover: XP carryover, level-up transaction commit/cancel, CON retroactive HP, level-5 boosts, skill increase validation.

## Source Paragraphs
- "When your character accumulates 1,000 XP, they advance to the next level. Excess XP carries over to the new level." (Ch1, Leveling Up, §1)
- "At each level, you gain the benefits listed in your class's advancement table." (Ch1, Leveling Up, §2)
- "At levels 5, 10, 15, and 20, you gain four ability boosts. Each boost must go to a different ability score." (Ch1, Leveling Up, §4)
- "When you gain a level, add your class's Hit Points per level plus your Constitution modifier to your Hit Point maximum." (Ch1, Leveling Up, §3)
