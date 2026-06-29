- Status: done
- Completed: 2026-04-19T05:31:31Z

# Suite Activation: dc-b2-bestiary2

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-19T04:30:36+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-b2-bestiary2"`**  
   This links the test to the living requirements doc at `features/dc-b2-bestiary2/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-b2-bestiary2-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-b2-bestiary2",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-b2-bestiary2"`**  
   Example:
   ```json
   {
     "id": "dc-b2-bestiary2-<route-slug>",
     "feature_id": "dc-b2-bestiary2",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-b2-bestiary2",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-b2-bestiary2

## Coverage summary
- AC items: 12 (creature import, schema compatibility, encounter integration, GM-only mutation routes, idempotent re-import handling)
- Test cases: 6 (TC-B2-01-06)
- Suites: phpunit import/service coverage + Playwright encounter/browser smoke coverage
- Security: GM-only import/override paths; player read-only creature-library access

---

## TC-B2-01 — Bestiary 2 import populates shared creature schema
- Description: Import a representative Bestiary 2 data set and confirm created records use the existing `creature` model from Bestiary 1.
- Suite: `dc-b2-bestiary2-e2e`
- Expected: records persist with level, rarity, traits, senses, attacks, abilities, and defenses mapped into the shared schema.
- AC: Creature Library Expansion 1-2, Data Import and Idempotency 1

## TC-B2-02 — New creature families are filterable in encounter tooling
- Description: Bestiary 2 families such as aberrations, aquatic creatures, constructs, fey, and fiends appear through the shared encounter/browser filters.
- Suite: `dc-b2-bestiary2-e2e`
- Expected: level, trait, rarity, and tactical-role filters surface Bestiary 2 creatures without a separate Bestiary 2 code path.
- AC: Creature Library Expansion 3-4, Encounter Tooling Integration 1-2

## TC-B2-03 — Re-import is deterministic
- Description: Re-run the same Bestiary 2 import payload.
- Suite: `dc-b2-bestiary2-e2e`
- Expected: matching records update in place; duplicate creatures, duplicate slugs, and duplicate filter rows are not created.
- AC: Data Import and Idempotency 2, Failure Modes 2

## TC-B2-04 — Optional or sparse stat blocks import cleanly
- Description: Import creatures that omit optional sections such as languages, weaknesses, or special senses.
- Suite: `dc-b2-bestiary2-e2e`
- Expected: records save successfully without junk placeholder values or malformed arrays.
- AC: Edge Cases 1-3

## TC-B2-05 — Player access remains read-only
- Description: Exercise creature-library reads as a player and mutation/import endpoints as a non-GM.
- Suite: `dc-b2-bestiary2-e2e`
- Expected: read paths succeed, but import/override routes return 403 for non-GM users.
- AC: Access and Content Integrity 1-2, Failure Modes 3

## TC-B2-06 — Malformed import payload fails explicitly
- Description: Submit incomplete or invalid Bestiary 2 import data.
- Suite: `dc-b2-bestiary2-e2e`
- Expected: import fails with explicit error handling and does not partially persist corrupted records.
- AC: Access and Content Integrity 3, Failure Modes 1

### Acceptance criteria (reference)

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
- Agent: qa-dungeoncrawler
- Status: pending
