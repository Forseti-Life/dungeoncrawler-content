# Feature Brief: APG Equipment, Magic Items, and Alchemical Items

- Work item id: dc-apg-equipment
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: shipped
- Release: 20260410-dungeoncrawler-release-c
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: apg/ch05, apg/ch06
- Category: equipment
- Created: 2026-04-06
- DB sections: apg/ch05/Alchemical Items, apg/ch05/Consumable Magic Items, apg/ch05/New Weapons and Adventuring Gear, apg/ch05/Permanent Magic Items, apg/ch05/Snares, apg/ch06/Alchemical Items, apg/ch06/Consumable Magic Items, apg/ch06/New Weapons and Adventuring Gear, apg/ch06/Permanent Magic Items, apg/ch06/Snares
- Depends on: dc-cr-equipment-system, dc-cr-magic-items, dc-cr-alchemical-items

## Goal

Extend the equipment catalog with APG-sourced weapons, armor, alchemical items, and magical items — using the same item schema as dc-cr-equipment-ch06 — adding APG entries to the character equipment selection without disrupting CRB catalog functionality.

## Source reference

> "The Advanced Player's Guide adds new equipment options including weapons, alchemical items, and magic items that expand character options."

## Implementation hint

APG equipment uses the same `Item`/`WeaponItem`/`ArmorItem` polymorphic schema as CRB equipment; add a `source_book: apg` discriminator field for filtering. Implement a bulk import pipeline from APG equipment JSON data mirroring the CRB import; validate each entry against the existing item schema before insertion. New alchemical items follow the `AlchemicalItem` entity type already established for the Alchemist class. Ensure the character equipment selector and item search API correctly return APG items when `source_book` filter is set to `all` or `apg`.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Equipment catalog is admin-write only; character equipment changes scoped to owning player or GM.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Source book field must be [crb, apg, gmg, etc.]; item schema validation on import; all APG-specific traits must be registered in TraitRegistry before import.
- PII/logging constraints: no PII logged; log import_batch_id, items_imported, validation_errors[]; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
