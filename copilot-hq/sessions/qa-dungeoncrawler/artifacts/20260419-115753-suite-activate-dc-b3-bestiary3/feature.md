# Feature Brief: Bestiary 3 (deferred)

- Work item id: dc-b3-bestiary3
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: ready
- Release: (set by PM at activation)
- Feature type: enhancement
- Priority: P3
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Bestiary 3
- Category: creature-library
- Created: 2026-04-07
- DB sections: b3/s01/Baseline Requirements, b3/s01/Integration Notes, b3/s02/Baseline Requirements, b3/s02/Integration Notes, b3/s03/Baseline Requirements, b3/s03/Integration Notes
- Depends on: dc-cr-encounter-rules, dc-cr-npc-system, dc-b1-bestiary1

## Goal

Implement the Bestiary 3 creature library expansion: additional creature stat blocks including extraplanar entities, mythic creatures, and rare/unique monster types. Extends the `creature` content type with ~300+ additional entries. Covers 18 requirements across `b3/s01–s03`.

## Source reference

> "Bestiary 3 introduces hundreds of new monsters, including powerful extraplanar beings, unusual creature variants, and expanded creature families." (Bestiary 3 — Introduction)

## Implementation hint

Extension of `dc-b1-bestiary1` schema — no new content type fields needed; this feature is a data load. Requires both Bestiary 1 (schema) and Bestiary 2 (data pipeline pattern) to be complete. Bestiary 3 introduces some extraplanar mechanics (planar traits, dimensional traits) that may require minor schema extensions — identify during import scaffolding. Import pipeline: drush batch import matching B1/B2 pattern. DB sections contain baseline data-model and integration-note placeholders.

## Acceptance Criteria

See `features/dc-b3-bestiary3/01-acceptance-criteria.md`.

## Gap Analysis

- **Import pipeline coverage: Partial** - `ContentRegistry::importContentFromJson()` and `dc:import-creatures` already support recursive JSON import for creature content, and Bestiary 2 established the import/update path. Bestiary 3 can extend this pipeline rather than introducing a new loader.
- **Creature API coverage: Full for read / Partial for new source filters** - `CreatureCatalogController` already supports public list/get endpoints and source-based filtering (`?source=b1|b2`). Bestiary 3 needs the same read path extended to accept `b3` content consistently.
- **GM mutation/security coverage: Full** - `CampaignGmAccessCheck` and the GM-only import/override routes already enforce the correct access pattern for creature-library mutation. Bestiary 3 should reuse the same route protections and sanitization flow.
- **Data model coverage: Partial** - The current creature registry handles tags, rarity, and schema payload storage, but the feature brief calls out possible planar/dimensional trait additions. Dev must confirm whether the existing schema fully represents those traits or needs a narrow extension.
- **Feature type: enhancement** - Bestiary 3 is not greenfield; it extends the shipped Bestiary 2 import/catalog pattern with a larger dataset and a small schema-risk check around extraplanar traits.

### Test path guidance for QA

- `web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/ContentRegistryTest.php` - extend import validation/idempotency coverage for Bestiary 3 creature payloads and planar/dimensional trait fields.
- `web/modules/custom/dungeoncrawler_content/tests/src/Functional/CreatureCatalogControllerTest.php` - extend list/get/import/override coverage for `source=b3`, GM-only mutation, and duplicate import behavior.
- `web/modules/custom/dungeoncrawler_content/tests/src/Functional/ContentSeederCommandsTest.php` - verify `dc:import-creatures` imports Bestiary 3 content idempotently when new JSON files are present.

## Latest updates

- 2026-04-19: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-19: Handed off to QA for test generation (pm-qa-handoff.sh)

- 2026-04-19: Bestiary 2 shipped in `20260412-dungeoncrawler-release-p`, so the explicit deferral gate for Bestiary 3 is now cleared. Feature moved from `deferred` to `planned`, gap analysis completed, and AC handed to QA next.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: creature library is read-only for players; GM-only mutation routes require `_campaign_gm_access: TRUE`
- CSRF expectations: any POST/PATCH creature import or override routes require `_csrf_request_header_mode: TRUE`
- Input validation: stat block fields validated against defined types and ranges; extraplanar trait fields validated against enum; import pipeline sanitizes all text fields
- PII/logging constraints: no PII logged; creature id + encounter id + action type only
