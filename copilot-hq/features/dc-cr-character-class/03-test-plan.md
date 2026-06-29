# Test Plan: dc-cr-character-class

**Owner:** qa-dungeoncrawler  
**Date:** 2026-03-27  
**Feature:** Character Class — content type, 12 core PF2E classes seeded, creation step, HP/proficiency/feature application  
**AC source:** `features/dc-cr-character-class/01-acceptance-criteria.md`  
**Implementation notes:** `features/dc-cr-character-class/02-implementation-notes.md`  

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit immediately after module changes; surface regressions quickly.
- KB: no prior lessons found for character class system; this is the first class implementation in the pipeline.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | Business logic: class selection, HP/proficiency/feature application, validation guards, rollback |
| `role-url-audit` | `scripts/site-audit-run.sh` | Access control: class content type node read (anon), class application endpoints (auth required) |
| `drush-verify` | `drush php-eval` | Seeding verification: 12 class nodes exist after install |

> **Note:** Most character class logic is service/entity layer (PHP). URL-accessible surfaces are the `character_class` content type nodes (publicly readable) and any API endpoints for applying a class to a character (auth-required). No Playwright suite needed at this scope.

---

## Test cases

### TC-CC-01 — character_class content type exists with required fields
- **AC:** `[NEW]` content type with name, description, key_ability, hit_points_per_level, proficiencies, class_features
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterClassContentTypeTest::testContentTypeFieldsExist()`
- **Setup:** Load `character_class` entity bundle; inspect field definitions
- **Expected:** All required fields present with correct types
- **Roles:** n/a (structural check)

### TC-CC-02 — 12 core PF2E classes seeded
- **AC:** `[NEW]` alchemist, barbarian, bard, champion, cleric, druid, fighter, monk, ranger, rogue, sorcerer, wizard
- **Suite:** `drush-verify`
- **Command:**
  ```bash
  drush php-eval "
    \$count = \Drupal::entityQuery('node')->condition('type','character_class')->count()->execute();
    print 'Class count: ' . \$count . PHP_EOL;
  "
  ```
- **Expected:** Count = 12
- **Roles:** n/a (data seeding check)

### TC-CC-03 — Character class nodes publicly readable (anonymous)
- **AC:** `[NEW]` Anonymous user behavior: publicly readable
- **Suite:** `role-url-audit`
- **Entry:** `GET /character_class/{id}` — HTTP 200 for anonymous
- **Expected:** 200 (or 200 with JSON if REST endpoint)
- **Roles:** anonymous

### TC-CC-04 — Class application endpoint requires authentication
- **AC:** `[NEW]` Application to a character requires authentication
- **Suite:** `role-url-audit`
- **Entry:** `POST /api/character/{id}/class` (or equivalent) — HTTP 403 for anonymous
- **Expected:** 403 for anonymous; 200/201 for authenticated player
- **Roles:** anonymous (403), authenticated player (200)

### TC-CC-05 — Selecting a class stores it on the character entity
- **AC:** `[NEW]` Character creation step accepts class selection and stores it
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterClassSelectionTest::testClassStoredOnCharacter()`
- **Setup:** Create character; call class-selection service with valid class ID
- **Expected:** Character entity has `class` reference matching selected class
- **Roles:** authenticated player

### TC-CC-06 — Selecting a class sets HP-per-level correctly
- **AC:** `[NEW]` Sets character's HP-per-level from class (e.g., fighter: 10, wizard: 6, cleric: 8)
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterClassSelectionTest::testHpPerLevelApplied()`
- **Setup:** Create character; select fighter, verify hp_per_level=10; select wizard, verify hp_per_level=6
- **Expected:** hp_per_level on character matches class value
- **Roles:** authenticated player

### TC-CC-07 — Selecting a class applies proficiency levels
- **AC:** `[NEW]` Applies class's proficiency levels to character's proficiency record
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterClassSelectionTest::testProficienciesApplied()`
- **Setup:** Create character; select fighter; check saves/attacks/defenses proficiency fields
- **Expected:** Proficiency record matches fighter's class proficiencies (e.g., Fortitude Expert, Reflex Expert, Will Trained)
- **Roles:** authenticated player

### TC-CC-08 — Selecting a class records 1st-level class features
- **AC:** `[NEW]` Records class's 1st-level class features on character entity
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterClassSelectionTest::testFirstLevelFeaturesRecorded()`
- **Setup:** Create character; select a class with known 1st-level features; inspect character's class_features field
- **Expected:** 1st-level features present on character entity
- **Roles:** authenticated player

### TC-CC-09 — Re-selecting class replaces prior choice
- **AC:** `[NEW]` Cannot have more than one class; re-selecting replaces prior choice (HP/proficiencies/features from prior class removed first)
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterClassSelectionTest::testReselectionReplacesPriorClass()`
- **Setup:** Character with fighter selected; re-select wizard
- **Expected:** Character's class = wizard, hp_per_level = 6 (not fighter's 10), fighter proficiencies removed and wizard proficiencies applied
- **Roles:** authenticated player

### TC-CC-10 — Saving without class selection returns validation error
- **AC:** `[NEW]` Returns "Class is required" validation error
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterClassSelectionTest::testMissingClassReturnsError()`
- **Setup:** Attempt to progress past class creation step without selecting a class
- **Expected:** Error message contains "Class is required"; character not saved
- **Roles:** authenticated player

### TC-CC-11 — Key ability choice required for multi-ability classes
- **AC:** `[NEW]` Class with multiple key ability options (e.g., champion: Str or Dex) requires player to choose one
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterClassSelectionTest::testKeyAbilityChoiceRequired()`
- **Setup:** Select champion class without providing key ability choice
- **Expected:** Error "You must choose a key ability for this class" returned; class not applied
- **Roles:** authenticated player

### TC-CC-12 — Invalid class ID returns 400
- **AC:** `[NEW]` Invalid class ID returns 400 with descriptive error
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterClassSelectionTest::testInvalidClassIdReturns400()`
- **Setup:** Call class-selection API with non-existent class ID (e.g., `id=99999`)
- **Expected:** HTTP 400, response contains descriptive error message
- **Roles:** authenticated player

### TC-CC-13 — Player cannot apply class to another player's character
- **AC:** `[NEW]` Authenticated player cannot modify another player's character
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterClassSelectionTest::testCannotModifyOtherPlayersCharacter()`
- **Setup:** Create character owned by user A; attempt class selection as user B
- **Expected:** 403 or authorization exception
- **Roles:** authenticated player (other's character)

### TC-CC-14 — Admin can create/edit/delete character_class nodes
- **AC:** `[NEW]` Admin behavior: can create, edit, delete character_class nodes
- **Suite:** `role-url-audit`
- **Entry:** `GET /node/add/character_class` — HTTP 200 for admin, 403 for player/anonymous
- **Expected:** Admin: 200; authenticated player: 403; anonymous: 403
- **Roles:** admin (200), player (403), anonymous (403)

### TC-CC-15 — No data loss on character with class after module update
- **AC:** `[NEW]` Existing character with class set retains data after module updates
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterClassDataIntegrityTest::testCharacterClassRetainedAfterModuleUpdate()`
- **Setup:** Character with class set; run module update hooks; verify class reference intact
- **Expected:** Class reference and derived fields (hp_per_level, proficiencies, features) unchanged
- **Roles:** n/a (data integrity check)

### TC-CC-16 — Rollback: uninstall leaves character nodes intact
- **AC:** `[NEW]` Uninstalling class sub-module leaves character nodes intact (class reference becomes empty/null, not corrupted)
- **Suite:** `module-test-suite`
- **Test class/method:** `CharacterClassDataIntegrityTest::testUninstallPreservesCharacterNodes()`
- **Setup:** Character with class; uninstall class sub-module; inspect character node
- **Expected:** Character node exists, class reference is empty/null, no corruption
- **Roles:** n/a (rollback check)

### TC-CC-17 — QA audit still passes after module deployment
- **AC:** QA automated audit must still pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler` (local dev)
- **Expected:** 0 violations, 0 failures
- **Roles:** all 6 roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| "class_features (list of feature names with level granted)" field completeness | Verifying that all 12 classes have accurate and complete feature lists at every level requires manual review against PF2E Core Rulebook; automation can only verify that fields exist and are non-empty at level 1. |
| Key ability UI prompt for multi-ability classes | The UI/API prompt behavior for champion's Str vs Dex choice depends on the frontend implementation not yet designed. TC-CC-11 tests the back-end validation guard; the UX flow requires a Playwright test once the form is built. |

---

## Regression risk areas

1. `dc-cr-ancestry-system` overlap: both ancestry and class contribute to character HP — verify HP calculation does not double-count or overwrite when both are set.
2. `dc-cr-background-system` overlap: background grants skill training; class also grants skill training — verify they are additive, not replacing each other.
3. Proficiency field schema: adding new proficiency categories (class level) must not corrupt existing proficiency records from prior character states.
4. QA audit regression: any new routes added by the class module must not introduce unexpected 404/403 failures in the `role-url-audit` suite.
