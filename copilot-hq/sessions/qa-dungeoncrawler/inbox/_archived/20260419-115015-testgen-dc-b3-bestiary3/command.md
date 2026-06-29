# Test Plan Design: dc-b3-bestiary3

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-19T11:50:15+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT edit the live product manifest `qa-suites/products/dungeoncrawler/suite.json` yet.
Instead, create a **feature-scoped suite overlay** at:
`qa-suites/products/dungeoncrawler/features/dc-b3-bestiary3.json`

That overlay is the runnable SoT for this feature during grooming. The live release manifest is compiled from selected overlays at Stage 0.

### Required outputs

1. **Create** `features/dc-b3-bestiary3/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Create** `qa-suites/products/dungeoncrawler/features/dc-b3-bestiary3.json` from `templates/qa-feature-suite.json`:
   - Declare at least one runnable suite entry for this feature
   - Include `owner_seat`, `source_path`, `env_requirements`, and `release_checkpoint`
   - Point `test_plan` at `features/dc-b3-bestiary3/03-test-plan.md`
   - Validate with:
     ```bash
     python3 scripts/qa-suite-validate.py --product dungeoncrawler --feature-id dc-b3-bestiary3
     ```
2. **Signal completion:**
    ```bash
    ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-b3-bestiary3 "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those release-scope changes happen at Stage 0 of the next release when this feature is selected into scope.
During grooming, keep all feature-specific runnable metadata in the overlay manifest.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan + overlay during grooming, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
