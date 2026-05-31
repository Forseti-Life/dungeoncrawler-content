# Feature Brief: Vengeful Hatred (Dwarf Ancestry Feat)

- Work item id: dc-cr-vengeful-hatred
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: Depends on dc-cr-dwarf-ancestry (deferred); re-evaluate when dwarf ancestry is activated
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-ancestry-traits
- Source: PF2E Core Rulebook (Fourth Printing), lines 5584–5883
- Category: game-mechanic
- Schema changes: no
- Cross-site modules: none
- Created: 2026-04-05

## Goal

Implement the Vengeful Hatred level-1 ancestry feat for Dwarf characters. When selected, the player designates one ancestral foe type (drow, duergar, giant, or orc). The character gains +1 circumstance bonus to weapon/unarmed damage against that creature type, scaling to the number of weapon dice at higher levels. Additionally, if any creature critically hits this character and deals damage, the character gains the damage bonus against that specific creature for 1 minute regardless of its type.

## Source reference

> "Choose one of the following dwarven ancestral foes when you gain Vengeful Hatred: drow, duergar, giant, or orc. You gain a +1 circumstance bonus to damage with weapons and unarmed attacks against creatures with that trait. If your attack would deal more than one weapon die of damage (as is common at higher levels than 1st), the bonus is equal to the number of weapon dice or unarmed attack dice."
> "If a creature critically succeeds at an attack against you and deals damage to you, you gain your bonus to damage against that creature for 1 minute regardless of whether it has the chosen trait."

## Implementation hint

Create an `ancestry_feat` entity: `id: vengeful-hatred`, `level: 1`, `ancestry: dwarf`, `config: [chosen_foe_trait: enum(drow|duergar|giant|orc)]`. Damage hook: when resolving damage against a creature with the chosen trait, add `min(1, weapon_dice_count)` circumstance bonus. Critical hit tracking: when a creature lands a critical hit with damage on this character, set a 1-minute flag on that creature ID granting full damage bonus. Requires creature trait lookups (dc-cr-ancestry-traits) and a per-target condition timer. Depends on dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-ancestry-traits.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
