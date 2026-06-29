# Feature Brief: Alchemical Items

- Work item id: dc-cr-alchemical-items
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Defer reason: 20260228-dungeoncrawler-release-next focuses on core MVP (dice, DC, encounter, conditions, character creation, class, background, skill, equipment); this feature is secondary priority and will be re-evaluated next grooming cycle.
- Consolidated into: dc-cr-equipment-ch06 (requirements covered in that feature's acceptance criteria)
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: item
- Created: 2026-02-26

## Goal

Implement the alchemical item catalog: bombs, elixirs, mutagens, poisons, and other alchemical consumables. These are non-magical items with limited uses, a level, activation cost (usually 1 action), and specific effects (damage, healing, buffs). The alchemist class can craft these daily via Infused Reagents. Alchemical items provide accessible power to non-spellcasting builds.

## Source reference

> "Award treasure, from magic weapons to alchemical compounds and transforming statues. The rules for activating and wearing alchemical and magical items are found here as well." (Chapter 11: Crafting & Treasure)

## Implementation hint

Content type: `alchemical_item` with fields for alchemical type (bomb/elixir/mutagen/poison/tool), level, price, bulk, activation cost, effect, and duration. Bombs integrate with the attack roll / encounter system. Alchemist daily infusion: separate flow that grants free alchemical items each day scaled to level. Distinct from magic items (no runes, no invest requirement).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access
