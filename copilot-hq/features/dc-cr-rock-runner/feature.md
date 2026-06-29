# Feature Brief: Rock Runner (Dwarf Ancestry Feat)

- Work item id: dc-cr-rock-runner
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: Depends on dc-cr-dwarf-ancestry (deferred); re-evaluate when dwarf ancestry is activated
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-tactical-grid
- Source: PF2E Core Rulebook (Fourth Printing), lines 5584–5883
- Category: game-mechanic
- Schema changes: no
- Cross-site modules: none
- Created: 2026-04-05

## Goal

Implement the Rock Runner level-1 ancestry feat for Dwarf characters. This feat removes the movement penalty from stone/earth rubble terrain, removes the flat-footed condition when balancing on stone/earth narrow surfaces, and upgrades Balance successes on stone/earth to critical successes. It requires the terrain system to be tagged with surface material (stone, earth, other) and the Acrobatics Balance action to check for this feat.

## Source reference

> "You can ignore difficult terrain caused by rubble and uneven ground made of stone and earth. In addition, when you use the Acrobatics skill to Balance on narrow surfaces or uneven ground made of stone or earth, you aren't flat-footed, and when you roll a success at one of these Acrobatics checks, you get a critical success instead."

## Implementation hint

Create an `ancestry_feat` entity: `id: rock-runner`, `level: 1`, `ancestry: dwarf`. Effects: (1) for terrain tagged `difficult_terrain + material:stone_or_earth`, set movement cost to normal for this character; (2) when resolving Acrobatics Balance on a `material:stone_or_earth` surface, suppress flat-footed condition; (3) upgrade Balance success → critical success on those surfaces. The tactical grid system (dc-cr-tactical-grid) must store terrain material tags per square/zone. Depends on dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-tactical-grid.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
