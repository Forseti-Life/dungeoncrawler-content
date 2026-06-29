# QA Verification Report — dc-apg-class-swashbuckler

- Feature: dc-apg-class-swashbuckler
- Dev item: 20260408-200013-impl-dc-apg-class-swashbuckler
- Dev commit: `0b2f2fc7f`
- QA decision: **APPROVE**
- Date: 2026-04-08

## Evidence

### CLASSES['swashbuckler']
All core mechanics confirmed:
- hp=10, key_ability=Dexterity ✓
- proficiencies: Perception=Expert, Fortitude=Trained (→Expert L3), Reflex=Expert, Will=Expert, class_dc=Trained ✓
- armor: light + unarmored; weapons: simple + martial ✓
- trained_skills=5 ✓

**Panache state machine:**
- binary type (in/out), consumed_on_finisher=TRUE (before outcome resolves) ✓
- +5ft status speed bonus with panache ✓
- +1 circumstance bonus to panache-earning checks ✓
- no-attack-trait actions after finisher rule ✓
- GM Very Hard DC award for daring non-standard actions ✓
- speed_bonus_without_panache table: L3=5, L7=7, L11=10, L15=12, L19=15 ✓

**Swashbuckler Styles (5):**
- battledancer: Performance skill + Fascinating Performance feat, panache via Performance vs foe Will DC ✓
- braggart: Intimidation skill, panache via Demoralize ✓
- fencer: Deception skill, panache via Feint or Create a Diversion ✓
- gymnast: Athletics skill, panache via Grapple/Shove/Trip vs same foe ✓
- wit: Diplomacy skill, panache via Bon Mot ✓

**Precise Strike:**
- requires_panache=TRUE, requires agile/finesse melee or unarmed ✓
- flat_bonus_by_level: L1=2, L5=3, L9=4, L13=5, L17=6 ✓
- finisher_dice_by_level: L1=2d6, L5=3d6, L9=4d6, L13=5d6, L17=6d6 ✓

**Finisher (Confident Finisher):**
- requires_panache=TRUE ✓
- success: full Precise Strike dice ✓
- failure: half Precise Strike dice ✓
- critical_failure: no Precise Strike damage ✓

**Opportune Riposte (L3):**
- type=Reaction, level_gained=3 ✓
- trigger: foe critically fails a Strike against you ✓
- effect: melee Strike OR Disarm ✓

**Exemplary Finisher (L9):**
- level_gained=9, trigger=Finisher Strike hits ✓
- 5 style-specific effects confirmed (battledancer/braggart/fencer/gymnast/wit) ✓

### CLASS_ADVANCEMENT['swashbuckler']
L1 auto_features confirmed (swashbuckler-panache, swashbuckler-style, swashbuckler-precise-strike, swashbuckler-confident-finisher). Higher-level entries confirmed in the block.

### PHP lint
`No syntax errors detected` — clean.

### Site audit
Run: 20260408-231209 — 0 missing assets, 0 permission violations, 0 failures. PASS.
(No new routes — ACL exemption applies.)

## KB references
- None applicable (new class type; no prior lessons on Swashbuckler mechanics).

## No new items for Dev
No defects found. PM may proceed to release gate for dc-apg-class-swashbuckler.
