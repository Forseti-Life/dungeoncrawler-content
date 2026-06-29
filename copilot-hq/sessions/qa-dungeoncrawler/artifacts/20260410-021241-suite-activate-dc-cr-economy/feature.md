# Feature Brief: Economy, Services, and Currency

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: ready
- Release: none
- Dependencies: dc-cr-equipment-system, dc-cr-character-creation

## Goal

Implement the PF2E currency and Bulk encumbrance system — GP/SP/CP exchange rates, the Bulk item weight system, encumbrance thresholds, and starting gold by class — as the foundational economic layer for all item and equipment features.

## Source reference

> "Bulk measures how much gear you're carrying. You can carry Bulk equal to 5 + your Strength modifier before becoming Encumbered; you are over your limit at 10 + your Strength modifier."

## Implementation hint

Model currency as a single integer `copper_pieces` on the character record; expose GP/SP/CP as computed display fields (1 GP = 10 SP = 100 CP). The Bulk system uses a `BulkCalculator` that sums item bulk values: L items count as 10 per full Bulk, negligible (`—`) items don't count; the carrying limit is `(5 + str_mod) × 10` in tenth-Bulk units. Apply the `Encumbered` condition automatically when bulk exceeds the limit; apply `Over Limit` when bulk exceeds `(10 + str_mod)`. Starting gold is a static table keyed by class, applied once at character creation.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; currency changes (buy/sell/loot) must be server-authoritative; negative balance prevented server-side.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Currency transaction amounts must be positive integers; item bulk values validated against item catalog; Strength modifier derived from character attributes, not client input.
- PII/logging constraints: no PII logged; log character_id, transaction_type, amount_cp, source_entity_id; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
