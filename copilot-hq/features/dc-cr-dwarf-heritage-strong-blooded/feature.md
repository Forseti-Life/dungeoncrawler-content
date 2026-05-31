# Feature Brief: Dwarf Heritage — Strong-Blooded Dwarf

- Work item id: dc-cr-dwarf-heritage-strong-blooded
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

Implement the Strong-Blooded Dwarf heritage for the Dwarf ancestry. This heritage grants poison resistance equal to half level (minimum 1) and accelerates poison affliction stage reduction on saves: success reduces stage by 2 (or 1 for virulent), critical success by 3 (or 2 for virulent). This requires the affliction/poison progression system to be aware of this heritage when resolving periodic saves.

## Source reference

> "You gain poison resistance equal to half your level (minimum 1), and each of your successful saving throws against a poison affliction reduces its stage by 2, or by 1 for a virulent poison. Each critical success against an ongoing poison reduces its stage by 3, or by 2 for a virulent poison."

## Implementation hint

Create a `heritage` entity: `id: strong-blooded-dwarf`, `parent_ancestry: dwarf`, `passive_effects: [poison_resistance_half_level, poison_stage_reduction_bonus]`. Poison resistance: same computation as fire resistance in forge-dwarf. Stage reduction: in the affliction-resolution hook, check if character has this heritage; if so, override default stage-reduction amounts with the heritage values. Virulent poisons are a poison sub-type flag (`virulent: true`). Depends on dc-cr-dwarf-ancestry, dc-cr-heritage-system, and implicitly dc-cr-conditions (affliction/poison stage tracking).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
