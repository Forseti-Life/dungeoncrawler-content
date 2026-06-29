# Test Plan: dc-cr-elf-ancestry

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-06
**Feature:** Elf Ancestry — HP 6, Speed 30, Dex+Int boosts, free boost, Constitution flaw, Low-Light Vision, Common+Elven languages + Int-modifier additional languages
**AC source:** `features/dc-cr-elf-ancestry/01-acceptance-criteria.md`
**Status:** groomed (next-release; DO NOT add to suite.json until Stage 0 activation)

---

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after any entity/module changes; test immediately after rebuild.
- No prior lessons for elf ancestry specifically. Pattern follows dwarf ancestry implementation.

## Implementation context (gap analysis findings)

Codebase scan of `dungeoncrawler_content`:

- `CharacterManager::ANCESTRIES` (line 25) already defines Elf: `['hp' => 6, 'size' => 'Medium', 'speed' => 30, 'boosts' => ['Dexterity', 'Intelligence'], 'flaw' => 'Constitution', 'languages' => ['Common', 'Elven'], 'traits' => ['Elf', 'Humanoid'], 'vision' => 'low-light vision']`
- `dungeoncrawler_content.install` seeds 6 core ancestries including Elf. `field_ancestry_languages`, `field_ancestry_senses`, `field_ancestry_boosts`, `field_ancestry_flaws` fields exist.
- `CharacterCreationStepForm.php` handles free boosts (`free_boosts` field) and Int-modifier-based skill picks but does **not** show Int-modifier-based additional language selection yet.
- **`dc-cr-languages` is deferred** (status: deferred). The Int-modifier-based additional language selection slot system is NOT yet implemented. TCs TC-EA-11 through TC-EA-13 require this system; they are flagged `deferred` and must be re-evaluated at Stage 0 when `dc-cr-languages` status is known.
- `dc-cr-low-light-vision` is planned (same release-c cycle). Low-Light Vision is currently stored as a plain string in `ANCESTRIES['vision']`. The structured sense entity path for Low-Light Vision must be clarified at Stage 0 — Dev should confirm whether LLV will use the same `sense` entity model as Darkvision (`dc-cr-darkvision`) or remain a string flag.

**Note to PM:** TC-EA-11, TC-EA-12, TC-EA-13 (Int-modifier language slots) are deferred until `dc-cr-languages` is re-activated. If elf ships without the full languages system, these TCs are out-of-scope for that release.

---

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` (unit) | PHPUnit unit (`./vendor/bin/phpunit tests/src/Unit/`) | Ancestry catalog data, flaw/boost constants, language slot calculation, low-light vision flag |
| `module-test-suite` (functional) | PHPUnit functional (`./vendor/bin/phpunit tests/src/Functional/`) | Full character creation flow, persistence, language selection validation, error handling |
| `role-url-audit` | `scripts/site-audit-run.sh` | Character create route (anon deny, auth allow); character sheet senses display |
| Playwright `e2e` | Playwright | Full Elf ancestry E2E character creation including free boost and optional language picks |

---

## Test cases

### TC-EA-01 — Elf ancestry record exists in catalog with correct HP, Speed, Size
- **Suite:** unit
- **class_method:** `CharacterManagerTest::testElfAncestryStatBlock`
- **ac_tag:** happy-path
- **Verify:** `CharacterManager::ANCESTRIES['Elf']['hp'] === 6`, `speed === 30`, `size === 'Medium'`
- **Expected:** Unit PASS — constants match PF2E source
- **Roles:** N/A (data layer)

### TC-EA-02 — Elf has correct fixed ability boosts (Dexterity, Intelligence)
- **Suite:** unit
- **class_method:** `CharacterManagerTest::testElfAncestryBoosts`
- **ac_tag:** happy-path
- **Verify:** `ANCESTRIES['Elf']['boosts']` = `['Dexterity', 'Intelligence']` (exactly; no 'Free' in fixed boosts)
- **Expected:** Unit PASS
- **Roles:** N/A

### TC-EA-03 — Elf applies Constitution flaw at character creation
- **Suite:** unit
- **class_method:** `CharacterManagerTest::testElfAncestryFlaw`
- **ac_tag:** happy-path
- **Verify:** `ANCESTRIES['Elf']['flaw'] === 'Constitution'`; character CON final = base CON − 2
- **Expected:** Unit PASS
- **Roles:** N/A

### TC-EA-04 — Elf grants Low-Light Vision sense
- **Suite:** unit
- **class_method:** `CharacterManagerTest::testElfAncestryLowLightVision`
- **ac_tag:** happy-path
- **Verify:** `ANCESTRIES['Elf']['vision'] === 'low-light vision'`; after character creation, `char_data['senses']` contains low-light vision entry
- **Expected:** Unit PASS — **Note to PM:** if `dc-cr-low-light-vision` formalizes LLV as a `sense` entity (like Darkvision), update assertion to check entity reference rather than plain string
- **Roles:** N/A

### TC-EA-05 — Elf has base languages Common + Elven
- **Suite:** unit
- **class_method:** `CharacterManagerTest::testElfAncestryBaseLanguages`
- **ac_tag:** happy-path
- **Verify:** `ANCESTRIES['Elf']['languages']` = `['Common', 'Elven']` (both present, no extras in base)
- **Expected:** Unit PASS
- **Roles:** N/A

### TC-EA-06 — Elf ancestry traits applied automatically (["Elf", "Humanoid"])
- **Suite:** unit
- **class_method:** `CharacterManagerTest::testElfAncestryTraits`
- **ac_tag:** happy-path
- **Verify:** `ANCESTRIES['Elf']['traits']` = `['Elf', 'Humanoid']`; after creation, character traits include both
- **Expected:** Unit PASS
- **Roles:** N/A

### TC-EA-07 — Free boost cannot target Dexterity or Intelligence (already boosted)
- **Suite:** unit / functional
- **class_method:** `CharacterCreationStepFormTest::testElfFreeBoostRejectsAlreadyBoostedAbility`
- **ac_tag:** edge-case
- **Verify:** Submit free boost selection targeting Dexterity or Intelligence; expect validation error returned, not 200 with data persisted
- **Expected:** Validation error "Dexterity is already boosted by your ancestry" (or equivalent); character creation blocked until valid free boost chosen
- **Roles:** authenticated

### TC-EA-08 — Language slot count = max(0, Int modifier); zero/negative Int gives no additional slots
- **Suite:** unit
- **class_method:** `CharacterCreationStepFormTest::testElfLanguageSlotCountFromIntModifier`
- **ac_tag:** edge-case
- **status:** deferred — depends on `dc-cr-languages` (currently deferred); re-evaluate at Stage 0
- **Verify:** Character with Int 8 (−1 modifier) → 0 additional language slots; Int 14 (+2) → 2 additional slots
- **Expected:** Unit PASS once dc-cr-languages ships
- **Roles:** N/A

### TC-EA-09 — Full Elf character creation produces valid character with all stat fields populated
- **Suite:** functional
- **class_method:** `ElfAncestryCreationTest::testFullElfCharacterCreation`
- **ac_tag:** happy-path
- **Verify:** POST character creation with Elf ancestry, valid free boost, no optional languages; character entity has: `hp=6`, `speed=30`, `size=Medium`, `boosts=[Dex+2, Int+2, <free>+2]`, `flaw=[Con−2]`, `traits=[Elf,Humanoid]`, `senses=[low-light vision]`, `languages=[Common, Elven]`
- **Expected:** HTTP 200 + character persisted with all fields correct
- **Roles:** authenticated (dc_playwright_player)

### TC-EA-10 — Elf character persists correctly across save/reload
- **Suite:** functional
- **class_method:** `ElfAncestryCreationTest::testElfCharacterPersistence`
- **ac_tag:** edge-case (test-only)
- **Verify:** Create Elf character, save, reload via character sheet API; verify all boosts, flaw, traits, senses, languages are identical before/after reload
- **Expected:** Functional PASS — no data loss on persist/load cycle
- **Roles:** authenticated

### TC-EA-11 — Additional language from permitted list accepted
- **Suite:** functional
- **class_method:** `ElfAncestryCreationTest::testAdditionalLanguageFromPermittedList`
- **ac_tag:** happy-path
- **status:** deferred — depends on `dc-cr-languages`
- **Verify:** Character with Int +1 modifier can select one additional language from [Celestial, Draconic, Gnoll, Gnomish, Goblin, Orcish, Sylvan]; language added to `languages` array
- **Expected:** HTTP 200 + language persisted
- **Roles:** authenticated

### TC-EA-12 — Language not on permitted list rejected with validation error
- **Suite:** functional
- **class_method:** `ElfAncestryCreationTest::testAdditionalLanguageFromUnpermittedListRejected`
- **ac_tag:** edge-case
- **status:** deferred — depends on `dc-cr-languages`
- **Verify:** Submit `additional_language=Abyssal` (not in elf's allowed list); expect validation error, no 500, language not persisted
- **Expected:** Validation error; HTTP 422 or form re-render with error message
- **Roles:** authenticated

### TC-EA-13 — Duplicate language selection rejected (no duplicates of Common or Elven)
- **Suite:** functional
- **class_method:** `ElfAncestryCreationTest::testDuplicateLanguageSelectionRejected`
- **ac_tag:** edge-case
- **status:** deferred — depends on `dc-cr-languages`
- **Verify:** Submit additional language = `Common` (already granted); expect rejection with clear error
- **Expected:** Validation error "Common is already known"; character languages unchanged
- **Roles:** authenticated

### TC-EA-14 — Invalid language selection returns validation error, not 500
- **Suite:** functional
- **class_method:** `ElfAncestryCreationTest::testInvalidLanguageSelectionNoServerError`
- **ac_tag:** failure-mode
- **status:** deferred — depends on `dc-cr-languages`
- **Verify:** Submit malformed language string (e.g., `<script>alert(1)</script>`); expect HTTP 422 or form re-render with validation error, never 500
- **Expected:** No 500; validation error returned cleanly
- **Roles:** authenticated

### TC-EA-15 — Missing free boost selection blocks character save
- **Suite:** functional
- **class_method:** `ElfAncestryCreationTest::testMissingFreeBoostBlocksSave`
- **ac_tag:** failure-mode
- **Verify:** Submit character creation form with Elf ancestry but no free boost selected (empty `free_boosts`); expect form validation error and save blocked
- **Expected:** HTTP 200 with form re-render + error "Please select a free ability boost"; character not persisted
- **Roles:** authenticated

### TC-EA-16 — Anonymous user cannot access character creation route (GET returns 302/403)
- **Suite:** acl
- **class_method:** `ElfAncestryPermissionsTest::testAnonDeniedCharacterCreate`
- **ac_tag:** permissions
- **Verify:** GET `/dungeoncrawler/character/create` as anonymous; expect 302 (redirect to login) or 403
- **Expected:** 302 or 403; not 200
- **Roles:** anonymous

### TC-EA-17 — Authenticated user can access character creation route (GET 200)
- **Suite:** acl
- **class_method:** `ElfAncestryPermissionsTest::testAuthUserAllowedCharacterCreate`
- **ac_tag:** permissions
- **Verify:** GET `/dungeoncrawler/character/create` as authenticated user; expect 200
- **Expected:** 200
- **Roles:** authenticated, dc_playwright_player, content_editor, administrator

### TC-EA-18 — E2E: Full Elf ancestry character creation flow in browser
- **Suite:** e2e
- **class_method:** `tests/e2e/elf-ancestry-creation.spec.js`
- **ac_tag:** happy-path
- **Verify:** Playwright: log in → navigate to character create → select Elf ancestry → assign free boost (Strength) → continue to completion → assert character sheet shows Elf traits, HP 6, Speed 30, Low-Light Vision, languages Common + Elven
- **Expected:** E2E PASS — character sheet renders correctly
- **Roles:** dc_playwright_player

---

## Deferred test cases summary

| TC | Reason | Unblock condition |
|---|---|---|
| TC-EA-08 | `dc-cr-languages` deferred | Re-activate `dc-cr-languages` before release-c |
| TC-EA-11 | `dc-cr-languages` deferred | Same |
| TC-EA-12 | `dc-cr-languages` deferred | Same |
| TC-EA-13 | `dc-cr-languages` deferred | Same |
| TC-EA-14 | `dc-cr-languages` deferred | Same |

## Notes to PM (not automation-expressible as written)

1. **`dc-cr-languages` dependency**: 5 of 18 TCs (TC-EA-08, 11–14) require the Int-modifier-based language slot system from `dc-cr-languages` (currently deferred). If elf ships without languages system, these TCs are deferred. The base 2 languages (Common + Elven) are covered by TC-EA-05 and TC-EA-09 and do not require dc-cr-languages.

2. **Low-Light Vision implementation path**: `CharacterManager::ANCESTRIES` stores LLV as a plain string `'low-light vision'`. The AC says `low_light_vision: true`. If `dc-cr-low-light-vision` formalizes this as a sense entity (like Darkvision), TC-EA-04 and TC-EA-09 assertions need to be updated at Stage 0 to check entity reference rather than string. Flag this dependency at Stage 0 activation.

3. **Security AC — CSRF on POST**: AC requires `_csrf_request_header_mode: TRUE` on POST `/dungeoncrawler/character/create`. This route already exists for dc-cr-character-creation (same route). Verify at Stage 0 that the CSRF rule is present and is not accidentally relaxed by the elf ancestry implementation.

## Route permission expectations (for qa-permissions.json at Stage 0)

| Rule ID | Route | Method | anon | authenticated | content_editor | administrator | dc_playwright_player | dc_playwright_admin |
|---|---|---|---|---|---|---|---|---|
| `dc-cr-elf-ancestry-create-form` | `/dungeoncrawler/character/create` | GET | 302 | 200 | 200 | 200 | 200 | 200 |
| (POST — already covered by dc-cr-character-creation CSRF rule) | `/dungeoncrawler/character/create` | POST | ignore | 200 | 200 | 200 | 200 | 200 |

**Note:** The GET route rule `dc-cr-character-creation-form` likely already exists in `qa-permissions.json` (from dc-cr-character-creation). Verify at Stage 0 — do not duplicate if already present.
