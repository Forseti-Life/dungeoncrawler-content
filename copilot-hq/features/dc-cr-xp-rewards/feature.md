# Feature Brief: XP and Rewards System

- Work item id: dc-cr-xp-rewards
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: 20260228-dungeoncrawler-release-next focuses on core MVP (dice, DC, encounter, conditions, character creation, class, background, skill, equipment); this feature is secondary priority and will be re-evaluated next grooming cycle.
- Consolidated into: dc-cr-xp-award-system (requirements covered in that feature's acceptance criteria)
- Priority: P1
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-character-leveling
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Goal

Implement XP tracking and the reward system: characters earn XP from encounters (by creature level relative to party), hazards, and story milestones. At 1,000 XP accumulated (above prior level threshold), the character levels up. This is the primary progression trigger and must integrate with the character leveling system.

## Source reference

> "Rules for setting Difficulty Classes, granting rewards, environments, and hazards can also be found here." (Chapter 10: Game Mastering)

## Implementation hint

Character entity field: `xp` (integer). Session reward API: GM awards XP to party (per creature CR vs. party level table). XP thresholds: level up at 1,000 XP from start of current level; each level resets the counter. Integration point: reaching 1,000 XP triggers the level-up flow (dc-cr-character-leveling). Separate gold/treasure rewards from XP.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
