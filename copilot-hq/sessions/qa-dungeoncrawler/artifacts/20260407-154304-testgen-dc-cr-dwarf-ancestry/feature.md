# Feature Brief: Dwarf Ancestry

- Work item id: dc-cr-dwarf-ancestry
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2 (extends ancestry-system with specific stat block; all deps now satisfied — ancestry-system ✓, clan-dagger shipped ✓, heritage-system ✓)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 5284–5583
- Category: game-mechanic
- Created: 2026-02-28
- Deferred decision: 2026-03-22 (pm-dungeoncrawler) — defer until dc-cr-heritage-system ships; CharacterManager::ANCESTRIES data already covers the dwarf data layer. Re-evaluate at that release's grooming cycle.

## Goal

Implement the Dwarf as a fully playable ancestry in dungeoncrawler. Dwarves have HP 10, Medium size, Speed 20 feet, ability boosts to Constitution and Wisdom (plus one free boost), an ability flaw to Charisma, traits Dwarf and Humanoid, and starting languages Common and Dwarven (plus bonus languages based on Intelligence modifier). Every dwarf receives a free Clan Dagger at character creation. The Dwarf ancestry is one of six core ancestries required for the initial character creation system.

## Source reference

> "Hit Points: 10 | Size: Medium | Speed: 20 feet | Ability Boosts: Constitution, Wisdom, Free | Ability Flaw: Charisma | Languages: Common, Dwarven (additional languages equal to Intelligence modifier from list: Gnomish, Goblin, Jotun, Orcish, Terran, Undercommon) | Traits: Dwarf, Humanoid"
> "You get one clan dagger for free, as it was given to you at birth. Selling this clan dagger is a terrible taboo."

## Implementation hint

Create a `ancestry` content entity for Dwarf with: `hp_grant: 10`, `size: medium`, `speed: 20`, `ability_boosts: [constitution, wisdom, free]`, `ability_flaw: charisma`, `default_languages: [common, dwarven]`, `bonus_language_pool: [gnomish, goblin, jotun, orcish, terran, undercommon]`, `bonus_language_source: intelligence_modifier`, `traits: [dwarf, humanoid]`, `starting_equipment: [clan_dagger]`. Character creation API must apply these values when ancestry = dwarf. Depends on dc-cr-ancestry-system, dc-cr-clan-dagger, dc-cr-heritage-system.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
