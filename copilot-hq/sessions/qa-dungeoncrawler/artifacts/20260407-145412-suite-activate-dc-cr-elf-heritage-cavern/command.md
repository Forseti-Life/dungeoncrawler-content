# Suite Activation: dc-cr-elf-heritage-cavern

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T14:54:12+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-elf-heritage-cavern"`**  
   This links the test to the living requirements doc at `features/dc-cr-elf-heritage-cavern/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-elf-heritage-cavern-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-elf-heritage-cavern",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-elf-heritage-cavern"`**  
   Example:
   ```json
   {
     "id": "dc-cr-elf-heritage-cavern-<route-slug>",
     "feature_id": "dc-cr-elf-heritage-cavern",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-elf-heritage-cavern",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-elf-heritage-cavern

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-06
**Feature:** Cavern Elf Heritage — replace Low-Light Vision with Darkvision when Cavern Elf heritage selected on an Elf-ancestry character
**AC source:** `features/dc-cr-elf-heritage-cavern/01-acceptance-criteria.md`
**Status:** groomed (next-release; DO NOT add to suite.json until Stage 0 activation)

---

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after any entity/module changes; test immediately after rebuild.
- No prior lessons for elf-specific heritage sense replacement. Pattern follows `dc-cr-darkvision` and `dc-cr-heritage-system`.

## Implementation context (gap analysis findings)

Codebase scan of `dungeoncrawler_content`:

- `CharacterManager::HERITAGES['Elf']` (line 219) already defines `['id' => 'cavern', 'name' => 'Cavern Elf', 'benefit' => 'Darkvision']` — heritage catalog entry exists.
- `CharacterCreationStepForm.php` renders heritage options from `HERITAGES` for the selected ancestry and saves the `heritage` field on the character. Heritage is selected during character creation, not via a separate endpoint.
- `FeatEffectManager::addSense()` is the mechanism for assigning senses to a character. The `orc-sight` case (line 883) uses `addSense($effects, 'darkvision', ...)` as a pattern. There is currently **no `cavern` case** in `FeatEffectManager` to apply the sense replacement — this is the primary new implementation needed.
- `AncestryController` maps `ANCESTRIES['vision']` → `senses` as a string. Elf has `vision: 'low-light vision'`. For Cavern Elf, the implementation must override this with darkvision and suppress low-light vision.
- **Route clarification needed:** The AC specifies route `/dungeoncrawler/character/{id}/heritage` as a POST endpoint for heritage updates. This route does **not exist** in `dungeoncrawler_content.routing.yml`. Current heritage selection happens within the character creation form at `/dungeoncrawler/character/create`. PM should confirm whether heritage-update-post-creation is in scope for this feature or if tests should cover only the creation-time heritage path.

**Note to PM:** TC-EC-08 and TC-EC-09 (heritage update via dedicated POST endpoint) are flagged as implementation-dependent. If the `/dungeoncrawler/character/{id}/heritage` POST route is not being implemented in this feature (i.e., heritage is only selectable at character creation), remove or defer those TCs at Stage 0 activation.

---

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` (unit) | PHPUnit unit (`./vendor/bin/phpunit tests/src/Unit/`) | Heritage catalog data, sense replacement logic (low-light removed, darkvision added), ancestry restriction check |
| `module-test-suite` (functional) | PHPUnit functional (`./vendor/bin/phpunit tests/src/Functional/`) | Full character creation with Cavern Elf heritage, persistence, revert-on-removal, validation errors |
| `role-url-audit` | `scripts/site-audit-run.sh` | Character create form access (anon deny / auth allow); optional: POST heritage endpoint if route is implemented |
| Playwright `e2e` | Playwright | E2E selection of Cavern Elf during character creation and character sheet sense verification |

---

## Test cases

### TC-EC-01 — Cavern Elf heritage exists in Elf heritage catalog
- **Suite:** unit
- **class_method:** `CharacterManagerTest::testCavernElfHeritageInCatalog`
- **ac_tag:** happy-path
- **Verify:** `CharacterManager::HERITAGES['Elf']` contains an entry with `id === 'cavern'`, `name === 'Cavern Elf'`; heritage is absent from non-Elf ancestry catalogs (e.g., Dwarf)
- **Expected:** Unit PASS — catalog entry present for Elf only
- **Roles:** N/A

### TC-EC-02 — Selecting Cavern Elf sets darkvision and clears low-light vision on character
- **Suite:** unit / functional
- **class_method:** `CavernElfHeritageTest::testSenseReplacementOnHeritageSelection`
- **ac_tag:** happy-path
- **Verify:** After character creation with Elf ancestry + Cavern Elf heritage: `char_data['senses']` contains `darkvision`; does NOT contain `low-light-vision`
- **Expected:** Functional PASS — exactly one active vision sense: darkvision
- **Roles:** authenticated (dc_playwright_player)

### TC-EC-03 — Sense replacement applied at character creation time
- **Suite:** functional
- **class_method:** `CavernElfHeritageTest::testSenseReplacementAppliedAtCreation`
- **ac_tag:** happy-path
- **Verify:** Full character creation POST with Elf + Cavern Elf heritage; character entity saved to DB; retrieve via API and confirm senses field has darkvision, not low-light-vision
- **Expected:** HTTP 200 + character persisted with correct senses
- **Roles:** authenticated

### TC-EC-04 — Cavern Elf darkvision behaves identically to dc-cr-darkvision implementation
- **Suite:** functional
- **class_method:** `CavernElfHeritageTest::testCavernElfDarkvisionMatchesDwarfDarkvision`
- **ac_tag:** happy-path (extend)
- **Verify:** Elf with Cavern Elf heritage and Dwarf (baseline darkvision character) both produce `darkvision` sense in `char_data['senses']` with identical structure; no new darkvision logic was introduced
- **Expected:** Functional PASS — senses structure identical between both characters
- **Roles:** authenticated

### TC-EC-05 — Cavern Elf heritage only selectable for Elf ancestry
- **Suite:** unit / functional
- **class_method:** `CavernElfHeritageTest::testCavernElfRestrictedToElfAncestry`
- **ac_tag:** edge-case
- **Verify:** Attempt to create a Dwarf character with `heritage=cavern`; expect validation error — cavern is not in Dwarf's heritage list
- **Expected:** Validation error returned; character creation blocked; no 500
- **Roles:** authenticated

### TC-EC-06 — Cavern Elf cannot be combined with other elf heritages (one heritage per character)
- **Suite:** functional
- **class_method:** `CavernElfHeritageTest::testOneHeritagePerCharacterEnforced`
- **ac_tag:** edge-case
- **Verify:** Attempt to submit character creation with Elf + two heritage values (e.g., `heritage=cavern` and `heritage=arctic` simultaneously); expect validation error, not silent override
- **Expected:** Validation error; only one heritage accepted per character per ancestry
- **Roles:** authenticated

### TC-EC-07 — Cavern Elf character persists darkvision: true, low_light_vision: false across save/reload
- **Suite:** functional
- **class_method:** `CavernElfHeritageTest::testCavernElfSensePersistence`
- **ac_tag:** edge-case (test-only)
- **Verify:** Create Cavern Elf character, save, load via character API; assert senses have darkvision present and low-light-vision absent; no data corruption on reload
- **Expected:** Functional PASS — senses stable across persist/load cycle
- **Roles:** authenticated

### TC-EC-08 — Removing Cavern Elf heritage reverts to low_light_vision (ancestry default)
- **Suite:** functional
- **class_method:** `CavernElfHeritageTest::testHeritageRemovalRevertsVision`
- **ac_tag:** edge-case
- **status:** implementation-dependent — requires the heritage-update or character-rebuild flow to be defined. If heritage can only be set at creation (no post-creation update route), defer this TC.
- **Verify:** After a heritage rebuild/update removing Cavern Elf from an Elf character: senses reverts to `low-light-vision: true`, `darkvision: false`
- **Expected:** Functional PASS — ancestral default vision restored
- **Roles:** authenticated

### TC-EC-09 — Anonymous POST to heritage endpoint returns 403 (route-level auth guard)
- **Suite:** acl
- **class_method:** `CavernElfHeritageTest::testAnonHeritagePostBlocked`
- **ac_tag:** permissions
- **status:** implementation-dependent — only applicable if the dedicated POST `/dungeoncrawler/character/{id}/heritage` route is implemented. If heritage is creation-only, map to TC-EC-12 instead.
- **Verify:** POST to `/dungeoncrawler/character/{id}/heritage` as anonymous; expect 403 or 302
- **Expected:** 403 or 302; not 200
- **Roles:** anonymous

### TC-EC-10 — Heritage selection for invalid/non-existent heritage ID returns validation error (not 500)
- **Suite:** functional
- **class_method:** `CavernElfHeritageTest::testInvalidHeritageIdReturnsValidationError`
- **ac_tag:** failure-mode
- **Verify:** Submit character creation with Elf ancestry and `heritage=nonexistent_heritage`; expect validation error returned cleanly, never 500
- **Expected:** Validation error; HTTP 422 or form re-render with error message
- **Roles:** authenticated

### TC-EC-11 — Heritage ownership check: user cannot mutate another user's character heritage
- **Suite:** functional
- **class_method:** `CavernElfHeritageTest::testCrossCharacterHeritageMutationBlocked`
- **ac_tag:** permissions
- **Verify:** Authenticated user A attempts to set heritage on character owned by user B; expect 403 or ownership validation error
- **Expected:** 403 or equivalent; no cross-character mutation
- **Roles:** authenticated (two separate users)

### TC-EC-12 — Anonymous user cannot access character creation form (GET 302/403)
- **Suite:** acl
- **class_method:** `CavernElfHeritageTest::testAnonDeniedCharacterCreate`
- **ac_tag:** permissions
- **Verify:** GET `/dungeoncrawler/character/create` as anonymous; expect 302 or 403
- **Expected:** 302 or 403
- **Roles:** anonymous
- **Note:** Rule `dc-cr-heritage-system-character-create-form` already exists in `qa-permissions.json`. Verify at Stage 0; do not duplicate if already present.

### TC-EC-13 — E2E: Cavern Elf selection in browser produces correct character sheet
- **Suite:** e2e
- **class_method:** `tests/e2e/cavern-elf-heritage.spec.js`
- **ac_tag:** happy-path
- **Verify:** Playwright: log in → navigate to character create → select Elf ancestry → select Cavern Elf heritage → complete creation → assert character sheet shows Darkvision (not Low-Light Vision) in senses section
- **Expected:** E2E PASS — character sheet renders correct sense
- **Roles:** dc_playwright_player

---

## Implementation-dependent test cases summary

| TC | Condition | Unblock |
|---|---|---|
| TC-EC-08 | Requires heritage removal/rebuild flow (post-creation) | PM/Dev to clarify if in scope |
| TC-EC-09 | Requires `/dungeoncrawler/character/{id}/heritage` POST route | PM/Dev to confirm if route is implemented in this feature |

## Notes to PM

1. **Heritage update route scope**: The AC specifies a POST `/dungeoncrawler/character/{id}/heritage` route for "heritage update" (creation or post-creation update). This route does **not exist** in current routing. If this feature only covers heritage selection at character creation time (same route as dc-cr-character-creation), TC-EC-08 and TC-EC-09 should be deferred until a post-creation heritage update flow is implemented. Clarify at Stage 0.

2. **Sense replacement mechanism**: `FeatEffectManager` handles senses via `addSense()`. There is currently no `cavern` case. Dev must add a case that (a) adds darkvision and (b) removes low-light-vision from `$effects['senses']`. The `removeSense()` helper may need to be added — no such helper exists in the current `FeatEffectManager`. Flag to Dev at Stage 0.

3. **`qa-permissions.json` heritage rule**: The rule `dc-cr-heritage-system-character-create-form` (GET `/dungeoncrawler/character/create`) already exists. At Stage 0, only add a new rule if the POST heritage endpoint route is implemented as a distinct URL. Do not duplicate the creation form rule.

## Route permission expectations (for qa-permissions.json at Stage 0)

| Rule ID | Route | Method | Notes |
|---|---|---|---|
| (verify existing) `dc-cr-heritage-system-character-create-form` | `/dungeoncrawler/character/create` | GET | Already in `qa-permissions.json`; verify, do not duplicate |
| `dc-cr-elf-heritage-cavern-update` | `/dungeoncrawler/character/{id}/heritage` | POST | Only add if route is implemented; anon→403, auth→200 |

### Acceptance criteria (reference)

# Acceptance Criteria — dc-cr-elf-heritage-cavern

- Feature: Cavern Elf Heritage
- Release target: 20260406-dungeoncrawler-release-c
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-06

## Scope

When an elf character selects the Cavern Elf heritage, replace their default Low-Light Vision with Darkvision. The two senses are mutually exclusive — Cavern Elf upgrades rather than adds.

## Prerequisites satisfied

- dc-cr-elf-ancestry: planned (release-c, same cycle) — must be implemented first or in same release
- dc-cr-heritage-system: in_progress (release-b) — must ship before release-c activates
- dc-cr-darkvision: ready — already implemented

## Knowledgebase check

None found. Pattern follows dc-cr-darkvision sense-flag assignment.

## Happy Path

- [ ] `[NEW]` Cavern Elf heritage record exists for the Elf ancestry.
- [ ] `[NEW]` When Cavern Elf is selected, character's `low_light_vision` flag is set to `false` and `darkvision` flag is set to `true`.
- [ ] `[NEW]` Sense replacement is applied at heritage selection time (character creation or heritage update).
- [ ] `[EXTEND]` Darkvision behavior is identical to what was implemented in dc-cr-darkvision (no new darkvision logic needed).
- [ ] `[NEW]` Selecting Cavern Elf heritage is only available to Elf-ancestry characters.

## Edge Cases

- [ ] `[NEW]` A non-Elf character cannot select Cavern Elf heritage (validation error).
- [ ] `[NEW]` Cavern Elf heritage cannot be combined with other elf heritages (one heritage per character per ancestry).
- [ ] `[TEST-ONLY]` After selecting Cavern Elf, character persists `darkvision: true, low_light_vision: false` across save/reload.
- [ ] `[NEW]` If Cavern Elf is removed (e.g., character rebuild), `darkvision` reverts to `false` and `low_light_vision` reverts to the elf default (`true`).

## Failure Modes

- [ ] `[NEW]` Heritage selection for invalid ancestry returns a validation error (not a 500).
- [ ] `[NEW]` Selecting a second heritage when one is already set is rejected.

## Permissions / Access Control

- [ ] Heritage selection available to authenticated users owning the character.
- [ ] Anonymous users cannot access heritage selection endpoints (403).

### Route permission expectations (required for qa-permissions.json)

| Route | HTTP method | Permission | anon | authenticated | content_editor | administrator | dc_playwright_player | dc_playwright_admin |
|---|---|---|---|---|---|---|---|---|
| `/dungeoncrawler/character/{id}/heritage` | `POST` | `access dungeoncrawler characters` | deny | allow | allow | allow | allow | allow |

## Security acceptance criteria

- [ ] POST `/dungeoncrawler/character/{id}/heritage` MUST have `_csrf_request_header_mode: TRUE`.
- [ ] Heritage POST validates that the authenticated user owns the character (no cross-character heritage mutation).
- [ ] Heritage ID is validated server-side against the ancestry's permitted heritage list before application.
- [ ] Anonymous heritage POST returns 403.
