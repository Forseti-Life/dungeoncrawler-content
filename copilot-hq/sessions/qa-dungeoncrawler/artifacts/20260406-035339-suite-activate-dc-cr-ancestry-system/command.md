# Suite Activation: dc-cr-ancestry-system

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-06T03:53:39+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-ancestry-system"`**  
   This links the test to the living requirements doc at `features/dc-cr-ancestry-system/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-ancestry-system-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-ancestry-system",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-ancestry-system"`**  
   Example:
   ```json
   {
     "id": "dc-cr-ancestry-system-<route-slug>",
     "feature_id": "dc-cr-ancestry-system",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-ancestry-system",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-ancestry-system

**Owner:** qa-dungeoncrawler  
**Date:** 2026-03-27  
**Feature:** Ancestry System — content type, API exposure, character creation integration  
**AC source:** `features/dc-cr-ancestry-system/01-acceptance-criteria.md`  
**Implementation notes:** `features/dc-cr-ancestry-system/02-implementation-notes.md`  

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after config install; surface regressions immediately after module changes.
- KB: no prior lessons found for ancestry system specifically; first ancestry feature in the pipeline.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | Content type existence, seed data integrity, ancestry selection logic, ability score application, data integrity, rollback |
| `role-url-audit` | `scripts/site-audit-run.sh` | Route ACL: `GET /ancestries` (public), `GET /ancestries/{id}` (public), mutation endpoints (auth-required) |

> **Note:** `GET /ancestries` and `GET /ancestries/{id}` are public read endpoints — add to `role-url-audit` anon-accessible list at Stage 0. Character ancestry selection/mutation endpoints require auth — add to `qa-permissions.json` role rules at Stage 0. Admin CRUD of ancestry nodes is tested via `module-test-suite`.

---

## Test cases

### TC-AN-01 — ancestry content type exists with all required fields
- **AC:** `[NEW]` `ancestry` content type with fields: name, hit_points, size, speed, ability_boosts, ability_flaws, languages, senses, ancestry feat reference
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testAncestryContentTypeExists()`
- **Setup:** Fresh install of module
- **Expected:** `node_type_load('ancestry')` returns non-null; all field storage configs present (`field_ancestry_hp`, `field_ancestry_size`, `field_ancestry_speed`, `field_ancestry_boosts`, `field_ancestry_flaws`, `field_ancestry_languages`, `field_ancestry_senses`)
- **Tags:** `[NEW]`, happy path

### TC-AN-02 — 6 core ancestry nodes seeded on install
- **AC:** `[EXTEND]` All six core PF2E ancestries available: dwarf, elf, gnome, goblin, halfling, human
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testSixCorancestriesSeeded()`
- **Verification command:** `drush php-eval "print \Drupal::entityQuery('node')->condition('type','ancestry')->count()->execute();"` → expect `6`
- **Expected:** Exactly 6 ancestry nodes; names match: dwarf, elf, gnome, goblin, halfling, human
- **Tags:** `[EXTEND]`, happy path

### TC-AN-03 — Ancestry node data matches CharacterManager constants (dwarf spot-check)
- **AC:** `[EXTEND]` Data sourced from `CharacterManager::ANCESTRIES`; not duplicated
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testDwarfAncestryDataMatchesConstants()`
- **Setup:** Load dwarf ancestry node
- **Expected:** `field_ancestry_hp = 10`, `field_ancestry_speed = 20`, `field_ancestry_boosts` contains Con and Wis, `field_ancestry_flaws` contains Cha
- **Tags:** `[EXTEND]`, happy path (spot-check; full set verified by TC-AN-02 count)

### TC-AN-04 — GET /ancestries returns all 6 ancestries
- **AC:** `[NEW]` `GET /ancestries` returns list with id, name, hp, size, speed, ability boosts/flaws, languages, senses
- **Suite:** `role-url-audit` (HTTP 200 anon) + `module-test-suite` (response body validation)
- **Test class/method:** `AncestrySystemTest::testGetAncestriesEndpoint()`
- **Setup:** 6 ancestry nodes seeded
- **Expected (role-url-audit):** HTTP 200 for anonymous, authenticated, admin
- **Expected (module-test-suite):** Response JSON contains array of 6 objects; each has keys: `id`, `name`, `hp`, `size`, `speed`, `ability_boosts`, `ability_flaws`, `languages`, `senses`
- **Roles covered:** anonymous, authenticated, admin
- **Tags:** `[NEW]`, happy path

### TC-AN-05 — GET /ancestries/{id} returns full ancestry detail with heritages
- **AC:** `[NEW]` `GET /ancestries/{id}` returns full detail including available heritages
- **Suite:** `role-url-audit` (HTTP 200 anon) + `module-test-suite` (response body)
- **Test class/method:** `AncestrySystemTest::testGetAncestryByIdEndpoint()`
- **Setup:** Use `dwarf` as test ID
- **Expected (role-url-audit):** HTTP 200 for anonymous
- **Expected (module-test-suite):** Response includes full field set plus `heritages` array sourced from `CharacterManager::HERITAGES`
- **Roles covered:** anonymous, authenticated, admin
- **Tags:** `[NEW]`, happy path

### TC-AN-06 — Character creation accepts and stores ancestry selection
- **AC:** `[NEW]` Character creation step stores ancestry on character entity
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testCharacterCreationStoresAncestry()`
- **Setup:** Authenticate as player; create character draft; submit ancestry selection (e.g., elf)
- **Expected:** Character entity has ancestry field set to elf; persists after save
- **Tags:** `[NEW]`, happy path

### TC-AN-07 — Selecting ancestry applies ability boosts and flaws
- **AC:** `[NEW]` Ancestry selection applies boosts/flaws to character ability score array
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testAncestryAppliesAbilityBoostsAndFlaws()`
- **Setup:** Character with neutral ability scores; select elf (Dex+, Int+ boosts; Con flaw per PF2E core)
- **Expected:** Character ability scores updated: Dex+2, Int+2, Con-2 (or equivalent boost/flaw mechanic)
- **Tags:** `[NEW]`, happy path

### TC-AN-08 — Selecting ancestry sets correct base HP contribution
- **AC:** `[NEW]` Ancestry sets base HP: dwarf 10, elf 6, gnome 8, goblin 6, halfling 6, human 8
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testAncestryBaseHitPoints()`
- **Inputs to test:** all 6 core ancestries
- **Expected:** `ancestry_hp` matches the canonical values in `CharacterManager::ANCESTRIES` for each ancestry
- **Tags:** `[NEW]`, happy path

### TC-AN-09 — Selecting ancestry sets correct speed
- **AC:** `[NEW]` Ancestry sets speed: dwarf 20, elf 30, gnome 25, goblin 25, halfling 25, human 25
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testAncestrySpeed()`
- **Inputs to test:** all 6 core ancestries
- **Expected:** character speed matches canonical values
- **Tags:** `[NEW]`, happy path

### TC-AN-10 — Re-selecting ancestry replaces previous choice
- **AC:** `[NEW]` A character cannot have more than one ancestry; re-selecting replaces
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testReSeletingAncestryReplacesExisting()`
- **Setup:** Character with elf ancestry; re-select dwarf
- **Expected:** ancestry field = dwarf; old elf boosts/flaws removed; dwarf stats applied
- **Tags:** `[NEW]`, edge case

### TC-AN-11 — Saving character without ancestry returns validation error
- **AC:** `[NEW]` Attempting to save without ancestry selection returns "Ancestry is required"
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testMissingAncestryValidationError()`
- **Setup:** Character draft with no ancestry; attempt save/submit
- **Expected:** validation error message containing "Ancestry is required"; character not saved
- **Tags:** `[NEW]`, edge case

### TC-AN-12 — Human free boost selection: duplicate ability scores rejected
- **AC:** `[NEW]` Human's two free ability boosts reject duplicate ability score selections
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testHumanFreeBoostDuplicateRejected()`
- **Setup:** Character selecting human ancestry; submit two free boosts targeting the same ability (e.g., Str + Str)
- **Expected:** validation error; boosts not applied
- **Tags:** `[NEW]`, edge case

### TC-AN-13 — Invalid ancestry ID returns 400
- **AC:** `[NEW]` Invalid ancestry ID to creation endpoint returns 400 with descriptive error
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testInvalidAncestryIdReturns400()`
- **Setup:** POST/PUT character creation with `ancestry_id = "dragon"` (nonexistent)
- **Expected:** HTTP 400; response body contains descriptive error (e.g., "Invalid ancestry: dragon")
- **Tags:** `[NEW]`, failure mode

### TC-AN-14 — Ability boost/flaw conflict rejected
- **AC:** `[NEW]` Boosting and flawing the same ability score caught at validation
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testBoostFlawConflictRejected()`
- **Setup:** Attempt to apply an ancestry (or custom selection) where same ability appears in both boosts and flaws
- **Expected:** validation error with explicit message; no partial state written
- **Tags:** `[NEW]`, failure mode

### TC-AN-15 — Anon can read ancestry content type nodes (public view)
- **AC:** Anonymous user behavior: ancestry nodes are publicly readable
- **Suite:** `role-url-audit`
- **Rule type:** `qa-permissions.json` entry — anon → HTTP 200 for `GET /ancestries` and `GET /ancestries/{id}`
- **Roles covered:** anonymous
- **Expected:** HTTP 200
- **Tags:** permissions/access control
- **Note:** Add `qa-permissions.json` rule at Stage 0 once routes are confirmed.

### TC-AN-16 — Authenticated player cannot modify another player's ancestry selection
- **AC:** Players may select ancestry for own characters; cannot modify another player's character
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testPlayerCannotModifyOtherCharacterAncestry()`
- **Setup:** Authenticate as player A; attempt to set ancestry on character owned by player B
- **Expected:** HTTP 403; no state change
- **Tags:** permissions/access control

### TC-AN-17 — Admin can create, edit, delete ancestry nodes
- **AC:** Admins can create, edit, and delete ancestry content type nodes
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testAdminCRUDAncestryNodes()`
- **Setup:** Authenticate as admin; create new ancestry node, update it, delete it
- **Expected:** all operations HTTP 200/201/204; node count changes accordingly
- **Tags:** permissions/access control

### TC-AN-18 — Existing character with ancestry set survives module update
- **AC:** No data loss: character with ancestry already set must not lose data after module updates
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testExistingCharacterAncestryPreservedOnUpdate()`
- **Setup:** Character with ancestry = elf; run module update hook
- **Expected:** ancestry field still = elf after update; no data corruption
- **Tags:** data integrity

### TC-AN-19 — Rollback: uninstalling module leaves character nodes intact
- **AC:** Uninstalling ancestry sub-module must not corrupt existing character nodes; ancestry reference becomes empty/null
- **Suite:** `module-test-suite`
- **Test class/method:** `AncestrySystemTest::testModuleUninstallLeavesCharactersIntact()`
- **Setup:** Character with ancestry set; uninstall module via `\Drupal::service('module_installer')->uninstall()`
- **Expected:** character node still loadable; ancestry reference field empty/null (not corrupt); no PHP errors
- **Tags:** data integrity, rollback
- **Automation note:** Module uninstall in Drupal test env is feasible; confirm with Dev that ancestry field is stored as string/JSON in character (not a hard entity reference that would cascade-delete on uninstall).

---

## AC items that cannot be fully expressed as automation

| AC item | Limitation | Note to PM |
|---|---|---|
| TC-AN-07 ability boost/flaw exact values | Exact boost/flaw mechanic (additive int vs. advantage flag) TBD by Dev | Confirm boost/flaw storage format with Dev before writing assertions |
| TC-AN-12 human free boosts | Requires UI step or API payload shape TBD by Dev | Add test once API request format for "free boost selection" is defined |
| TC-AN-19 rollback | Depends on whether ancestry is an entity reference field or JSON string in character | If entity reference: cascade-delete risk; if JSON string: safe null. Dev to confirm. |

---

## Pre-activation checklist (Stage 0, do not activate now)

- [ ] Confirm `GET /ancestries` and `GET /ancestries/{id}` route paths with Dev; add `qa-permissions.json` anon-read rules (TC-AN-15)
- [ ] Confirm ancestry-on-character storage format (entity reference vs. JSON string) for TC-AN-19 rollback test
- [ ] Confirm ability boost/flaw storage format for TC-AN-07 assertions
- [ ] Confirm API request shape for human free-boost selection (TC-AN-12)
- [ ] Add `module-test-suite` test class `AncestrySystemTest` covering TC-AN-01 through TC-AN-19
- [ ] Validate suite after adding entries: `python3 scripts/qa-suite-validate.py`
- [ ] Run full `role-url-audit` baseline (0 violations) after Dev deploys to local

### Acceptance criteria (reference)

# Acceptance Criteria (PM-owned)

## Gap analysis reference

Gap analysis performed against `dungeoncrawler_content/src/Service/CharacterManager.php`.

Coverage findings:
- `CharacterManager::ANCESTRIES` constant — all 14 ancestries (including 6 core + half-elf, half-orc, leshy, orc, catfolk, kobold, ratfolk, tengu) with hp, size, speed, boosts, flaws, languages, traits, vision — **Full** (data exists as PHP constants)
- `CharacterManager::HERITAGES` — heritage options per ancestry — **Full** (data exists as PHP constants)
- Drupal `ancestry` content type (persistent nodes, admin-editable) — **None** (data is in PHP only; no content type found)
- `GET /ancestries` API endpoint — **None** (no route found for exposing ancestry list)
- Character creation step applying ancestry stats to draft — **Partial** (CharacterManager exists; ancestry application to character not confirmed wired)

Feature type: **enhancement** — ancestry data is already in PHP constants; Dev must expose it via API/content type. Do NOT duplicate the ancestry data; reference or migrate `CharacterManager::ANCESTRIES`.

Depends on: character entity model (no hard dependency on dc-cr-action-economy; can be built in parallel).

## Happy Path
- [ ] `[NEW]` An `ancestry` content type exists with fields: name, hit_points (int), size (tiny/small/medium/large), speed (int, feet), ability_boosts (list of ability scores), ability_flaws (list of ability scores), languages (list), senses (list, e.g. darkvision), and a reference to available ancestry feats.
- [ ] `[EXTEND]` All six core PF2E ancestries are available via API: dwarf, elf, gnome, goblin, halfling, human. (Data already in `CharacterManager::ANCESTRIES` — expose it, do NOT duplicate.)
- [ ] `[NEW]` `GET /ancestries` endpoint returns all ancestries with id, name, hp, size, speed, ability boosts/flaws, languages, and senses.
- [ ] `[NEW]` `GET /ancestries/{id}` returns full ancestry detail including available heritages (from `CharacterManager::HERITAGES`).
- [ ] `[NEW]` A character creation step accepts an ancestry selection and stores it on the character entity.
- [ ] `[NEW]` Selecting an ancestry applies its ability boosts and flaws to the character's ability score array.
- [ ] `[NEW]` Selecting an ancestry sets the character's base hit points contribution from ancestry (e.g., dwarf: 10, elf: 6, gnome: 8, goblin: 6, halfling: 6, human: 8).
- [ ] `[NEW]` Selecting an ancestry sets the character's speed (e.g., dwarf: 20 ft, elf: 30 ft, gnome: 25 ft, goblin: 25 ft, halfling: 25 ft, human: 25 ft).

## Edge Cases
- [ ] `[NEW]` A character cannot have more than one ancestry selected; re-selecting replaces the previous choice.
- [ ] `[NEW]` Attempting to save a character without an ancestry selection returns a clear validation error ("Ancestry is required").
- [ ] `[NEW]` An ancestry with two free ability boosts (e.g., human) allows the player to select any two ability scores; validation rejects duplicate selections.

## Failure Modes
- [ ] `[NEW]` An invalid ancestry ID passed to the creation endpoint returns 400 with a descriptive error.
- [ ] `[NEW]` Ability boost conflicts (boosting and flawing the same score) are caught at validation and rejected with an explicit message.

## Permissions / Access Control
- [ ] Anonymous user behavior: ancestry content type nodes are publicly readable (view); creation/selection requires authentication.
- [ ] Authenticated user behavior: players may select an ancestry for their own characters; cannot modify another player's character.
- [ ] Admin behavior: admins can create, edit, and delete ancestry content type nodes.

## Data Integrity
- [ ] No data loss: an existing character that already has an ancestry set must not lose that data after module updates.
- [ ] Rollback path: uninstalling the ancestry sub-module must leave character nodes intact (ancestry reference field becomes empty/null, not corrupted).

## Verification method
```
drush php-eval "
  // Verify 6 ancestry nodes exist
  \$count = \Drupal::entityQuery('node')->condition('type','ancestry')->count()->execute();
  print 'Ancestry count: ' . \$count . PHP_EOL; // expect 6
"
```
- QA automated audit must still pass (0 violations, 0 failures) after module changes are deployed to local.

## Knowledgebase check
- KB: none found for ancestry system specifically.
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit immediately after module changes.
- Dependency note: character creation workflow (`dc-cr-character-creation`) will reference this feature as a prerequisite; triage character creation only after ancestry, class, and background are planned.
- Agent: qa-dungeoncrawler
- Status: pending
