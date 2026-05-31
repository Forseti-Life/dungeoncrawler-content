# Feature Brief: Clan Dagger (Dwarven Starting Equipment)

- Work item id: dc-cr-clan-dagger
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: shipped
- Release: 20260326-dungeoncrawler-release-b
- Dev complete: yes (commits 5bc95ffe4, efc7eef2a — 2026-03-20)
- Priority: P3 (note: dependency on dc-cr-equipment-system and dc-cr-dwarf-ancestry was overridden by dev; all AC verified via drush ev)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 5284–5583
- Category: item
- Created: 2026-02-28

## Goal

Implement the Clan Dagger as a special item unique to Dwarf characters. Every dwarf receives one free Clan Dagger at character creation (it was forged just before their birth and bears the gemstone of their clan). The Clan Dagger has cultural significance: selling it is a severe social/mechanical taboo. This item serves as the prototype for ancestry-granted starting equipment and cultural item rules (items with social consequences for sale or loss).

## Source reference

> "Few dwarves are seen without their clan dagger strapped to their belt. This dagger is forged just before a dwarf's birth and bears the gemstone of their clan. A parent uses this dagger to cut the infant's umbilical cord, making it the first weapon to taste their blood."
> "You get one clan dagger (page 280) for free, as it was given to you at birth. Selling this clan dagger is a terrible taboo."

## Implementation hint

Create a `equipment` content entity: `id: clan-dagger`, `type: weapon (dagger)`, `cost: 0 (granted free)`, `flags: [ancestral, unsellable_strong_taboo]`, `cultural_note: "Selling this is a terrible social taboo among dwarves"`. Character creation for dwarves must auto-add this item to inventory with the `ancestral` flag set. The inventory UI should warn players against selling/discarding it and may impose a social penalty (reputation/alignment consequence) if sold. Stats referenced at page 280 (line range ~40555+ in Equipment chapter); full stats to be added when equipment chapter is scanned. Depends on dc-cr-dwarf-ancestry, dc-cr-equipment-system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
