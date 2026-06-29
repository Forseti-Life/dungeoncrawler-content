# Feature Brief: Character Leveling and Advancement

- Work item id: dc-cr-character-leveling
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: in_progress
- Release: 20260402-dungeoncrawler-release-b
- Defer reason: 20260228-dungeoncrawler-release-next focuses on core MVP (dice, DC, encounter, conditions, character creation, class, background, skill, equipment); this feature is secondary priority and will be re-evaluated next grooming cycle.
- Priority: unset (PM will set at triage)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Goal

Implement the level-up flow that advances a character from their current level to the next (levels 1–20), applying new class features, ability boosts (at levels 5/10/15/20), additional skill increases, and new feats. This is the core progression loop that drives long-term player engagement in the dungeoncrawler game.

## Source reference

> "This section also covers how to build a character, as well as how to level up your character after adventuring."

## Implementation hint

Level-up API endpoint that reads the character's class advancement table and presents available choices (class feat, skill feat, general feat, ability boosts at milestone levels). Must gate on XP threshold or session milestone. Stores incremental changes on the character entity without overwriting prior state.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
