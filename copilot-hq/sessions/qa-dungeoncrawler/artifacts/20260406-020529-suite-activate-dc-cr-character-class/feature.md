# Feature Brief: Character Class System

- Work item id: dc-cr-character-class
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: high (core pillar of character building; defines proficiencies, HP/level, class features, and class feats — required for character creation workflow)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: game-mechanic
- Created: 2026-02-26

## Goal

Provide a selection of 12 character classes (including fighter, cleric, wizard, and alchemist) that define a character's primary abilities, proficiencies, class features, and advancement table. Each class is a pillar of the character-building experience and drives the dungeoncrawler game's depth and replayability.

## Source reference

> "Bold fighters, devout clerics, scholarly wizards, and inventive alchemists are just a few of the 12 character classes you can select from."
> "Choose from ancestries like elf, human, and goblin and classes like alchemist, fighter, and sorcerer to create a hero of your own design."

## Implementation hint

Content type: `character_class` with fields for key ability, hit points per level, proficiencies (saves, attacks, defenses), class features list, and class feat options per level. Character creation and leveling APIs must reference this type. Core Rulebook provides all 12 classes: alchemist, barbarian, bard, champion, cleric, druid, fighter, monk, ranger, rogue, sorcerer, wizard.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
