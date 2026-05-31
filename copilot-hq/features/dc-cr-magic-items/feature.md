# Feature Brief: Magic Items and Treasure

- Work item id: dc-cr-magic-items
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: 20260228-dungeoncrawler-release-next focuses on core MVP (dice, DC, encounter, conditions, character creation, class, background, skill, equipment); this feature is secondary priority and will be re-evaluated next grooming cycle.
- Consolidated into: dc-cr-magic-ch11 (requirements covered in that feature's acceptance criteria)
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: item
- Created: 2026-02-26

## Goal

Implement the magic item catalog including weapons, armor, wondrous items, and held/worn items. Magic items have a level, price, activation method (command word, Cast a Spell, Interact, etc.), and usage (held/worn/invested). Characters may have up to 10 invested items. Magic items are the primary reward of dungeon-crawling and must integrate with the inventory and encounter systems.

## Source reference

> "Award treasure, from magic weapons to alchemical compounds and transforming statues. The rules for activating and wearing alchemical and magical items are found here as well." (Chapter 11: Crafting & Treasure)

## Implementation hint

Content type: `magic_item` extending `equipment_item` with fields for item level, activation (type + action cost + frequency), usage (held-in-X-hand / worn / invested), and bulk. Investment tracking: character entity `invested_count` (max 10). Activation API: deducts charges/uses per frequency, applies effect. Potency runes and property runes are sub-items that modify weapons/armor.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
