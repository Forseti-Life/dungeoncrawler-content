# Feature Brief: Crafting System

- Work item id: dc-cr-crafting
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260410-dungeoncrawler-release-b
20260410-dungeoncrawler-release-b
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Depends on: dc-cr-downtime-mode
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: rule-system
- Created: 2026-02-26

## Goal

Implement the Crafting downtime activity that lets characters spend time and gold to create equipment, alchemical items, and magical gear. Covers the full resolution loop: formula lookup, skill check vs item DC, days-to-complete calculation, material cost deduction, and item grant on success or partial loss on failure.

## Source reference

> "Award treasure, from magic weapons to alchemical compounds and transforming statues. The rules for activating and wearing alchemical and magical items are found here as well." (Chapter 11: Crafting & Treasure)

## Implementation hint

Crafting downtime activity (extends dc-cr-downtime-mode): inputs (target item, days allocated, raw material cost spent). Resolution: Crafting skill check vs. item DC; success/failure determines if item is completed or materials partially lost. Alchemist class gets special daily crafting via Infused Reagents (different flow). Requires formula system (item formula as character asset).

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: authenticated users only; crafting actions require character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH crafting routes require `_csrf_request_header_mode: TRUE`
- Input validation: item ID validated against formula book; gold deduction and item grant are atomic server operations to prevent duplication
- PII/logging constraints: no PII logged; character id + item id + gold delta + outcome only

## Roadmap section

- Roadmap: Core Rulebook
