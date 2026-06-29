# Feature Brief: Ancestry System

- Work item id: dc-cr-ancestry-system
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P0 (required dependency for character creation; enables ancestry feat trees and heritage selection downstream)
- Release: 20260319-dungeoncrawler-release-b
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: game-mechanic
- Created: 2026-02-26

## Goal

Allow players to select their character's ancestry (dwarf, elf, gnome, goblin, halfling, or human) during character creation, with each ancestry granting distinct ability boosts, hit points, size, speed, senses, and ancestry feats. This is a foundational character-building component that unlocks downstream features including heritage selection and ancestry feat trees.

## Source reference

> "Choose from ancestries like elf, human, and goblin and classes like alchemist, fighter, and sorcerer to create a hero of your own design, destined to become a legend!"
> "Choose the people your character belongs to—whether they're dwarves, elves, gnomes, goblins, halflings, or humans—then pick a background that fleshes out what your character did before becoming an adventurer."

## Implementation hint

Content type: `ancestry` with fields for hit points, size, speed, ability boosts/flaws, languages, senses, and a reference to available ancestry feats. Character creation API endpoint must accept ancestry selection and apply stat modifiers. Initial scope: six core ancestries.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
