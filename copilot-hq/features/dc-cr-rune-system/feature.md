# Feature Brief: Runes, Materials, and Magic Items

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: done
- Release:

20260409-dungeoncrawler-release-e

Implement the rune etching and transfer system — fundamental runes (Striking/Resilient tiers) for weapons and armor, property rune slots gated by potency, and the Etch a Rune downtime action — enabling the primary weapon and armor power scaling path.

## Source reference

> "A weapon potency rune adds an item bonus to attack rolls and determines the number of property rune slots; you must have a +1 potency rune before adding any property runes to a weapon."

## Implementation hint

Model runes as child entities of `WeaponItem`/`ArmorItem` with fields: rune_type (fundamental/property), rune_name, tier (1/2/3 for Striking/Resilient). The property rune slot count is derived from the potency rune tier: +1=1 slot, +2=2 slots, +3=3 slots. `EtchaRuneService` validates potency prerequisite, deducts the rune item from inventory (or gp for the rune material cost), applies a Craft check vs rune level DC, and on success attaches the rune entity to the target item. `TransferRuneService` moves a rune from one item to another; requires a Craft check and an empty matching slot on the destination.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; rune attachment/detachment server-authoritative; potency prerequisite enforced before property rune etch.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Rune entity ID must reference a valid rune item in character inventory; target item must be a valid weapon or armor; property rune slot count validated against potency tier server-side.
- PII/logging constraints: no PII logged; log character_id, rune_id, target_item_id, action (etch/transfer), craft_check_result; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
