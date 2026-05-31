# Feature Brief: Dwarven Weapon Familiarity (Ancestry Feat)

- Work item id: dc-cr-dwarven-weapon-familiarity
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: Depends on dc-cr-dwarf-ancestry (deferred); re-evaluate when dwarf ancestry is activated
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-equipment-system
- Source: PF2E Core Rulebook (Fourth Printing), lines 5584–5883
- Category: game-mechanic
- Schema changes: no
- Cross-site modules: none
- Created: 2026-04-05

## Goal

Implement the Dwarven Weapon Familiarity level-1 ancestry feat for Dwarf characters. When selected, this feat grants trained proficiency with the battle axe, pick, and warhammer, and access to all uncommon dwarf weapons. It also reclassifies martial dwarf weapons as simple and advanced dwarf weapons as martial for the purposes of this character's proficiency calculation. This establishes the pattern for ancestry-specific weapon proficiency overrides.

## Source reference

> "Your kin have instilled in you an affinity for hard-hitting weapons, and you prefer these to more elegant arms. You are trained with the battle axe, pick, and warhammer. You also gain access to all uncommon dwarf weapons. For the purpose of determining your proficiency, martial dwarf weapons are simple weapons and advanced dwarf weapons are martial weapons."

## Implementation hint

Create an `ancestry_feat` entity: `id: dwarven-weapon-familiarity`, `level: 1`, `ancestry: dwarf`. Effects: (1) set proficiency to `trained` for `battle-axe`, `pick`, `warhammer` if not already higher; (2) grant access to uncommon dwarf-trait weapons; (3) add a proficiency reclassification rule: when computing proficiency for a dwarf-trait weapon, downgrade its category by one tier for this character. The equipment system must support the `dwarf` weapon trait and `uncommon` access gating (dc-cr-equipment-system dependency). Depends on dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-equipment-system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
