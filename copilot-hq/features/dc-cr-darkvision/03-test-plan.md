# Test Plan: dc-cr-darkvision

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-06
**Feature:** Darkvision Sense Entity — standalone `sense` entity, ancestry `senses` field reference, encounter visibility check integration, Low-Light Vision distinction, permissions
**AC source:** `features/dc-cr-darkvision/01-acceptance-criteria.md`
**Status:** groomed (next-release; DO NOT add to suite.json until Stage 0 activation)

---

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after any entity/module changes; test immediately after rebuild.
- KB: no prior lessons for sense entity systems in dungeoncrawler. Reference general Drupal entity reference patterns if needed.

## Implementation context (gap analysis findings)

Codebase scan confirms AC `[NEW]` classification is correct:
- `ConditionManager.php` defines `concealed` and `flat_footed` conditions but has no lighting/sense check path.
- `CharacterViewController.php` renders `perception.senses` from `feat_effects['senses']` (feat-based path only; no standalone sense entity system).
- `AncestryController.php` maps `vision` to a plain string (`'senses' => $data['vision'] ?? 'normal'`); no structured multi-value sense reference.
- `HexMapController.php` tracks `lighting` level per room but does not currently apply conditions based on character senses.
- `CharacterCreationController.php` references `'Extended darkvision'` as a Rock Gnome heritage benefit string — display only, not mechanical.

All AC items are new implementation. No existing sense entity, no visibility-check-by-senses integration found.

---

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` (unit) | PHPUnit unit (`./vendor/bin/phpunit tests/src/Unit/Service/`) | Sense entity structure, ConditionManager darkvision bypass, Low-Light Vision distinction, null-senses crash guard, ancestry corruption guard |
| `module-test-suite` (functional) | PHPUnit functional (`./vendor/bin/phpunit tests/src/Functional/`) | Ancestry senses field populated for Dwarf; multiple ancestry reference without duplication; character sheet API includes senses; admin sense entity CRUD |
| `role-url-audit` | `scripts/site-audit-run.sh` | Character sheet senses display (anon/auth); admin sense entity UI access |

---

## Test cases

### TC-DV-01 — Darkvision sense entity exists with correct id and type

- **AC:** `[NEW]` Sense entity id=`darkvision`, type=`vision` exists
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `SenseEntityTest::testDarkvisionEntityExistsWithCorrectIdAndType()`
- **Setup:** Load sense entity with id `darkvision`
- **Expected:** Entity exists; `type = 'vision'`; `id = 'darkvision'`; no exception
- **Roles:** n/a (data layer)

### TC-DV-02 — Darkvision sense entity defines correct effects

- **AC:** `[NEW]` Sense defines: no Concealed from darkness/dim light, no flat-footed from darkness, black-and-white vision in darkness
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `SenseEntityTest::testDarkvisionEffectsDefinition()`
- **Setup:** Load darkvision sense entity; read effects array
- **Expected:** `effects['suppress_concealed_in_darkness'] = true`; `effects['suppress_flat_footed_in_darkness'] = true`; `effects['vision_in_darkness'] = 'black_and_white'`
- **Roles:** n/a (data layer)

### TC-DV-03 — Low-Light Vision is a distinct sense entity from Darkvision

- **AC:** `[NEW]` Darkvision is distinct from Low-Light Vision; separate sense entities with different behaviors
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `SenseEntityTest::testLowLightVisionIsDistinctFromDarkvision()`
- **Setup:** Load both `darkvision` and `low-light-vision` sense entities
- **Expected:** Two distinct entity objects; `low-light-vision` has `effects['sees_dim_as_bright'] = true` and `effects['blind_in_darkness'] = true` (or equivalent); darkvision effects are not present on low-light-vision
- **Roles:** n/a (data layer)

### TC-DV-04 — Dwarf ancestry has darkvision in senses field

- **AC:** `[NEW]` Ancestry data model supports multi-value `senses` field; dwarf references `darkvision`
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `AncestryDataTest::testDwarfAncestryReferencesDarkvision()`
- **Setup:** Load dwarf ancestry data; read `senses` field
- **Expected:** `senses` is an array; contains entry with `id = 'darkvision'`; no duplication
- **Roles:** n/a (data layer)

### TC-DV-05 — Multiple ancestries can reference darkvision without duplication

- **AC:** `[NEW]` Multiple ancestries can reference the same `darkvision` sense entity without duplication
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `AncestryDataTest::testMultipleAncestriesCanReferenceSameSenseEntity()`
- **Setup:** Load two ancestries that both have darkvision (e.g., Dwarf + Goblin); check their senses reference
- **Expected:** Both ancestries have `darkvision` in senses; the sense entity itself is not duplicated in the data store; shared reference intact
- **Roles:** n/a (data layer)

### TC-DV-06 — Encounter visibility: character with darkvision does NOT get Concealed in darkness

- **AC:** `[NEW]` Visibility/lighting system checks `character.senses` before applying Concealed from darkness
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `VisibilitySystemTest::testDarkvisionCharacterNotConcealedInDarkness()`
- **Setup:** Character with `senses = ['darkvision']`; room lighting = `darkness`; call visibility condition check
- **Expected:** `concealed` condition NOT applied to character in darkness; no Concealed condition in returned state
- **Roles:** n/a (service layer)

### TC-DV-07 — Encounter visibility: character with darkvision does NOT get flat-footed in darkness

- **AC:** `[NEW]` Visibility system bypasses flat-footed from darkness for darkvision-bearing characters
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `VisibilitySystemTest::testDarkvisionCharacterNotFlatFootedInDarkness()`
- **Setup:** Character with `senses = ['darkvision']`; room lighting = `darkness`; call visibility condition check
- **Expected:** `flat_footed` condition NOT applied due to darkness (may still be applied from other sources); no lighting-sourced flat_footed
- **Roles:** n/a (service layer)

### TC-DV-08 — Encounter visibility: character WITHOUT darkvision still gets Concealed in darkness

- **AC:** `[NEW]` Baseline behavior unchanged: character without darkvision receives Concealed in darkness
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `VisibilitySystemTest::testCharacterWithoutDarkvisionConcealedInDarkness()`
- **Setup:** Character with `senses = []` (or `senses = ['normal']`); room lighting = `darkness`
- **Expected:** `concealed` condition IS applied; baseline behavior preserved
- **Roles:** n/a (service layer)

### TC-DV-09 — Darkvision does not suppress Concealed from non-darkness sources

- **AC:** `[NEW]` Darkvision character may still receive Concealed from other sources (fog, invisibility)
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `VisibilitySystemTest::testDarkvisionDoesNotSuppressConcealedFromFog()`
- **Setup:** Character with `senses = ['darkvision']`; room lighting = `normal`; Concealed applied via fog/invisibility source
- **Expected:** Concealed condition IS applied (darkvision does not suppress fog/invisibility Concealed); source = `fog` not `darkness`
- **Roles:** n/a (service layer)

### TC-DV-10 — Darkvision does not alter bright-light vision rules

- **AC:** `[NEW]` Darkvision has no effect in bright light; normal vision rules apply
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `VisibilitySystemTest::testDarkvisionNoEffectInBrightLight()`
- **Setup:** Character with `senses = ['darkvision']`; room lighting = `bright`
- **Expected:** No Concealed, no flat_footed — same as character without darkvision; darkvision provides zero additional/different behavior in bright light
- **Roles:** n/a (service layer)

### TC-DV-11 — Null senses field does not crash encounter visibility check

- **AC:** `[TEST-ONLY]` Missing or null `senses` field on a character does not crash encounter visibility checks
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `VisibilitySystemTest::testNullSensesFieldDoesNotCrashVisibilityCheck()`
- **Setup:** Character with no `senses` key in data (null/missing); room lighting = `darkness`; call visibility condition check
- **Expected:** No PHP exception or error; Concealed condition applied as baseline (null treated as no special senses)
- **Roles:** n/a (service layer)

### TC-DV-12 — Adding darkvision at ancestry swap does not corrupt existing character state

- **AC:** `[TEST-ONLY]` Adding darkvision to an existing character at ancestry swap does not corrupt prior state
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `SenseIntegrationTest::testAncestrySwapAddsDarkvisionWithoutCorruption()`
- **Setup:** Character with no darkvision; swap ancestry to Dwarf (which has darkvision); reload character state
- **Expected:** Character gains `darkvision` in senses; all prior character state (skills, HP, conditions) unchanged; no data loss
- **Roles:** n/a (service layer)

### TC-DV-13 — Character sheet API includes senses field with darkvision for Dwarf character

- **AC:** Authenticated user sees darkvision trait on their character sheet
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `CharacterViewControllerTest::testCharacterSheetIncludesSensesForDwarf()`
- **Setup:** Authenticated GET to character sheet endpoint for a Dwarf character
- **Expected:** Response includes `perception.senses` array containing `'darkvision'`; no 500; no missing field
- **Roles:** authenticated player (owner)

### TC-DV-14 — Anonymous user can view darkvision on public character sheet

- **AC:** Anonymous user: character sheet viewer respects darkvision display (if public-facing)
- **Suite:** `role-url-audit`
- **Entry:** `GET /character/{id}` — anonymous 200; `perception.senses` field present in response
- **Notes:** Parameterized path; use `ignore` in route-scan; verify via functional test or manual check. If character sheet is not public-facing, document explicit access policy and skip.
- **Roles:** anonymous

### TC-DV-15 — Admin can add/edit sense entities via Drupal admin UI

- **AC:** Admin: can add/edit sense entities via Drupal admin UI
- **Suite:** `role-url-audit`
- **Entry:** Sense entity admin route (path TBD by Dev — confirm at Stage 0). Expected: admin 200, authenticated player 403, anonymous 403.
- **Notes:** Route path must be confirmed by Dev at Stage 0 activation. Add as role-url-audit entry at that time.
- **Roles:** admin, authenticated player (forbidden), anonymous (forbidden)

---

## Items not expressable as automation

| AC item | Reason |
|---|---|
| "Vision in darkness is black-and-white" (display/rendering) | Automation can verify the `vision_in_darkness: black_and_white` effect constant exists on the entity; actual visual rendering in a game UI requires Playwright or manual review once the dungeon renderer implements it. |
| Admin sense entity CRUD via UI | Route path is TBD (Dev must create the sense admin route); TC-DV-15 entry path must be confirmed at Stage 0 before rule is added to `qa-permissions.json`. |
| Low-Light Vision full behavior | AC covers distinction from Darkvision; full Low-Light Vision AC is not in scope for this feature — TC-DV-03 verifies distinctness only, not full Low-Light Vision mechanics. |

---

## Regression risk areas

1. **`ConditionManager` coupling**: `concealed` and `flat_footed` are already defined in `ConditionManager.php`; the new visibility-by-senses path must route through `applyCondition()` consistently. Direct condition mutation bypassing `ConditionManager` will break dc-cr-conditions and dc-cr-encounter-rules regression.
2. **`CharacterViewController` senses rendering**: `CharacterViewController` currently reads senses from `feat_effects['senses']` (feat-based path). Dev must ensure the new standalone sense entity is surfaced in the same render path, or the character sheet will silently show no senses even when Darkvision is present.
3. **`AncestryController` plain-string `vision` field**: `AncestryController.php:71` maps `vision` to a plain string (`'senses' => $data['vision'] ?? 'normal'`). This legacy field must be migrated or coexist cleanly with the new structured `senses` array; if not handled, ancestry GET responses will return inconsistent shapes.
4. **HexMap lighting integration**: `HexMapController` tracks per-room `lighting` but doesn't call a visibility condition check. The new visibility system must be plumbed into the encounter flow without breaking existing HexMap state serialization.
5. **`dc-cr-conditions` coupling**: TC-DV-08/09 depend on `concealed` applying correctly as a baseline. If `dc-cr-conditions` refactors `ConditionManager::applyCondition()` during this release, both features' test cases are at risk.

---

## Activation checklist (for Stage 0 of next release)

When this feature is selected into scope:
1. Add `dc-cr-darkvision-phpunit` suite entry to `qa-suites/products/dungeoncrawler/suite.json` with TCs above.
2. Add permission rules to `qa-permissions.json`:
   - Sense entity catalog GET (if public): `anon = allow`
   - Sense entity admin route: `anon = deny, authenticated = deny, administrator = allow`
   - Confirm TC-DV-15 admin route path with Dev before adding rule.
3. Confirm `AncestryController` plain-string `vision` migration path with Dev (regression risk #3 above).
4. Confirm Low-Light Vision sense entity is also created and added to test data.
