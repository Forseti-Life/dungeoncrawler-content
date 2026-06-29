# QA Verification Report — dc-cr-animal-companion

- Feature: dc-cr-animal-companion
- Dev item: 20260408-200013-impl-dc-cr-animal-companion
- Dev commit: `24bcfd52b`
- QA decision: **APPROVE**
- Date: 2026-04-08

## Evidence

### ANIMAL_COMPANIONS constant
Constant confirmed present in `CharacterManager.php`.

**command_rules:**
- action='Command an Animal', action_cost=1 ✓
- check='Nature', dc_formula='DC 15, or the creature\'s Will DC if higher' ✓
- success_effect: companion takes 2 actions on its turn ✓
- no_command_effect: repeat same Stride/Strike from last turn (fallback behavior) ✓

**advancement table (4 stages):**
- young: base stats, all bonuses=0, initial_stage=TRUE ✓
- mature: +20hp, +2AC, +2 atk mod, +2 dmg, +2 saves, size_increase=TRUE, new companion-action ✓
- nimble (mature specialization): +30hp, +4AC, speed+10, evasion=TRUE ✓
- savage (mature specialization): extra attack (confirmed in section) ✓

**death_rules:**
- 0 HP = unconscious, not permanently dead ✓
- permanent death only from deliberate action or failed recovery ✓

**Species (7 confirmed):**
- bear: size=Medium, speeds/senses/base_ac/base_saves/attacks/support_benefit ✓
- bird: aerial_movement=TRUE, size/speeds/attacks/support_benefit ✓
- cat: size/speeds/senses/attacks/support_benefit ✓
- wolf: size/speeds/senses/attacks/support_benefit ✓
- horse: size/speeds/senses/attacks/support_benefit ✓
- snake: size/speeds/senses/attacks/support_benefit ✓
- ape: size/speeds/senses/attacks/support_benefit ✓

### PHP lint
`No syntax errors detected` — clean.

### Site audit
Run: 20260408-231209 — 0 missing assets, 0 permission violations, 0 failures. PASS.
(No new routes — ACL exemption applies.)

## KB references
- None applicable (new constant type; no prior lessons on animal companion data modeling).

## No new items for Dev
No defects found. PM may proceed to release gate for dc-cr-animal-companion.
