# Feature Brief: Equipment and Gear System

- Work item id: dc-cr-equipment-system
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: 20260406-dungeoncrawler-release-b
- Priority: P1 (combat and character creation dependency; InventoryManagementService partial impl exists)
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), lines 1–300
- Category: item
- Created: 2026-02-26

## Goal

Implement the equipment catalog covering weapons, armor, shields, adventuring gear, and other mundane items. Characters select starting equipment during creation and acquire items during play. Equipment stats (damage dice, AC bonus, bulk, price) feed directly into combat calculations and encumbrance tracking.

## Source reference

> "Gear up for adventure with this vast arsenal of armor, weapons, and gear." (Chapter 6: Equipment)

## Implementation hint

Content type: `equipment_item` with fields for item type (weapon/armor/shield/gear), traits[], damage (dice + type), bulk, price (gp/sp/cp), weapon group, armor category, and AC/dex cap (for armor). Character entity has an `inventory[]` relationship and an `equipped[]` subset. Starting equipment rules by class must be encoded or selectable. Separate from magic items.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria
- Authentication/permission surface: authenticated users only; inventory mutation requires character ownership (`_character_access: TRUE`)
- CSRF expectations: all POST/PATCH equipment/inventory routes require `_csrf_request_header_mode: TRUE`
- Input validation: item quantities and bulk values validated against defined limits; item names sanitized at Drupal field layer
- PII/logging constraints: no PII logged; item id + character id + transaction type only
