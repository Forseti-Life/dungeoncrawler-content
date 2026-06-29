# Feature Brief: Crafting System

- Work item id: dc-cr-crafting
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: planned
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-downtime-mode
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Source reference

> "Award treasure, from magic weapons to alchemical compounds and transforming statues. The rules for activating and wearing alchemical and magical items are found here as well." (Chapter 11: Crafting & Treasure)

## Implementation hint

Crafting downtime activity (extends dc-cr-downtime-mode): inputs (target item, days allocated, raw material cost spent). Resolution: Crafting skill check vs. item DC; success/failure determines if item is completed or materials partially lost. Alchemist class gets special daily crafting via Infused Reagents (different flow). Requires formula system (item formula as character asset).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Security AC exemption: Crafting modifies gold and inventory; gold deduction and item grant are atomic server operations to prevent duplication.

## Roadmap section

- Roadmap: Core Rulebook
