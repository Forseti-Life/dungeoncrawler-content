# Suite Activation: dc-cr-low-light-vision

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
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-low-light-vision"`**  
   This links the test to the living requirements doc at `features/dc-cr-low-light-vision/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-low-light-vision-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-low-light-vision",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-low-light-vision"`**  
   Example:
   ```json
   {
     "id": "dc-cr-low-light-vision-<route-slug>",
     "feature_id": "dc-cr-low-light-vision",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-low-light-vision",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-low-light-vision

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-06
**Feature:** Low-Light Vision Rule System — `low_light_vision` sense flag; dim light treated as bright for concealment; Elf default sense; analogous to darkvision for dwarves
**AC source:** `features/dc-cr-low-light-vision/01-acceptance-criteria.md`
**Status:** groomed (next-release; DO NOT add to suite.json until Stage 0 activation)

---

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after any entity/module changes; test immediately after rebuild.
- No prior lessons for low-light-vision specifically. Pattern follows `dc-cr-darkvision` — reference `features/dc-cr-darkvision/03-test-plan.md` for parallel TCs.

## Implementation context (gap analysis findings)

Codebase scan of `dungeoncrawler_content`:

- `CharacterStateService.php` (line 952) already builds `state['senses']` from `$effects['senses'] ?? []` — senses are resolved via `FeatEffectManager` and exposed on the character state. The integration point for LLV is already wired.
- `FeatEffectManager::addSense()` (line 888) already has a case that calls `addSense($effects, 'low-light-vision', 'Low-Light Vision', 'See clearly in dim light.')` for the `feline-eyes` feat and the `draconic-scout` feat. The shared sense helper is available.
- `HexMapController.php` tracks `lighting` level per room but does **not** currently apply conditions based on character senses — this is the new integration point LLV needs.
- `AncestryController.php` maps `ANCESTRIES['vision']` → `senses` as a plain string (e.g., `'low-light vision'`). This does NOT call `FeatEffectManager::addSense()` yet — it's a display-only string. The structured sense flag path needs to be wired at character-load time.
- **`/dungeoncrawler/character/{id}/senses` route does NOT exist** in `dungeoncrawler_content.routing.yml`. This is a new route specified in the AC. Sense data is currently only available via `/api/character/{character_id}/state`.
- `ConditionManager.php` defines `concealed` and `flat_footed` conditions but has no lighting/sense check path yet. The dim-light → concealment bypass is new.

**Note to PM:** The `/dungeoncrawler/character/{id}/senses` GET route is a new endpoint. The AC requires it for session participants to read sense flags for targeting/encounter display. At Stage 0, Dev must confirm whether this is a standalone endpoint or an alias/wrapper of `/api/character/{id}/state` filtered to senses. This affects TC-LLV-11 (ACL test).

---

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` (unit) | PHPUnit unit (`./vendor/bin/phpunit tests/src/Unit/`) | Sense flag constants, dim-light concealment bypass logic, dual-flag resolution (darkvision wins), no-sense baseline |
| `module-test-suite` (functional) | PHPUnit functional (`./vendor/bin/phpunit tests/src/Functional/`) | Ancestry sense assignment, persistence, ancestry-swap clears/re-applies sense, non-existent character error, client-write rejected |
| `role-url-audit` | `scripts/site-audit-run.sh` | `/dungeoncrawler/character/{id}/senses` GET — anon deny, auth allow |
| Playwright `e2e` | Playwright | Elf character creation → character sheet shows Low-Light Vision; encounter dim-light zone does not apply Concealed |

---

## Test cases

### TC-LLV-01 — Character entity supports low_light_vision boolean sense flag stored alongside darkvision
- **Suite:** unit
- **class_method:** `LowLightVisionTest::testLowLightVisionFlagExistsOnCharacterEntity`
- **ac_tag:** happy-path
- **Verify:** `FeatEffectManager` processes `low-light-vision` sense and adds it to `$effects['senses']`; character state from `CharacterStateService` includes `senses` array with LLV entry when ancestry grants it
- **Expected:** Unit PASS — sense flag present in effects structure
- **Roles:** N/A (data layer)

### TC-LLV-02 — Elf ancestry grants low_light_vision via structured sense (not plain string)
- **Suite:** unit
- **class_method:** `LowLightVisionTest::testElfAncestryGrantsStructuredLowLightVisionSense`
- **ac_tag:** happy-path
- **Verify:** After character creation with Elf ancestry, `char_data['senses']` contains a low-light-vision entry with `id = 'low-light-vision'` (not just the plain string `'low-light vision'`); `CharacterStateService` resolves this at character load
- **Expected:** Unit PASS — structured sense entry, not display-only string
- **Roles:** N/A

### TC-LLV-03 — Characters with low_light_vision: dim light treated as bright for concealment checks
- **Suite:** unit
- **class_method:** `LowLightVisionTest::testDimLightConcealmentsuppressedByLowLightVision`
- **ac_tag:** happy-path
- **Verify:** In HexMapController or ConditionManager: when a character with `low_light_vision: true` is in a dim-light zone, the Concealed condition from dim light is NOT applied
- **Expected:** Unit PASS — concealment bypass active for LLV character in dim light
- **Roles:** N/A

### TC-LLV-04 — Characters without special vision receive Concealed in dim light (baseline unchanged)
- **Suite:** unit
- **class_method:** `LowLightVisionTest::testNormalVisionCharacterGetsConcealedInDimLight`
- **ac_tag:** edge-case
- **Verify:** Character with no sense flags (`low_light_vision: false`, `darkvision: false`) in dim-light zone receives Concealed condition from lighting
- **Expected:** Unit PASS — baseline dim-light concealment intact for normal-vision characters
- **Roles:** N/A

### TC-LLV-05 — Both flags set (data error): darkvision wins (stronger sense resolution)
- **Suite:** unit
- **class_method:** `LowLightVisionTest::testBothFlagsSetDarkvisionWins`
- **ac_tag:** edge-case
- **Verify:** Character state with both `darkvision: true` and `low_light_vision: true` resolves to darkvision behavior in darkness; no conflict error; LLV not double-applied
- **Expected:** Unit PASS — darkvision takes precedence
- **Roles:** N/A

### TC-LLV-06 — Low-light vision is distinct from darkvision (separate effects, separate conditions)
- **Suite:** unit
- **class_method:** `LowLightVisionTest::testLowLightVisionDistinctFromDarkvision`
- **ac_tag:** happy-path
- **Verify:** LLV effect: dim light → bright (concealment suppressed in dim only); DV effect: darkness → dim light equivalent (concealment suppressed in darkness). Character with only LLV still gets Concealed in darkness; character with only DV does not get Concealed in darkness OR dim light.
- **Expected:** Unit PASS — two distinct behaviors with no cross-contamination
- **Roles:** N/A

### TC-LLV-07 — Sense flag persists across save/reload
- **Suite:** functional
- **class_method:** `LowLightVisionTest::testLowLightVisionFlagPersistsAcressSaveReload`
- **ac_tag:** happy-path
- **Verify:** Create Elf character (LLV granted by ancestry); save; load via character API; `senses` array still contains low-light-vision entry; no data loss
- **Expected:** Functional PASS
- **Roles:** authenticated

### TC-LLV-08 — Changing ancestry during creation clears and re-applies correct sense flag
- **Suite:** functional
- **class_method:** `LowLightVisionTest::testAncestrySwitchClearsAndReappliesSense`
- **ac_tag:** edge-case (test-only)
- **Verify:** Start character creation with Elf (LLV granted); switch to Dwarf ancestry (darkvision granted, LLV removed); verify final character has darkvision not LLV; no stale LLV flag
- **Expected:** Functional PASS — sense flags are always current with final ancestry selection
- **Roles:** authenticated

### TC-LLV-09 — Querying senses for non-existent character returns structured error (not 500)
- **Suite:** functional
- **class_method:** `LowLightVisionTest::testNonExistentCharacterSensesReturnsStructuredError`
- **ac_tag:** failure-mode (test-only)
- **Verify:** GET `/dungeoncrawler/character/99999/senses` (or equivalent API call) for non-existent character ID; expect structured 404 JSON error, never 500
- **Expected:** HTTP 404 with JSON error body; no 500
- **Roles:** authenticated

### TC-LLV-10 — Client cannot directly mutate sense flags (server-side only)
- **Suite:** functional
- **class_method:** `LowLightVisionTest::testClientCannotDirectlyMutateSenseFlags`
- **ac_tag:** failure-mode
- **Verify:** Attempt to POST/PATCH `low_light_vision: true` directly on character entity without going through ancestry/heritage assignment; expect 403 or 405 (no direct write endpoint)
- **Expected:** 403 or 405; sense flags unchanged; cannot bypass server-side application
- **Roles:** authenticated

### TC-LLV-11 — Anonymous user cannot read /dungeoncrawler/character/{id}/senses (GET returns 302/403)
- **Suite:** acl
- **class_method:** `LowLightVisionTest::testAnonDeniedSensesEndpoint`
- **ac_tag:** permissions
- **status:** implementation-dependent — route `/dungeoncrawler/character/{id}/senses` does not yet exist; apply at Stage 0 once route is implemented
- **Verify:** GET `/dungeoncrawler/character/{id}/senses` as anonymous; expect 302 or 403
- **Expected:** 302 or 403; not 200
- **Roles:** anonymous

### TC-LLV-12 — Authenticated user can read senses endpoint (GET 200)
- **Suite:** acl
- **class_method:** `LowLightVisionTest::testAuthUserAllowedSensesEndpoint`
- **ac_tag:** permissions
- **status:** implementation-dependent — same route caveat as TC-LLV-11
- **Verify:** GET `/dungeoncrawler/character/{id}/senses` as authenticated user owning the character; expect 200 with senses JSON
- **Expected:** 200 + JSON senses array
- **Roles:** authenticated, dc_playwright_player

### TC-LLV-13 — Cross-character senses read blocked (character ownership enforced)
- **Suite:** functional
- **class_method:** `LowLightVisionTest::testCrossCharacterSensesReadBlocked`
- **ac_tag:** permissions
- **Verify:** Authenticated user A attempts to GET senses for a character owned by user B; expect 403 or 404
- **Expected:** 403 or 404; no cross-character data exposure
- **Roles:** authenticated (two separate users)

### TC-LLV-14 — E2E: Elf character sheet displays Low-Light Vision in senses section
- **Suite:** e2e
- **class_method:** `tests/e2e/low-light-vision.spec.js`
- **ac_tag:** happy-path
- **Verify:** Playwright: log in → create Elf character → view character sheet → assert senses section shows "Low-Light Vision" (not "darkvision", not empty)
- **Expected:** E2E PASS — correct sense label rendered on sheet
- **Roles:** dc_playwright_player

---

## Implementation-dependent test cases

| TC | Condition | Unblock |
|---|---|---|
| TC-LLV-11 | `/dungeoncrawler/character/{id}/senses` GET route does not exist | Dev implements route; confirm at Stage 0 |
| TC-LLV-12 | Same | Same |

## Notes to PM

1. **New `/senses` route**: The AC requires a GET `/dungeoncrawler/character/{id}/senses` endpoint. This route does not exist in current routing. Sense data IS available on `/api/character/{character_id}/state` under `senses`. PM/Dev should decide at Stage 0 whether to implement a dedicated `/senses` endpoint or alias it to the state endpoint with filtering. TC-LLV-11 and TC-LLV-12 apply only if the dedicated route is implemented.

2. **Structured sense vs. plain string**: `AncestryController` currently maps `ANCESTRIES['vision']` to a plain string (e.g., `'low-light vision'`). The AC requires a structured `low_light_vision` boolean flag. Dev must wire the ancestry-load path to call `FeatEffectManager::addSense('low-light-vision', ...)` rather than just storing the display string. TC-LLV-02 explicitly tests this distinction.

3. **Concealment integration**: `HexMapController` tracks lighting per room but does not yet check character senses before applying conditions. The LLV dim-light → concealment bypass requires a new check in the encounter resolution path (same integration needed for darkvision but for darkness). TC-LLV-03 and TC-LLV-04 will only pass once this integration is implemented.

4. **darkvision dependency**: `dc-cr-darkvision` must ship before or with this feature (it defines the sense entity pattern and the parallel integration point). TC-LLV-05 and TC-LLV-06 assume darkvision is implemented — if darkvision is deferred, defer those TCs.

## Route permission expectations (for qa-permissions.json at Stage 0)

| Rule ID | Route | Method | anon | authenticated | content_editor | administrator | dc_playwright_player | dc_playwright_admin |
|---|---|---|---|---|---|---|---|---|
| `dc-cr-low-light-vision-senses-read` | `/dungeoncrawler/character/{id}/senses` | GET | 302 | 200 | 200 | 200 | 200 | 200 |

**Note:** Only add this rule at Stage 0 if the dedicated `/senses` route is implemented. If senses are exposed via the existing state endpoint, do not add a new rule.

### Acceptance criteria (reference)

# Acceptance Criteria — dc-cr-low-light-vision

- Feature: Low-Light Vision Rule System
- Release target: 20260406-dungeoncrawler-release-c
- PM owner: pm-dungeoncrawler
- Date groomed: 2026-04-06

## Scope

Implement Low-Light Vision as a shared sense flag on the character entity. Characters with this sense treat dim light as bright light for concealment checks. This is the elf default vision, analogous to darkvision for dwarves.

## Prerequisites satisfied

- dc-cr-darkvision: complete or in-progress (same sense-flag pattern; reuse the implementation contract)

## Knowledgebase check

None found specifically for low-light-vision. Pattern follows dc-cr-darkvision implementation.

## Happy Path

- [ ] `[NEW]` Character entity supports a `low_light_vision` boolean sense flag (stored alongside `darkvision`).
- [ ] `[NEW]` When `low_light_vision: true`, dim-light zones are treated as bright light for concealment resolution (the concealed condition from dim light does not apply).
- [ ] `[NEW]` Ancestry records (Elf and future ancestries) can reference `low_light_vision` as a granted sense.
- [ ] `[NEW]` Sense flag persists across save/reload.
- [ ] `[NEW]` A character can have `darkvision: true` OR `low_light_vision: true` — or neither — but Cavern Elf explicitly replaces low-light-vision with darkvision (the two are mutually exclusive in that case).

## Edge Cases

- [ ] `[NEW]` A character with both flags set (via data error) resolves to darkvision (stronger sense wins).
- [ ] `[NEW]` A character without any special vision sense resolves normal rules in dim light (concealed condition applies).
- [ ] `[TEST-ONLY]` Changing ancestry during character creation clears and re-applies the correct sense flag.

## Failure Modes

- [ ] `[TEST-ONLY]` Querying sense flags for a non-existent character returns structured error (not a 500).
- [ ] `[NEW]` Sense flags cannot be directly mutated by the client (server-side ancestry/heritage application only).

## Permissions / Access Control

- [ ] Sense flag data is readable by session participants (needed for targeting/encounter display).
- [ ] Sense assignment is server-side only; clients cannot POST/PATCH sense flags directly.

### Route permission expectations (required for qa-permissions.json)

| Route | HTTP method | Permission | anon | authenticated | content_editor | administrator | dc_playwright_player | dc_playwright_admin |
|---|---|---|---|---|---|---|---|---|
| `/dungeoncrawler/character/{id}/senses` | `GET` | `access dungeoncrawler characters` | deny | allow | allow | allow | allow | allow |

## Security acceptance criteria

- [ ] GET `/dungeoncrawler/character/{id}/senses` does NOT require CSRF token (`_csrf_token: FALSE`).
- [ ] Any write path that modifies sense flags (heritage/ancestry assignment) MUST use `_csrf_request_header_mode: TRUE` on the POST route.
- [ ] Characters can only read sense flags for characters they have session access to (no cross-character data exposure).
- [ ] Sense flag write endpoint returns 403 for anonymous users.
