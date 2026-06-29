# Feature Brief: Core Book Chapter 6 — Complete Equipment Rules

- Work item id: dc-cr-equipment-ch06
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch06
- Category: equipment
- Created: 2026-04-07
- DB sections: core/ch06/Adventuring Gear, core/ch06/Alchemical Gear (1st-Level Access), core/ch06/Animals and Mounts, core/ch06/Armor, core/ch06/Carrying and Item Rules, core/ch06/Class Starting Kits (Reference), core/ch06/Currency and Economy, core/ch06/Formulas, core/ch06/Magical Gear (1st-Level Access), core/ch06/Services and Economy, core/ch06/Shields, core/ch06/Weapons
- Depends on: dc-cr-equipment-system

## Goal

Implement the core equipment catalog — weapons, armor, shields, and general items — with full PF2E item data model including price, bulk, damage dice, AC bonuses, traits, and rarity, serving as the foundational item database for all character and encounter features.

## Source reference

> "Equipment in Pathfinder has a Price in gold pieces, a Bulk value, and in the case of weapons a damage die and damage type; armor adds an AC bonus with Dexterity cap, check penalty, and speed penalty."

## Implementation hint

Define an `Item` base entity with fields: id, name, price_cp, bulk, rarity, traits[], source_book, level. Extend with `WeaponItem` (damage_dice, damage_type, weapon_group, range, reload, hands) and `ArmorItem` (ac_bonus, dex_cap, check_penalty, speed_penalty, str_requirement, armor_group) and `ShieldItem` (hardness, hp, bt). Use a discriminator column for polymorphic queries. Traits are stored as a normalized `ItemTrait` table with a many-to-many join; validate trait values against a `TraitRegistry`. Implement a bulk import pipeline for the ~500 CRB equipment entries using a structured JSON source.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Equipment catalog is read-only for players; GM/admin-only write access for catalog management; character equipment changes scoped to owning player.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Item IDs validated against catalog on equip; price validated as positive integer; trait values validated against TraitRegistry enum.
- PII/logging constraints: no PII logged; log character_id, item_id, action (equip/unequip/purchase); no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
