# Feature Brief: Core Book Chapter 11 — Complete Magic Items and Treasure

- Work item id: dc-cr-magic-ch11
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Release: 20260412-dungeoncrawler-release-d
20260412-dungeoncrawler-release-d
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch11
- Category: equipment
- Created: 2026-04-07
- DB sections: core/ch11/Activating Items, core/ch11/Alchemical Items, core/ch11/Consumables (Magical), core/ch11/Crafting Requirements, core/ch11/Item Rarity, core/ch11/Item Stat Block Format, core/ch11/Item Traits (Notable), core/ch11/Magic Armor, core/ch11/Magic Item Basics, core/ch11/Magic Weapons, core/ch11/Precious Materials, core/ch11/Runes, core/ch11/Shields, core/ch11/Snares, core/ch11/Staves, core/ch11/Wands, core/ch11/Worn Items
- Depends on: dc-cr-magic-items, dc-cr-alchemical-items, dc-cr-rune-system, dc-cr-crafting

## Goal

Implement the magic items system — consumables vs permanent items, the rune system, activation types, charges, and the 10-item investment limit — as the primary vehicle for character power scaling through found and purchased magical gear.

## Source reference

> "Each day during your daily preparations, you can invest up to 10 magic items; investing requires you to interact with the item for 10 minutes, and it remains invested until your next daily preparations."

## Implementation hint

`MagicItem` extends the base `Item` entity with fields: item_category (consumable/permanent), activation_type enum (Cast/Command/Envision/Interact/None), charges (nullable), investment_required boolean. The investment system tracks invested items as a `DailyInvestment` entity with a 10-item cap enforced at daily prep; items not invested do not provide their benefits. Consumables decrement charges on use and are destroyed at 0 charges; validate server-side. Rune data (see dc-cr-rune-system) is stored on weapon/armor as child entities rather than inline fields.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; investment limit enforced server-side; activation consumes charges server-authoritative.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Investment count validated to ≤10 per day; activation type must match the item's defined activation_type; charges cannot be set by client.
- PII/logging constraints: no PII logged; log character_id, item_id, action (invest/activate/consume), charges_remaining; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
