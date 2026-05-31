# Feature Brief: Dwarf Heritage — Rock Dwarf

- Work item id: dc-cr-dwarf-heritage-rock
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

Implement the Rock Dwarf heritage for the Dwarf ancestry. This heritage provides two anti-displacement passive effects: (1) +2 circumstance bonus to Fortitude or Reflex DC against Shove/Trip attempts and saves against knock-prone effects, and (2) forced movement distance is halved (10+ foot forced moves become half that). These are combat-relevant passives that interact with the grapple/maneuver and forced-movement subsystems.

## Source reference

> "You gain a +2 circumstance bonus to your Fortitude or Reflex DC against attempts to Shove or Trip you. This bonus also applies to saving throws against spells or effects that attempt to knock you prone. In addition, if any effect would force you to move 10 feet or more, you are moved only half the distance."

## Implementation hint

Create a `heritage` entity: `id: rock-dwarf`, `parent_ancestry: dwarf`, `passive_effects: [anti_shove_trip_dc_bonus_2, forced_movement_halved]`. DC bonus: when resolving a Shove or Trip against this character, add +2 to the defender's Fortitude/Reflex DC before comparing. Forced movement: when calculating movement from an effect, check for this heritage and halve any displacement ≥ 10 feet (round down to nearest 5). Depends on dc-cr-dwarf-ancestry, dc-cr-heritage-system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
