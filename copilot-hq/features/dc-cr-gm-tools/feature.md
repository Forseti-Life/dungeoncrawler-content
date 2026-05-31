# Feature Brief: GM Tools and Adventure Preparation

- Work item id: dc-cr-gm-tools
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: 20260228-dungeoncrawler-release-next focuses on core MVP (dice, DC, encounter, conditions, character creation, class, background, skill, equipment); this feature is secondary priority and will be re-evaluated next grooming cycle.
- Consolidated into: dc-gmg-running-guide (requirements covered in that feature's acceptance criteria)
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Goal

Implement GM-facing tools for adventure/session preparation: encounter budgeting by threat level (Trivial/Low/Moderate/Severe/Extreme), environment/terrain rules, NPC stat blocks, and loot generation by level. These tools are used by the dungeoncrawler AI GM to generate balanced, appropriate encounters and rewards without requiring manual math from the human player or GM.

## Source reference

> "Prepare and run your games with the advice in this section. Rules for setting Difficulty Classes, granting rewards, environments, and hazards can also be found here." (Chapter 10: Game Mastering)

## Implementation hint

Encounter budget table: XP budget per threat level by party size/level. Creature XP values by level delta vs. party. NPC content type with abbreviated stat block fields. AI GM prompt integration: pass encounter budget + current dungeon level to generate appropriate encounter. Loot-by-level table for GP value of treasure awards. Separate GM-only API endpoint from player-facing endpoints.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
