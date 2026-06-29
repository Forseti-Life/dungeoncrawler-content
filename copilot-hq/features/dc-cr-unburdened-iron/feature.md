# Feature Brief: Unburdened Iron (Dwarf Ancestry Feat)

- Work item id: dc-cr-unburdened-iron
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: Depends on dc-cr-dwarf-ancestry (deferred); re-evaluate when dwarf ancestry is activated
- Merged into: dc-cr-dwarf-ancestry (all heritages and ancestry feats covered in bulk AC)
- Priority: P3
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

Implement the Unburdened Iron level-1 ancestry feat for Dwarf characters. This feat eliminates the Speed penalty from worn armor entirely, and reduces any other Speed penalties (e.g., encumbered condition, spells) by 5 feet — but only the largest single other penalty is reduced, not each penalty independently. This interacts with the character's speed calculation in the movement system.

## Source reference

> "Ignore the reduction to your Speed from any armor you wear. In addition, any time you're taking a penalty to your Speed from some other reason (such as from the encumbered condition or from a spell), deduct 5 feet from the penalty. If your Speed is taking multiple penalties, pick only one penalty to reduce."

## Implementation hint

Create an `ancestry_feat` entity: `id: unburdened-iron`, `level: 1`, `ancestry: dwarf`. Speed calculation hook: (1) zero out the armor Speed penalty for this character; (2) when aggregating non-armor Speed penalties, find the single largest penalty and subtract 5 (minimum 0 reduction). The equipment system must expose armor Speed penalty as a separate value from other Speed modifiers. Depends on dc-cr-dwarf-ancestry, dc-cr-ancestry-feat-schedule, dc-cr-equipment-system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
