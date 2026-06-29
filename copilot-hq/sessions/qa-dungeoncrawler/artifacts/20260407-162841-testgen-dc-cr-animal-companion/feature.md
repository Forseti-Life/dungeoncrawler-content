# Feature Brief: Animal Companion System

- Work item id: dc-cr-animal-companion
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: game-mechanic
- Created: 2026-02-26

## Goal

Allow certain classes (druid, ranger, etc.) to have an animal companion that acts as a second combatant and companion entity. Animal companions have their own stat block, proficiencies, and advancement track separate from the player character. They add tactical depth and a companion-bond narrative element to the game.

## Source reference

> "This chapter also details animal companions, familiars, and multiclass archetypes that expand your character's abilities." (Chapter 3: Classes)

## Implementation hint

Content type: `animal_companion` with fields for companion type (bear, horse, wolf, etc.), base stats, attack actions, and advancement table. Link to owning character entity via relationship field. API must support companion actions in the combat/encounter system. Separate from `familiar` (which uses different rules).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Security AC exemption: Companion data is character-scoped; stat advancement is server-validated; no PII.

## Roadmap section

- Roadmap: Core Rulebook
