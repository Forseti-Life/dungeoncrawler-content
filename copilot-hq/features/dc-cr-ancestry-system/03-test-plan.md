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
