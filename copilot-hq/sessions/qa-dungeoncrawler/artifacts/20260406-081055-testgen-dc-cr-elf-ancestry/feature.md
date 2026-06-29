# Feature Brief: Elf Ancestry

- Work item id: dc-cr-elf-ancestry
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-ancestry-system, dc-cr-heritage-system, dc-cr-low-light-vision, dc-cr-languages
- Source: PF2E Core Rulebook (Fourth Printing), lines 5896–6145
- Category: game-mechanic
- Schema changes: no
- Cross-site modules: none
- Release: (set by PM at activation)
- Created: 2026-04-06

## Goal

Implement the Elf ancestry stat block: HP 6, Speed 30 ft, Medium size, ability boosts to Dexterity and Intelligence plus one free boost, Constitution flaw, Low-Light Vision, languages Common and Elven plus additional languages equal to Intelligence modifier (from a defined list). This unlocks the elf character archetype with its characteristic glass-cannon stat profile and extended language access.

## Source reference

> Elf — Hit Points: 6. Size: Medium. Speed: 30 feet. Ability Boosts: Dexterity, Intelligence, Free. Ability Flaw: Constitution. Languages: Common, Elven; additional languages equal to Intelligence modifier (if positive) from: Celestial, Draconic, Gnoll, Gnomish, Goblin, Orcish, Sylvan. Traits: Elf, Humanoid. Special: Low-Light Vision.

## Implementation hint

Create the elf ancestry record in dungeoncrawler_content using the same ancestry data model as dwarves. Key differences from dwarf: HP 6 (vs. 10), Speed 30 (vs. 20 for dwarf), Con flaw (no Cha flaw), Low-Light Vision (not darkvision), broader free-language list. Language system must support the Int-modifier-scaled additional language slots — this may require a small extension to the languages system if not already parameterized.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- All write endpoints (POST/PATCH heritage/ancestry assignment) require `_csrf_request_header_mode: TRUE`.
- All read endpoints (GET sense flags, ancestry data) use `_csrf_token: FALSE`.
- Anonymous users receive 403 on all character write paths.
- Character data is scoped to the owning user's session; no cross-character data exposure.
