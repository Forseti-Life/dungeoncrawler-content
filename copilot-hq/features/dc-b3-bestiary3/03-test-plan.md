# Test Plan: dc-b3-bestiary3

## Coverage summary
- AC items: 13 (Bestiary 3 import, source filtering, extraplanar trait handling, GM-only mutation, idempotent re-import)
- Test cases: 7 (TC-B3-01-07)
- Suites: Playwright encounter/browser smoke coverage + phpunit import/validation coverage
- Security: GM-only import/override paths; player read-only creature-library access

---

## Scope
- In scope: Bestiary 3 creature import flow, `source=b3` catalog reads, GM-only import/override access, planar/dimensional trait handling, idempotent re-import behavior
- Out of scope: unrelated non-creature content sources, UI polish outside creature-library surfaces, release-stage activation into the live suite manifest

## Test Matrix
- Browsers/devices (if UI): Chromium via Playwright for creature-library browser flows
- Roles/permissions: anonymous user, authenticated non-GM user, GM/admin user with `administer dungeoncrawler content`
- Environments: Dungeoncrawler QA/local environment with packaged Bestiary 3 JSON fixtures available

## Central automated test-case suite (SoT)
- Overlay manifest path: `qa-suites/products/dungeoncrawler/features/dc-b3-bestiary3.json`
- Live release manifest path: `qa-suites/products/dungeoncrawler/suite.json`
- How to run (commands):
  - `python3 scripts/qa-suite-validate.py --product dungeoncrawler --feature-id dc-b3-bestiary3`
  - `npx playwright test --grep "dc-b3-bestiary3"`
- Reporting (where PASS/FAIL is recorded): Playwright HTML report plus QA outbox completion note for the grooming handoff

## Feature suite overlay requirements
- Overlay file: `qa-suites/products/dungeoncrawler/features/dc-b3-bestiary3.json`
- Each suite entry declares `owner_seat`, `source_path`, `env_requirements`, and `release_checkpoint`

## Standard source locations
- Unit tests: `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/ContentRegistryTest.php`
- Functional tests: `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/tests/src/Functional/CreatureCatalogControllerTest.php`
- E2E tests: Playwright Dungeoncrawler encounter/browser coverage keyed to `dc-b3-bestiary3`
- Audit/static checks: release-scoped site audit remains the release gate, but this grooming plan targets feature-specific automation

## Automated Tests
- Existing suites to run: Dungeoncrawler Playwright/browser coverage and phpunit content-registry/controller coverage
- New tests expected (if any): extend the creature import/controller coverage to exercise `source=b3`, Bestiary 3 re-import, and planar/dimensional trait persistence

## TC-B3-01 - Bestiary 3 import populates the shared creature schema
- Description: Import a representative Bestiary 3 data set through the packaged JSON path.
- Suite: `dc-b3-bestiary3-e2e`
- Expected: records persist through the existing creature schema with level, rarity, traits, defenses, abilities, and source metadata mapped correctly.
- AC: Happy Path 1-2

## TC-B3-02 - `source=b3` catalog reads work through the existing API
- Description: Query the creature catalog using `GET /api/creatures?source=b3` and fetch an individual Bestiary 3 creature.
- Suite: `dc-b3-bestiary3-e2e`
- Expected: list/get responses match the shared B1/B2 shape and only Bestiary 3 records are returned for the source filter.
- AC: Happy Path 3, Permissions / Access Control 1

## TC-B3-03 - Extraplanar and dimensional traits survive validation
- Description: Import creatures that include planar/dimensional trait data not common in B1/B2.
- Suite: `dc-b3-bestiary3-e2e`
- Expected: supported traits persist cleanly; any required narrow schema extension is validated without breaking prior creature records.
- AC: Edge Cases 2, Data Integrity 1

## TC-B3-04 - Re-import is deterministic
- Description: Re-run the same Bestiary 3 import payload after the initial import.
- Suite: `dc-b3-bestiary3-e2e`
- Expected: matching rows update in place, duplicate registry entries are not created, and prior B1/B2 records remain intact.
- AC: Happy Path 2, Edge Cases 3, Data Integrity 1

## TC-B3-05 - Rare and unique creatures preserve metadata
- Description: Import rare and unique Bestiary 3 creatures and inspect API output.
- Suite: `dc-b3-bestiary3-e2e`
- Expected: rarity, trait, and source metadata remain accurate in both storage and read responses.
- AC: Edge Cases 1

## TC-B3-06 - GM-only mutation remains enforced
- Description: Exercise Bestiary 3 import/override routes as anonymous, authenticated non-GM, and GM/admin users.
- Suite: `dc-b3-bestiary3-e2e`
- Expected: read endpoints remain public, non-GM mutation attempts return 403, and GM/admin mutation succeeds through the existing `_campaign_gm_access` + CSRF path.
- AC: Happy Path 4, Permissions / Access Control 1-3

## TC-B3-07 - Invalid payloads fail explicitly
- Description: Submit malformed Bestiary 3 JSON or unsupported trait data to the import path.
- Suite: `dc-b3-bestiary3-e2e`
- Expected: validation errors are explicit, failed items do not partially corrupt registry state, and the operator is not shown a false success.
- AC: Failure Modes 1-2, Data Integrity 2

## Pass/Fail Criteria
- PASS when the overlay validates, the planned suites cover all AC areas above, and Bestiary 3 can be promoted to the ready pool for next Stage 0.
- FAIL if the overlay is invalid, AC coverage is incomplete, or any release-critical scenario above cannot be expressed as automation without a documented PM exception.

## Knowledgebase references
- Related lesson(s) or proposal(s) (or 'none found'): none found

## What I learned (QA)
- Bestiary 3 is primarily a B2 pipeline extension, so the highest-value tests focus on source-specific filtering, idempotent import behavior, and the narrow schema-risk around extraplanar traits.

## What I'd change next time (QA)
- Add a reusable creature-library source-expansion QA checklist so future Bestiary-style imports can be groomed faster with less repeated planning work.
