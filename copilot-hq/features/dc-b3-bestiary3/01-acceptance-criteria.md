# Acceptance Criteria - dc-b3-bestiary3

- Feature: Bestiary 3 creature-library expansion
- Release target: next available dungeoncrawler cycle after grooming completes
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-19
- Priority: P3

## Gap analysis reference

Feature type: `enhancement` - the B2 import/catalog path already exists. All criteria below extend that pipeline for Bestiary 3 content, with one schema-check branch for extraplanar and dimensional traits.

## Knowledgebase check
- Related lessons/playbooks (or 'none found'): none found

## Happy Path

- [ ] `[EXTEND]` Packaged Bestiary 3 creature JSON files live under the Dungeoncrawler content tree and import through the existing `ContentRegistry::importContentFromJson()` pipeline without creating a separate loader path.
- [ ] `[EXTEND]` `dc:import-creatures` imports Bestiary 3 records idempotently and reports created/updated counts without duplicate registry rows on re-run.
- [ ] `[EXTEND]` `GET /api/creatures?source=b3` returns Bestiary 3 creatures with the same list/get shape already used for Bestiary 1 and Bestiary 2.
- [ ] `[EXTEND]` GM-only creature import/override routes accept Bestiary 3 stat blocks through the existing `_campaign_gm_access` + CSRF-guarded mutation flow.

## Edge Cases

- [ ] `[EXTEND]` Rare and unique Bestiary 3 creatures preserve rarity, traits, and source metadata correctly in the registry and API output.
- [ ] `[EXTEND]` If Bestiary 3 introduces planar or dimensional traits not represented in the current schema, the implementation extends validation/storage narrowly without breaking existing B1/B2 creature records.
- [ ] `[EXTEND]` Re-importing the same Bestiary 3 file set updates existing rows rather than creating duplicates or dropping prior creature families.

## Failure Modes

- [ ] `[EXTEND]` Invalid or malformed Bestiary 3 JSON files are rejected with explicit import errors and do not partially corrupt existing registry rows.
- [ ] `[EXTEND]` Unsupported trait values or missing required fields are surfaced as validation failures, not silent skips that appear successful to the operator.

## Permissions / Access Control

- [ ] Anonymous user behavior: `GET /api/creatures?source=b3` and `GET /api/creatures/{id}` remain readable without authentication.
- [ ] Authenticated user behavior: non-GM authenticated users can read Bestiary 3 catalog content but cannot call GM import/override mutation routes.
- [ ] Admin behavior: users with `administer dungeoncrawler content` can import and override Bestiary 3 records through the existing GM mutation routes.

## Data Integrity

- [ ] Bestiary 3 imports are idempotent and do not regress existing Bestiary 1/2 records in `dungeoncrawler_content_registry`.
- [ ] Rollback path identified: remove or revert the Bestiary 3 content/validation changes and rerun import without requiring a destructive DB restore.
