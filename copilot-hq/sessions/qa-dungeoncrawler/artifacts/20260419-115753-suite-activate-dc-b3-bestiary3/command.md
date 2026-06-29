# Suite Activation: dc-b3-bestiary3

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-19T11:57:53+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-b3-bestiary3"`**  
   This links the test to the living requirements doc at `features/dc-b3-bestiary3/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-b3-bestiary3-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-b3-bestiary3",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-b3-bestiary3"`**  
   Example:
   ```json
   {
     "id": "dc-b3-bestiary3-<route-slug>",
     "feature_id": "dc-b3-bestiary3",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-b3-bestiary3",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

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

### Acceptance criteria (reference)

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
- Agent: qa-dungeoncrawler
- Status: pending
