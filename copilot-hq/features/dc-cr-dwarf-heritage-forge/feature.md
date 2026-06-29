# Feature Brief: Dwarf Heritage — Forge Dwarf

- Work item id: dc-cr-dwarf-heritage-forge
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: Depends on dc-cr-dwarf-ancestry (deferred); re-evaluate when dwarf ancestry is activated
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-dwarf-ancestry, dc-cr-heritage-system
- Source: PF2E Core Rulebook (Fourth Printing), lines 5584–5883
- Category: game-mechanic
- Schema changes: no
- Cross-site modules: none
- Created: 2026-04-05

## Goal

Implement the Forge Dwarf heritage for the Dwarf ancestry. This heritage grants fire resistance equal to half the character's level (minimum 1) and downgrades environmental heat severity by one step. The resistance is a level-scaling passive; the heat downgrade is an environmental interaction relevant to exploration mode and hazard rules.

## Source reference

> "You have a remarkable adaptation to hot environments from ancestors who inhabited blazing deserts or volcanic chambers beneath the earth. This grants you fire resistance equal to half your level (minimum 1), and you treat environmental heat effects as if they were one step less extreme (incredible heat becomes extreme, extreme heat becomes severe, and so on)."

## Implementation hint

Create a `heritage` entity: `id: forge-dwarf`, `parent_ancestry: dwarf`, `passive_effects: [fire_resistance_half_level, heat_severity_downgrade_1_step]`. Fire resistance: `damage_type: fire, resistance_value: max(1, floor(character.level / 2))` — recomputed on level-up. Heat severity mapping: `incredible → extreme → severe → moderate → mild`; the character's effective heat is one step left. Requires the environmental-hazards subsystem (dc-cr-exploration-hazards, if implemented) to apply the step reduction. Depends on dc-cr-dwarf-ancestry, dc-cr-heritage-system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
