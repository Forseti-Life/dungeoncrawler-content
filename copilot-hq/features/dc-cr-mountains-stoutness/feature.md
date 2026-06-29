# Feature Brief: Mountain's Stoutness (Dwarf Ancestry Feat)

- Work item id: dc-cr-mountains-stoutness
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: Depends on dc-cr-dwarf-ancestry (deferred); re-evaluate when dwarf ancestry is activated
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-character-leveling, dc-cr-conditions
- Source: PF2E Core Rulebook (Fourth Printing), lines 5584–5883
- Category: game-mechanic
- Schema changes: no
- Cross-site modules: none
- Created: 2026-04-05

## Goal

Implement the Mountain's Stoutness level-9 ancestry feat for Dwarf characters. This feat adds the character's current level to their maximum Hit Points, and reduces the Recovery Check DC when dying from `10 + dying_value` to `9 + dying_value`. If the character also has the Toughness general feat, the HP bonuses stack and Recovery DC is further reduced to `6 + dying_value`. This is the first instance of stacking-feat HP bonuses and modified dying recovery DCs.

## Source reference

> "Increase your maximum Hit Points by your level. When you have the dying condition, the DC of your recovery checks is equal to 9 + your dying value (instead of 10 + your dying value). If you also have the Toughness feat, the Hit Points gained from it and this feat are cumulative, and the DC of your recovery checks is equal to 6 + your dying value."

## Implementation hint

Create an `ancestry_feat` entity: `id: mountains-stoutness`, `level: 9`, `ancestry: dwarf`. HP calculation hook: add `character.level` to max HP when this feat is present (recomputed on level-up). Recovery DC hook: override base Recovery Check DC from `10 + dying` to `9 + dying`. Toughness combo check: if character also has the `toughness` feat, set Recovery DC to `6 + dying` (this feat owns the combo outcome). The dying/recovery system (dc-cr-conditions) must expose an overrideable Recovery DC formula hook. Depends on dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-character-leveling, dc-cr-conditions.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
