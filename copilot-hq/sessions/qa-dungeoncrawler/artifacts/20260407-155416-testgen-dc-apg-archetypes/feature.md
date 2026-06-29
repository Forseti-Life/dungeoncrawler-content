# Feature Brief: APG Archetypes System

- Work item id: dc-apg-archetypes
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Advanced Player's Guide, Chapter 3
- Category: game-mechanic
- Created: 2026-04-07
- DB sections: apg/ch03/Archetype System Rules (General), apg/ch03/Acrobat, apg/ch03/Archaeologist, apg/ch03/Archer, apg/ch03/Assassin, apg/ch03/Bastion, apg/ch03/Beastmaster, apg/ch03/Blessed One, apg/ch03/Bounty Hunter, apg/ch03/Cavalier, apg/ch03/Celebrity, apg/ch03/Dandy, apg/ch03/Dragon Disciple, apg/ch03/Dual-Weapon Warrior, apg/ch03/Duelist, apg/ch03/Eldritch Archer, apg/ch03/Familiar Master, apg/ch03/Gladiator, apg/ch03/Herbalist, apg/ch03/Horizon Walker, apg/ch03/Linguist, apg/ch03/Loremaster, apg/ch03/Marshal, apg/ch03/Martial Artist, apg/ch03/Mauler, apg/ch03/Medic, apg/ch03/Pirate, apg/ch03/Poisoner, apg/ch03/Ritualist, apg/ch03/Scout, apg/ch03/Scroll Trickster, apg/ch03/Scrounger, apg/ch03/Sentinel, apg/ch03/Shadowdancer, apg/ch03/Snarecrafter, apg/ch03/Talisman Dabbler, apg/ch03/Vigilante, apg/ch03/Viking, apg/ch03/Weapon Improviser
- Depends on: dc-cr-multiclass-archetype, dc-cr-character-class, dc-cr-character-leveling

## Description
Implement the 26 APG archetypes (Acrobat, Archaeologist, Archer, Assassin, Bastion, Beastmaster, Blessed One, Bounty Hunter, Cavalier, Celebrity, Dandy, Dragon Disciple, Dual-Weapon Warrior, Duelist, Eldritch Archer, Familiar Master, Gladiator, Herbalist, Horizon Walker, Linguist, Loremaster, Marshal, Martial Artist, Mauler, Medic, Pirate, Poisoner, Ritualist, Scout, Scroll Trickster, Scrounger, Sentinel, Shadowdancer, Snarecrafter, Talisman Dabbler, Vigilante, Viking, Weapon Improviser). Each archetype = a set of archetype feats that any class can take. Covers apg/ch03. Depends on dc-cr-multiclass-archetype system.

## Security acceptance criteria

- Security AC exemption: game-mechanic character data logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
