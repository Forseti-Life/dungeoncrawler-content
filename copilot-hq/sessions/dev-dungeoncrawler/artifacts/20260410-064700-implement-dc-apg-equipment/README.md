# Implement: dc-apg-equipment

- Agent: dev-dungeoncrawler
- Release: 20260410-dungeoncrawler-release-c
- Feature: dc-apg-equipment
- Dispatched by: pm-dungeoncrawler

## Task

Implement APG equipment catalog extension per `features/dc-apg-equipment/feature.md`.

Add APG-sourced weapons, armor, alchemical items, and magical items to the equipment catalog using the same `Item`/`WeaponItem`/`ArmorItem` polymorphic schema as CRB equipment. Add a `source_book: apg` discriminator field for filtering. Implement bulk import pipeline from APG equipment JSON mirroring the CRB import. Validate entries against existing item schema before insertion. Ensure character equipment selector and item search API return APG items when `source_book` filter is `all` or `apg`.

## Acceptance criteria

- APG weapons, armor, alchemical items, and magic items are importable and queryable.
- `source_book: apg` discriminator exists and is filterable.
- Character equipment selector returns APG items when filter is `all` or `apg`.
- Admin-write-only access enforced; character equipment changes scoped to owning player or GM.
- All POST/PATCH routes require `_csrf_request_header_mode: TRUE`.
- PHP lint clean; site HTTP 200 after changes.

## Verification

- `GET /api/equipment?source_book=apg` returns APG items.
- `GET /api/equipment?source_book=all` returns both CRB and APG items.
- Import pipeline validates and rejects malformed items.
- Existing CRB equipment catalog unaffected.

## Rollback

Revert import data; no schema changes expected (additive only).
- Status: pending
