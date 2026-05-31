# Feature Brief: Action Economy System

- Work item id: dc-cr-action-economy
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P0 (foundation for all encounter-mode gameplay; all class features, spells, and skill actions depend on this)
- Release: 
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Goal

Implement PF2E's three-action economy: each turn a character gets 3 actions and 1 reaction. Actions, activities (2-action or 3-action), free actions, and reactions each cost a specified number of the turn's resources. This streamlined system replaces PF1e's standard/move/swift/immediate action taxonomy and is the foundation for all encounter-mode gameplay.

## Source reference

> "Read up on comprehensive rules for playing the game, using actions, and calculating your statistics." (Chapter 9: Playing the Game)

## Implementation hint

Turn state model: `actions_remaining` (0–3), `reaction_available` (bool), resets each turn. Action content type: fields for action cost (1/2/3/free/reaction), trigger (for reactions/free actions), and effect. Combat engine must consume actions and enforce limits. All class features, spells, and skill actions reference this type.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
