# Test Plan: dc-cr-heritage-system

**Owner:** qa-dungeoncrawler  
**Date:** 2026-03-28  
**Feature:** Heritage Selection System — required heritage enforcement, ancestry-filtered dropdown, server-side mismatch validation, AJAX reset on ancestry change, dropdown counts per ancestry, permissions  
**AC source:** `features/dc-cr-heritage-system/01-acceptance-criteria.md`  
**Implementation notes:** `features/dc-cr-heritage-system/02-implementation-notes.md`  

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after any form/template changes; test immediately after rebuild.
- `knowledgebase/lessons/20260228-ba-feature-type-defaults-new-without-gap-analysis.md` — gap analysis performed; feature correctly typed as enhancement (UI+data exist, enforcement missing). Not a new-feature type.
- `features/dc-cr-ancestry-system/01-acceptance-criteria.md` — ancestry selection is the upstream step; heritage dropdown must be empty until ancestry is selected.
- `features/dc-cr-character-creation/01-acceptance-criteria.md` — heritage is one step in the end-to-end creation wizard; regression risk: heritage step changes can break wizard progression.

## Gap analysis summary (from AC + impl notes)
- `CharacterManager::HERITAGES` data (28 heritages, 6 ancestries) — **Full**; TCs verify dropdown counts match constant
- AJAX dropdown filtering by ancestry — **Full** (`getHeritageOptions()` + `updateHeritageOptions()` exist); TCs verify reset + repopulation behavior
- Heritage stored in `basicInfo.heritage` — **Full** (`CharacterStateService`); TCs verify persistence and retrieval
- Heritage field required enforcement — **None** (currently Optional) → extend; TCs verify validation error
- Server-side ancestry/heritage mismatch validation — **None** → new; TCs verify 400 rejection
- Back-navigation heritage reset — **None** → new; TCs verify stale heritage does not carry over

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` (unit) | PHPUnit unit (`./vendor/bin/phpunit tests/src/Unit/Service/`) | `CharacterManager::HERITAGES` count assertions |
| `module-test-suite` (functional) | PHPUnit functional (`./vendor/bin/phpunit tests/src/Functional/`) | Form submission: required enforcement, mismatch 400, valid save, persistence, API retrieval |
| `playwright-suite` | Playwright | AJAX dropdown reset on ancestry change; back-navigation heritage reset; Human single-option display; wizard progression after valid selection |
| `role-url-audit` | `scripts/site-audit-run.sh` | Anonymous 403/redirect on character creation route |

> **Playwright dependency note:** TCs requiring AJAX interaction (TC-HS-07, TC-HS-08, TC-HS-11) require Playwright infrastructure to be set up at Stage 0. This is the same dependency flagged in dc-cr-character-creation. If Playwright is not available at Stage 0, those TCs must be documented as manually verified with evidence, not skipped silently.

---

## Test cases

### TC-HS-01 — CharacterManager::HERITAGES contains correct count per ancestry
- **AC:** `[TEST-ONLY]` Heritage options: Dwarf 4, Elf 4, Gnome 4, Goblin 4, Halfling 4, Human 1 (21 total)
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterManagerTest::testHeritageCountPerAncestry()`
- **Setup:** Access `CharacterManager::HERITAGES`; count heritage entries per ancestry key
- **Expected:** Dwarf=4, Elf=4, Gnome=4, Goblin=4, Halfling=4, Human=1; total=21
- **Roles:** n/a (data layer)

### TC-HS-02 — Heritage field is required; empty submission fails with inline error
- **AC:** `[EXTEND]` Heritage selection is required. Empty submission fails validation with: "Please select a heritage for your character."
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `CharacterCreationFormTest::testHeritageRequiredValidation()`
- **Setup:** Authenticated user submits character creation step 1 with ancestry=Dwarf and heritage=empty
- **Expected:** HTTP 200 (form reload); inline validation error "Please select a heritage for your character." displayed; no PHP 500; no character saved
- **Roles:** authenticated player

### TC-HS-03 — Server-side: mismatched heritage/ancestry returns 400 error
- **AC:** `[NEW]` Direct API/form call with heritage that doesn't belong to chosen ancestry → 400 "Invalid heritage for selected ancestry."
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `CharacterCreationFormTest::testHeritageMismatchReturns400()`
- **Setup:** Authenticated POST to character save endpoint with ancestry=Elf and heritage=forge (a Dwarf heritage id)
- **Expected:** HTTP 400 (or Form API error equivalent); message "Invalid heritage for selected ancestry."; character not saved
- **Roles:** authenticated player

### TC-HS-04 — Valid heritage/ancestry combination saves successfully
- **AC:** `[TEST-ONLY]` Saving with valid heritage+ancestry persists and is retrievable via GET /character/{id}
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `CharacterCreationFormTest::testValidHeritageSavesSuccessfully()`
- **Setup:** Authenticated submit with ancestry=Dwarf, heritage=forge; complete step 1
- **Expected:** Character `basicInfo.heritage` = `forge`; `GET /character/{id}` response includes `heritage: "forge"`
- **Roles:** authenticated player

### TC-HS-05 — Heritage id stored is from approved HERITAGES list (never free-form text)
- **AC:** `[TEST-ONLY]` Heritage id stored is from approved list
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `CharacterCreationFormTest::testHeritageStoredAsId()`
- **Setup:** Submit with ancestry=Gnome, heritage=chameleon (a valid Gnome heritage id); retrieve saved character
- **Expected:** `basicInfo.heritage` = `"chameleon"` (id); not `"Chameleon Gnome"` (display name); id is a known key in `CharacterManager::HERITAGES['Gnome']`
- **Roles:** authenticated player

### TC-HS-06 — Character summary displays heritage name (not id)
- **AC:** `[TEST-ONLY]` Character profile shows "Ancient-Blooded Dwarf" (name), not `ancient-blooded` (id)
- **Suite:** `playwright-suite`
- **Test class/method:** `CharacterCreationFlow::testCharacterSummaryDisplaysHeritageName()`
- **Setup:** Complete character creation with ancestry=Dwarf, heritage=ancient-blooded; navigate to character profile
- **Expected:** Profile page displays "Ancient-Blooded Dwarf" or equivalent display name; raw id `ancient-blooded` is not shown to user
- **Roles:** authenticated player

### TC-HS-07 — AJAX: heritage dropdown repopulates when ancestry changes
- **AC:** `[EXTEND]` When ancestry changes via AJAX, heritage dropdown resets and repopulates with matching options
- **Suite:** `playwright-suite`
- **Test class/method:** `CharacterCreationFlow::testAjaxHeritageDropdownRepopulatesOnAncestryChange()`
- **Setup:** Select Dwarf ancestry; verify 4 heritage options appear; change ancestry to Elf; observe dropdown
- **Expected:** Heritage dropdown clears and repopulates with 4 Elf-specific options; no Dwarf heritages visible after Elf selection
- **Roles:** authenticated player

### TC-HS-08 — AJAX: changing ancestry resets previously selected heritage
- **AC:** `[EXTEND]` Heritage dropdown resets when ancestry changes; stale value does not persist
- **Suite:** `playwright-suite`
- **Test class/method:** `CharacterCreationFlow::testAjaxHeritageClearsOnAncestryChange()`
- **Setup:** Select Dwarf; select forge heritage; change ancestry to Goblin; inspect dropdown value
- **Expected:** Dropdown value is empty/placeholder (not `forge`); no cross-ancestry heritage value retained
- **Roles:** authenticated player

### TC-HS-09 — Human ancestry shows exactly one heritage option (Versatile Heritage)
- **AC:** `[TEST-ONLY]` Human ancestry: dropdown shows exactly 1 option (+placeholder); auto-select acceptable
- **Suite:** `module-test-suite` (unit) + `playwright-suite`
- **Unit test class/method:** `CharacterManagerTest::testHumanHasOneHeritage()`
- **Playwright test:** `CharacterCreationFlow::testHumanAncestryShowsOneHeritageOption()`
- **Setup (unit):** `count(CharacterManager::HERITAGES['Human'])` = 1; key = `versatile-heritage`
- **Setup (Playwright):** Select Human ancestry; count heritage dropdown options (excluding placeholder)
- **Expected:** Unit: exactly 1 Human heritage. Playwright: exactly 1 option rendered, labeled "Versatile Heritage"
- **Roles:** authenticated player

### TC-HS-10 — Valid selection allows progression to next wizard step
- **AC:** `[TEST-ONLY]` Completed ancestry + heritage step allows progression to next step (class/background)
- **Suite:** `playwright-suite`
- **Test class/method:** `CharacterCreationFlow::testValidHeritageAllowsWizardProgression()`
- **Setup:** Complete step 1 with ancestry=Halfling, heritage=gutsy; click Continue/Next
- **Expected:** Wizard advances to step 2 (class selection); no validation error shown; step 1 state preserved in `CharacterStateService`
- **Roles:** authenticated player

### TC-HS-11 — Back-navigation clears stale heritage from previous ancestry
- **AC:** `[NEW]` If player navigates back to step 1 after completing it, heritage field resets and must be re-selected; stale value from prior ancestry does not carry over
- **Suite:** `playwright-suite`
- **Test class/method:** `CharacterCreationFlow::testBackNavigationClearsHeritageField()`
- **Setup:** Complete step 1 with Dwarf/forge; advance to step 2; navigate back to step 1; change ancestry to Gnome; inspect heritage dropdown
- **Expected:** Heritage dropdown is empty (not `forge`); Gnome heritages shown; user must re-select
- **Roles:** authenticated player

### TC-HS-12 — Anonymous user cannot access character creation form
- **AC:** `[TEST-ONLY]` Anonymous user → 403 or redirect to login on `/dungeoncrawler/character/create`
- **Suite:** `role-url-audit`
- **Entry:** `GET /dungeoncrawler/character/create` — HTTP 403 or 302→login for anonymous role
- **Expected:** Anonymous: 403 or redirect; authenticated: 200
- **Roles:** anonymous

### TC-HS-13 — Authenticated user can complete heritage selection
- **AC:** `[TEST-ONLY]` Authenticated user and admin can both complete heritage selection
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `CharacterCreationFormTest::testAuthenticatedUserCanSelectHeritage()`
- **Setup:** Authenticated POST with ancestry=Goblin, heritage=unbreakable; verify success
- **Expected:** HTTP 200 (step advance or success); no 403; heritage saved correctly
- **Roles:** authenticated player, admin

### TC-HS-14 — Player cannot change another player's heritage after creation
- **AC:** `[TEST-ONLY]` Player cannot change another player's heritage (character ownership regression check)
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `CharacterCreationFormTest::testPlayerCannotChangeOtherPlayersHeritage()`
- **Setup:** Player A owns character; Player B attempts to submit a heritage change for Player A's character
- **Expected:** 403 or authorization exception; heritage unchanged
- **Roles:** authenticated player (other's character)

### TC-HS-15 — QA audit still passes after module deployment
- **AC:** QA automated audit must still pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler` (local dev)
- **Expected:** 0 violations, 0 failures; `/dungeoncrawler/character/create` correctly shows 403 for anonymous
- **Roles:** all 6 roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| "Error message is displayed inline, not as a 500 page" | Automation verifies HTTP status and that no 500 occurs; visual inline display position requires Playwright screenshot review or manual confirmation. |
| Heritage ability mechanics (e.g., Forge Dwarf fire resistance) | Out of scope per AC: `benefit` field is display text only; mechanical application tracked in per-heritage feature stubs. Do not add to this test plan. |
| Auto-select for Human's single heritage | "Auto-select is acceptable" (AC language) means this is a design decision, not a testable pass/fail criterion. Playwright verifies the single option is visible; whether it is pre-selected is a UI preference, not an AC requirement. |

---

## Regression risk areas

1. `dc-cr-ancestry-system` coupling: heritage dropdown population depends on the currently selected ancestry value; if `updateHeritageOptions()` AJAX callback interface changes, TC-HS-07 and TC-HS-08 will silently break.
2. `dc-cr-character-creation` wizard coupling: making heritage required (TC-HS-02) changes the wizard step 1 submission contract; any other step relying on partial-save state (no heritage) will now fail. Regression test the full wizard flow after this change.
3. `CharacterStateService` state persistence: heritage is stored in `basicInfo.heritage`; if the state service refactors its JSON structure, TC-HS-04 and TC-HS-05 will catch storage regressions.
4. Server-side validation gate: the new mismatch check (TC-HS-03) must run even on direct API calls (not just form submissions); if the validate handler only fires via Form API and not the REST endpoint, direct API bypass remains open.
5. Cache invalidation: `drush cr` required after any changes to `CharacterCreationStepForm.php`; without cache clear, old optional-heritage form definition may persist and TC-HS-02 will incorrectly pass.
