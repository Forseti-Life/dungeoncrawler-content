# Suite Activation: dc-cr-background-system

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-06T02:05:29+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-background-system"`**  
   This links the test to the living requirements doc at `features/dc-cr-background-system/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-background-system-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-background-system",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-background-system"`**  
   Example:
   ```json
   {
     "id": "dc-cr-background-system-<route-slug>",
     "feature_id": "dc-cr-background-system",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-background-system",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-background-system

**Owner:** qa-dungeoncrawler  
**Date:** 2026-03-27  
**Feature:** Background System — content type, seed data, character creation integration, ability boost/skill application  
**AC source:** `features/dc-cr-background-system/01-acceptance-criteria.md`  
**Implementation notes:** `features/dc-cr-background-system/02-implementation-notes.md`  

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after config install; surface regressions immediately.
- KB: none found for background system specifically; first background feature in the pipeline.
- Dependency note: `dc-cr-character-creation` depends on this feature (and ancestry + class) being implemented first; this plan must be complete before character-creation grooming.

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` | PHPUnit functional (`./vendor/bin/phpunit`) | Content type existence, seed data count, background selection, boost/skill/feat application, validation guards, ACL, data integrity, rollback |
| `role-url-audit` | `scripts/site-audit-run.sh` | Background content type nodes public read; character mutation auth-required |

> **Parallel feature note:** This feature can be developed in parallel with `dc-cr-ancestry-system` and `dc-cr-character-class`. Test patterns mirror `dc-cr-ancestry-system`; coordinate Stage-0 activation order with PM.

---

## Test cases

### TC-BG-01 — background content type exists with all required fields
- **AC:** `[NEW]` Content type with fields: name, description, fixed_boost, free_boost note, skill_training, lore_skill, skill_feat
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testBackgroundContentTypeExists()`
- **Setup:** Fresh install of module
- **Expected:** `node_type_load('background')` returns non-null; field storage configs present for all required fields
- **Tags:** `[NEW]`, happy path

### TC-BG-02 — At least 5 core backgrounds seeded on install
- **AC:** `[NEW]` At least 5 core backgrounds seeded: Acolyte, Acrobat, Animal Whisperer, Artisan, Barkeep
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testFiveCoreBackgroundsSeeded()`
- **Verification command:** `drush php-eval "print \Drupal::entityQuery('node')->condition('type','background')->count()->execute();"` → expect `>= 5`
- **Expected:** Count `>= 5`; node titles include Acolyte, Acrobat, Animal Whisperer, Artisan, Barkeep
- **Tags:** `[NEW]`, happy path

### TC-BG-03 — Background node data spot-check (Acolyte)
- **AC:** `[NEW]` Data sourced from `CharacterManager::BACKGROUNDS` constant; not duplicated
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testAcolyteBackgroundData()`
- **Setup:** Load Acolyte background node
- **Expected:** All required fields non-null; fixed_boost matches CharacterManager constant for Acolyte; skill_training populated; lore_skill populated; skill_feat populated
- **Note:** Exact field values to be confirmed from `CharacterManager::BACKGROUNDS` in impl notes
- **Tags:** `[NEW]`, happy path (spot-check)

### TC-BG-04 — Character creation accepts and stores background selection
- **AC:** `[NEW]` Character creation step accepts a background selection and stores it on character entity
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testCharacterCreationStoresBackground()`
- **Setup:** Authenticate as player; create character draft; submit background selection (e.g., Acolyte)
- **Expected:** Character entity has background stored; persists after save
- **Tags:** `[NEW]`, happy path

### TC-BG-05 — Selecting background applies fixed and free ability boosts
- **AC:** `[NEW]` Background selection applies fixed boost and player's chosen free boost to ability score array
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testBackgroundAppliesAbilityBoosts()`
- **Setup:** Character with neutral ability scores; select background with known fixed boost; provide valid free boost choice (different ability)
- **Expected:** Both ability scores updated: fixed boost +2, free boost +2 (or equivalent mechanic)
- **Note:** Exact boost mechanic (additive int vs. advantage flag) TBD by Dev
- **Tags:** `[NEW]`, happy path

### TC-BG-06 — Selecting background grants skill training
- **AC:** `[NEW]` Background grants training in designated skill and Lore skill
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testBackgroundGrantsSkillTraining()`
- **Setup:** Character with no skill training; select background (e.g., Acolyte with Religion + Religion Lore)
- **Expected:** Character skills array includes both the background skill and lore skill as Trained
- **Tags:** `[NEW]`, happy path

### TC-BG-07 — Selecting background records skill feat
- **AC:** `[NEW]` Background records granted skill feat on character entity
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testBackgroundGrantsSkillFeat()`
- **Setup:** Select background with known skill_feat value
- **Expected:** Character's feat list or skill_feat field contains the background feat
- **Tags:** `[NEW]`, happy path

### TC-BG-08 — Fixed boost and free boost cannot target same ability score
- **AC:** `[NEW]` Validation rejects duplicate fixed+free boost on same ability score with message "Cannot apply two boosts to the same ability score from a single background"
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testDuplicateBoostRejected()`
- **Setup:** Select background; choose free boost = same ability as background's fixed boost
- **Expected:** Validation error: message contains "Cannot apply two boosts to the same ability score from a single background"; no state written
- **Tags:** `[NEW]`, edge case

### TC-BG-09 — Re-selecting background replaces prior choice
- **AC:** `[NEW]` Re-selection removes prior background's boosts, skills, and feat before applying new one
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testReSeletingBackgroundReplacesPrior()`
- **Setup:** Character with Acolyte background applied; re-select Artisan
- **Expected:** Acolyte boosts/skills/feat removed; Artisan boosts/skills/feat applied; no double-counting
- **Tags:** `[NEW]`, edge case

### TC-BG-10 — Saving without background returns validation error
- **AC:** `[NEW]` Missing background at creation step returns "Background is required"
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testMissingBackgroundValidationError()`
- **Setup:** Character draft with no background; attempt save
- **Expected:** Validation error message containing "Background is required"; character not saved
- **Tags:** `[NEW]`, edge case

### TC-BG-11 — Invalid background ID returns 400
- **AC:** `[NEW]` Invalid background ID to creation endpoint returns 400 with descriptive error
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testInvalidBackgroundIdReturns400()`
- **Setup:** Submit character creation with `background_id = "nonexistent"`
- **Expected:** HTTP 400; response body contains descriptive error
- **Tags:** `[NEW]`, failure mode

### TC-BG-12 — Free boost conflict caught before data write
- **AC:** `[NEW]` Free boost conflicting with fixed boost caught at save-time; returns clear message before any data written
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testFreeBoostConflictCaughtBeforeWrite()`
- **Setup:** Submit background selection with free_boost = same as fixed_boost; verify character state before and after
- **Expected:** Error returned; character ability scores unchanged (no partial write)
- **Tags:** `[NEW]`, failure mode

### TC-BG-13 — Anon can read background nodes (public view)
- **AC:** Background nodes publicly readable; application requires authentication
- **Suite:** `role-url-audit`
- **Rule type:** `qa-permissions.json` entry — anon → HTTP 200 for `GET /node/{background-node-id}` or equivalent background read route
- **Roles covered:** anonymous
- **Expected:** HTTP 200
- **Tags:** permissions/access control
- **Note:** Add `qa-permissions.json` rule at Stage 0 once route pattern confirmed with Dev.

### TC-BG-14 — Authenticated player cannot modify another player's background
- **AC:** Players may select background for own character only
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testPlayerCannotModifyOtherCharacterBackground()`
- **Setup:** Authenticate as player A; attempt to set background on character owned by player B
- **Expected:** HTTP 403; no state change
- **Tags:** permissions/access control

### TC-BG-15 — Admin can create, edit, delete background nodes
- **AC:** Admins can create, edit, and delete background content type nodes
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testAdminCRUDBackgroundNodes()`
- **Setup:** Authenticate as admin; create, update, delete a background node
- **Expected:** All operations succeed; count changes accordingly
- **Tags:** permissions/access control

### TC-BG-16 — Existing character with background survives module update
- **AC:** No data loss on module update
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testExistingCharacterBackgroundPreservedOnUpdate()`
- **Setup:** Character with background = Acolyte; run module update hook
- **Expected:** Background still stored correctly after update; no data corruption
- **Tags:** data integrity

### TC-BG-17 — Rollback: uninstalling module leaves character nodes intact
- **AC:** Uninstalling leaves character nodes intact; background reference becomes empty/null
- **Suite:** `module-test-suite`
- **Test method:** `BackgroundSystemTest::testModuleUninstallLeavesCharactersIntact()`
- **Setup:** Character with background applied; uninstall module
- **Expected:** Character node still loadable; background field empty/null (not corrupt); no PHP errors
- **Note:** Background stored as string in character JSON (per impl notes) — uninstall should be safe; confirm with Dev.
- **Tags:** data integrity, rollback

---

## AC items that cannot be fully expressed as automation

| AC item | Limitation | Note to PM |
|---|---|---|
| TC-BG-03 exact field values | Exact boost/skill/feat values per background TBD from `CharacterManager::BACKGROUNDS` constant | Dev to include exact field values per background in impl notes |
| TC-BG-05 boost mechanic | Additive int vs. advantage flag TBD | Dev to confirm boost storage format; same question as ancestry (coordinate with `dc-cr-ancestry-system` TC-AN-07) |
| TC-BG-13 background read route | Route pattern TBD by Dev (Drupal node view path vs. custom API) | Confirm with Dev at Stage 0; add `qa-permissions.json` rule accordingly |

---

## Pre-activation checklist (Stage 0, do not activate now)

- [ ] Get Dev impl notes with exact field values per background (TC-BG-03)
- [ ] Confirm ability boost mechanic (same question as `dc-cr-ancestry-system`; coordinate)
- [ ] Confirm background read route pattern (TC-BG-13)
- [ ] Add `module-test-suite` test class `BackgroundSystemTest` covering TC-BG-01 through TC-BG-17
- [ ] Add `qa-permissions.json` rule for background node public read (TC-BG-13)
- [ ] Validate suite: `python3 scripts/qa-suite-validate.py`
- [ ] Run full `role-url-audit` baseline (0 violations) after Dev deploys to local
- [ ] Coordinate activation with `dc-cr-ancestry-system` and `dc-cr-character-class` (all three prereqs for `dc-cr-character-creation`)

### Acceptance criteria (reference)

# Acceptance Criteria (PM-owned)

## Gap analysis reference

All criteria below are `[NEW]` — no existing background system implementation exists in dungeoncrawler_content. Dev builds from scratch. Can be built in parallel with dc-cr-ancestry-system and dc-cr-character-class.

## Happy Path
- [ ] `[NEW]` A `background` content type exists with fields: name, description, fixed_ability_boost (one of: Str/Dex/Con/Int/Wis/Cha), free_ability_boost (player selects any ability score except the fixed one), skill_training (one skill name), lore_skill (one Lore specialization string), and skill_feat (reference to a feat node or feat name string).
- [ ] `[NEW]` At least 5 core backgrounds are seeded as content (e.g., Acolyte, Acrobat, Animal Whisperer, Artisan, Barkeep).
- [ ] `[NEW]` A character creation step accepts a background selection and stores it on the character entity.
- [ ] `[NEW]` Selecting a background applies its fixed ability boost and the player's chosen free ability boost to the character's ability score array.
- [ ] `[NEW]` Selecting a background grants training in the background's designated skill and Lore skill on the character entity.
- [ ] `[NEW]` Selecting a background records the granted skill feat on the character entity.

## Edge Cases
- [ ] `[NEW]` A character cannot select the same ability score for both the fixed boost and the free boost; validation rejects duplicate selections with a clear error ("Cannot apply two boosts to the same ability score from a single background").
- [ ] `[NEW]` A character cannot have more than one background; re-selecting replaces the prior choice (ability boosts, skills, and feat from prior background are removed before applying the new one).
- [ ] `[NEW]` Attempting to save a character without a background selection during the background creation step returns a clear validation error ("Background is required").

## Failure Modes
- [ ] `[NEW]` An invalid background ID passed to the creation endpoint returns 400 with a descriptive error.
- [ ] `[NEW]` Selecting a free ability boost that conflicts with the fixed boost is caught at save-time and returns a clear message before any data is written.

## Permissions / Access Control
- [ ] Anonymous user behavior: background content type nodes are publicly readable; application to a character requires authentication.
- [ ] Authenticated user behavior: players may select a background for their own character only; cannot modify another player's character.
- [ ] Admin behavior: admins can create, edit, and delete background content type nodes.

## Data Integrity
- [ ] No data loss: an existing character with a background set must retain that data after module updates.
- [ ] Rollback path: uninstalling the background sub-module must leave character nodes intact (background reference and applied fields become empty/null, not corrupted).

## Verification method
```
drush php-eval "
  \$count = \Drupal::entityQuery('node')->condition('type','background')->count()->execute();
  print 'Background count: ' . \$count . PHP_EOL; // expect >= 5
"
```
- QA automated audit must still pass (0 violations, 0 failures) after module changes are deployed to local.

## Knowledgebase check
- KB: none found for background system specifically.
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit immediately after module changes.
- Dependency note: `dc-cr-character-creation` will depend on ancestry, background, and class all being planned/ready before its own triage.
- Agent: qa-dungeoncrawler
- Status: pending
