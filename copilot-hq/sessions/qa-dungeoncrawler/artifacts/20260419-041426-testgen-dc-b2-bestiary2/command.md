- Status: done
- Completed: 2026-04-19T04:15:21Z

# Test Plan Design: dc-b2-bestiary2

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-19T04:14:26+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT edit the live product manifest `qa-suites/products/dungeoncrawler/suite.json` yet.
Instead, create a **feature-scoped suite overlay** at:
`qa-suites/products/dungeoncrawler/features/dc-b2-bestiary2.json`

That overlay is the runnable SoT for this feature during grooming. The live release manifest is compiled from selected overlays at Stage 0.

### Required outputs

1. **Create** `features/dc-b2-bestiary2/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Create** `qa-suites/products/dungeoncrawler/features/dc-b2-bestiary2.json` from `templates/qa-feature-suite.json`:
   - Declare at least one runnable suite entry for this feature
   - Include `owner_seat`, `source_path`, `env_requirements`, and `release_checkpoint`
   - Point `test_plan` at `features/dc-b2-bestiary2/03-test-plan.md`
   - Validate with:
     ```bash
     python3 scripts/qa-suite-validate.py --product dungeoncrawler --feature-id dc-b2-bestiary2
     ```
2. **Signal completion:**
    ```bash
    ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-b2-bestiary2 "<brief summary>"
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

# Acceptance Criteria: dc-b2-bestiary2

## Gap analysis reference
- DB sections: b2/s01–s02 (Baseline Requirements + Integration Notes, 12 REQs)
- Depends on: dc-cr-encounter-rules ✓, dc-cr-npc-system ✓, dc-b1-bestiary1 ✓
- Track B: existing creature schema/import path from Bestiary 1 is available; Bestiary 2 work is primarily `[EXTEND]` against that foundation

---

## Happy Path

### Creature Library Expansion
- [ ] `[EXTEND]` Bestiary 2 creatures can be imported into the existing `creature` content model without introducing a second creature schema.
- [ ] `[EXTEND]` Imported Bestiary 2 entries support the same baseline stat block fields already used by Bestiary 1 creatures: level, rarity, traits, perception, languages, skills, senses, AC, saves, HP, immunities, weaknesses, resistances, speeds, attacks, and abilities.
- [ ] `[EXTEND]` New creature families introduced by Bestiary 2 (including aberrations, aquatic creatures, constructs, fey, fiends, and similar additions) are represented using existing taxonomy/reference patterns so they are filterable alongside Bestiary 1 content.
- [ ] `[EXTEND]` Bestiary 2 variants and template-driven creatures can be represented without breaking the canonical base creature record.

### Data Import and Idempotency
- [ ] `[EXTEND]` A batch import pipeline loads structured Bestiary 2 source data into the live creature library.
- [ ] `[EXTEND]` Re-running the Bestiary 2 import updates matching records deterministically instead of creating duplicate creatures.
- [ ] `[EXTEND]` Import logs identify the source creature and action taken (create/update/skip/error) without logging player data or free-text payloads beyond the sanitized creature content itself.

### Encounter Tooling Integration
- [ ] `[EXTEND]` Encounter-building and creature-browser tooling can filter and surface Bestiary 2 creatures using the same level, trait, rarity, and tactical-role controls available to Bestiary 1 entries.
- [ ] `[EXTEND]` Encounter tooling does not require a separate Bestiary 2 code path; newly imported creatures become available automatically through the shared creature query layer.

### Access and Content Integrity
- [ ] `[TEST-ONLY]` Players can read Bestiary 2 creature entries through the same read-only surfaces used for the existing creature library.
- [ ] `[EXTEND]` GM-only import, override, or mutation routes for creature records remain restricted by `_campaign_gm_access: TRUE`.
- [ ] `[EXTEND]` Imported text fields are sanitized before persistence so creature flavor or rules text cannot inject executable or unsafe markup.

---

## Edge Cases
- [ ] `[EXTEND]` Creatures with optional or empty sections (for example no languages, no weaknesses, or no special senses) import successfully without placeholder junk data.
- [ ] `[EXTEND]` Creature families with many variants share taxonomy and filter behavior correctly while keeping each stat block independently addressable.
- [ ] `[EXTEND]` Template-applied creatures preserve both the base creature identity and the applied template metadata when that distinction exists in the source.

## Failure Modes
- [ ] `[TEST-ONLY]` Attempting to import malformed or incomplete creature payloads fails explicitly and does not partially persist corrupted creature records.
- [ ] `[TEST-ONLY]` Re-importing the same source data does not create duplicate slugs, duplicate records, or duplicate encounter filter entries.
- [ ] `[TEST-ONLY]` Non-GM users receive a 403 on creature import or override routes.

## Security acceptance criteria
- Authentication: creature library is read-only for players; GM-only mutation routes require `_campaign_gm_access: TRUE`
- CSRF: all POST/PATCH creature import or override routes require `_csrf_request_header_mode: TRUE`
- Input validation: creature stat block fields validate against expected types/ranges; import pipeline sanitizes all text fields
- PII/logging: no PII logged; creature id + encounter id + import action only
