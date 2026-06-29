# Suite Activation: dc-cr-equipment-system

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T18:12:11+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-equipment-system"`**  
   This links the test to the living requirements doc at `features/dc-cr-equipment-system/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-equipment-system-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-equipment-system",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-equipment-system"`**  
   Example:
   ```json
   {
     "id": "dc-cr-equipment-system-<route-slug>",
     "feature_id": "dc-cr-equipment-system",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-equipment-system",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-equipment-system

**Owner:** qa-dungeoncrawler  
**Date:** 2026-03-28  
**Feature:** Equipment and Gear System — catalog completeness, GET /equipment route, starting equipment by class, equip→stat integration, encumbrance conditions, worn-vs-held distinction, strength requirement enforcement, permissions  
**AC source:** `features/dc-cr-equipment-system/01-acceptance-criteria.md`  
**Implementation notes:** `features/dc-cr-equipment-system/02-implementation-notes.md`  

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit immediately after module/routing changes.
- `docs/dungeoncrawler/INVENTORY_MANAGEMENT_SYSTEM.md` — existing inventory design doc (cited in both AC and impl notes); review before writing inventory integration tests.
- Prior art: `dc-cr-conditions` test plan references `ConditionManager::applyCondition()` — encumbrance TCs below extend that coupling.

## Gap analysis summary (from AC + impl notes)
- `InventoryManagementService` CRUD and bulk calculation — **Full** (already implemented); TCs verify no regression
- Equipment catalog completeness (weapons, armor, gear) — **Partial** → extend; TCs verify AC minimums
- `GET /equipment?type=` route — **None** → new; TCs cover happy path + type filtering + invalid type
- `GET /classes/{id}/starting-equipment` — **None** → new (slice 2, depends on dc-cr-character-class); TCs are written now, activate at Stage 0 when both features land
- Equip → combat stat integration (AC, damage dice) — **Partial** → extend; TCs cover Breastplate AC formula + weapon damage feed-in
- Encumbrance condition trigger — **Full** (bulk-sum logic present); TCs verify threshold boundary behavior
- Worn vs held distinction — **Partial** → TCs verify stat bonus only applies for worn, not carried
- Strength requirement enforcement for armor — **None** → new TCs

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` (unit) | PHPUnit unit (`./vendor/bin/phpunit tests/src/Unit/Service/`) | ItemCombatDataService catalog, InventoryManagementService encumbrance, CharacterCalculator AC/damage |
| `module-test-suite` (functional) | PHPUnit functional (`./vendor/bin/phpunit tests/src/Functional/`) | GET /equipment route filtering + anonymous access; GET /classes/{id}/starting-equipment |
| `role-url-audit` | `scripts/site-audit-run.sh` | Anonymous read of GET /equipment (200); authenticated equip/unequip (auth-required) |

> **Dependency note:** TC-EQ-13 (`GET /classes/{id}/starting-equipment`) depends on dc-cr-character-class being in scope at the same Stage 0. If equipment-system ships without character-class, TC-EQ-13 should be skipped with an explicit note in the verification report.

---

## Test cases

### TC-EQ-01 — Weapon catalog meets AC minimums (5 simple + 5 martial weapons)
- **AC:** `[EXTEND]` Catalog includes at minimum 5 simple weapons and 5 martial weapons
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `ItemCombatDataServiceTest::testWeaponCatalogMeetsMinimumCount()`
- **Setup:** Load all items from `content/items/` where category is `simple` or `martial`
- **Expected:** At least 5 simple weapons and 5 martial weapons present; count assertion passes
- **Roles:** n/a (data layer)

### TC-EQ-02 — Armor catalog meets AC minimums (3 light, 2 medium, 1 heavy)
- **AC:** `[EXTEND]` Catalog includes at minimum 3 light armors, 2 medium armors, 1 heavy armor
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `ItemCombatDataServiceTest::testArmorCatalogMeetsMinimumCount()`
- **Setup:** Load all items from `content/items/` where type is `armor`; group by category
- **Expected:** light ≥ 3, medium ≥ 2, heavy ≥ 1
- **Roles:** n/a (data layer)

### TC-EQ-03 — Adventuring gear catalog meets AC minimum (10 items)
- **AC:** `[EXTEND]` Catalog includes at minimum 10 adventuring gear items
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `ItemCombatDataServiceTest::testGearCatalogMeetsMinimumCount()`
- **Setup:** Load all items where type is `gear`
- **Expected:** Count ≥ 10
- **Roles:** n/a (data layer)

### TC-EQ-04 — Weapon items have required schema fields
- **AC:** `[EXTEND]` Each weapon has: name, category, group, damage (dice + type), bulk, price, traits[]
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `ItemCombatDataServiceTest::testWeaponItemsHaveRequiredSchema()`
- **Setup:** Load all weapon items; iterate and assert each has non-null: name, category, group, damage_dice, damage_type, bulk, price, traits (array)
- **Expected:** All weapons pass schema assertion; no missing fields
- **Roles:** n/a (data layer)

### TC-EQ-05 — Armor items have required schema fields
- **AC:** `[EXTEND]` Each armor has: name, category, AC_bonus, max_dex_bonus, check_penalty, speed_penalty, bulk, price, strength_req
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `ItemCombatDataServiceTest::testArmorItemsHaveRequiredSchema()`
- **Setup:** Load all armor items; iterate and assert each has non-null: name, category, AC_bonus, max_dex_bonus, check_penalty, speed_penalty, bulk, price, strength_req
- **Expected:** All armors pass schema assertion; no missing fields
- **Roles:** n/a (data layer)

### TC-EQ-06 — GET /equipment?type=weapon returns weapons only
- **AC:** `[NEW]` `GET /equipment?type=weapon` returns filtered catalog
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `EquipmentApiTest::testGetEquipmentFilterWeapons()`
- **Setup:** Anonymous GET to `http://localhost:8080/equipment?type=weapon`
- **Expected:** HTTP 200; response array contains only items with type=weapon; each item has damage_dice, damage_type, traits
- **Roles:** anonymous

### TC-EQ-07 — GET /equipment?type=armor returns armors only
- **AC:** `[NEW]` `GET /equipment?type=armor` returns filtered catalog
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `EquipmentApiTest::testGetEquipmentFilterArmor()`
- **Setup:** Anonymous GET to `http://localhost:8080/equipment?type=armor`
- **Expected:** HTTP 200; response array contains only items with type=armor; each item has AC_bonus, max_dex_bonus
- **Roles:** anonymous

### TC-EQ-08 — GET /equipment?type=gear returns gear only
- **AC:** `[NEW]` `GET /equipment?type=gear` returns filtered catalog
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `EquipmentApiTest::testGetEquipmentFilterGear()`
- **Setup:** Anonymous GET to `http://localhost:8080/equipment?type=gear`
- **Expected:** HTTP 200; response array contains only gear items
- **Roles:** anonymous

### TC-EQ-09 — GET /equipment with invalid type returns 400
- **AC:** Impl notes: invalid `type` must return 400 (input validation)
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `EquipmentApiTest::testGetEquipmentInvalidTypeReturns400()`
- **Setup:** Anonymous GET to `http://localhost:8080/equipment?type=magic_items`
- **Expected:** HTTP 400; structured error response
- **Roles:** anonymous

### TC-EQ-10 — GET /equipment is accessible to anonymous users
- **AC:** Anonymous user behavior: equipment catalog is readable by anonymous users
- **Suite:** `role-url-audit`
- **Entry:** `GET /equipment` — HTTP 200 for anonymous role
- **Expected:** 200 with catalog content (no redirect, no 403)
- **Roles:** anonymous

### TC-EQ-11 — Equipping a weapon updates character's attack damage dice
- **AC:** `[EXTEND]` Equipping a weapon updates combat stats; weapon damage dice feeds into attack actions
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterCalculatorTest::testEquippedWeaponUpdatesDamageDice()`
- **Setup:** Character with no weapon equipped; equip a longsword (1d8 slashing); call `CharacterCalculator::getAttackDamage(character_id)`
- **Expected:** Returned damage_dice = `1d8`; damage_type = `slashing`; reflects longsword stats
- **Roles:** n/a (service layer)

### TC-EQ-12 — Equipping Breastplate gives correct AC formula
- **AC:** `[TEST-ONLY]` AC = 10 + Dex mod (capped at +3) + 4 (Breastplate AC bonus)
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterCalculatorTest::testBreastplateACFormula()`
- **Setup:** Character with Dex modifier +5 (capped at +3 by Breastplate max_dex); equip Breastplate (AC_bonus=4); call `CharacterCalculator::calculateAC(character_id)`
- **Expected:** AC = 10 + 3 + 4 = 17 (Dex capped at +3, not full +5)
- **Roles:** n/a (service layer)

### TC-EQ-13 — GET /classes/{id}/starting-equipment returns starting package
- **AC:** `[NEW]` `GET /classes/{id}/starting-equipment` lists the starting equipment package
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `EquipmentApiTest::testGetClassStartingEquipment()`
- **Setup:** GET to `http://localhost:8080/classes/{fighter_id}/starting-equipment`
- **Expected:** HTTP 200; response includes at least one weapon and one armor item appropriate to the class
- **Roles:** anonymous
- **Dependency:** Requires dc-cr-character-class in same release. If not in scope, skip with explicit verification report note.

### TC-EQ-14 — Encumbrance: bulk ≥ threshold applies Encumbered condition
- **AC:** `[EXTEND]` Total bulk ≥ (Strength ÷ 2) + 5 → Encumbered condition applied
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `InventoryManagementServiceTest::testEncumbranceThresholdAppliesEncumbered()`
- **Setup:** Character with Strength 10 (threshold = 5 + 5 = 10 bulk); add items summing to bulk 10
- **Expected:** `InventoryManagementService` detects encumbrance threshold crossed; calls `ConditionManager::applyCondition('encumbered')` or equivalent
- **Roles:** n/a (service layer)

### TC-EQ-15 — Encumbrance: bulk ≥ Strength + 5 applies Immobilized condition
- **AC:** `[EXTEND]` Total bulk ≥ Strength + 5 → Immobilized condition applied
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `InventoryManagementServiceTest::testImmobilizedThresholdAppliesImmobilized()`
- **Setup:** Character with Strength 10 (immobilized threshold = 10 + 5 = 15 bulk); add items summing to bulk 15
- **Expected:** Immobilized condition applied; Encumbered should also be active (both thresholds crossed)
- **Roles:** n/a (service layer)

### TC-EQ-16 — Worn items apply stat bonuses; carried items do not
- **AC:** `[EXTEND]` Worn items apply their stat bonuses; held-only items do not
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `InventoryManagementServiceTest::testWornVsCarriedItemStatBonuses()`
- **Setup:** Character carries leather armor (in inventory, not worn); call `CharacterCalculator::calculateAC()`; then equip/wear leather armor; call calculateAC again
- **Expected:** AC is unchanged while armor is carried (not worn); AC increases by armor's AC_bonus once worn
- **Roles:** n/a (service layer)

### TC-EQ-17 — Equipping armor below Strength requirement applies check penalty to Str/Dex skills
- **AC:** `[NEW]` Equipping armor without meeting Strength req applies armor check penalty to all Str/Dex skill checks
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterCalculatorTest::testArmorStrengthRequirementPenalty()`
- **Setup:** Character with Strength 10; equip Full Plate (strength_req=18, check_penalty=-3); call `CharacterCalculator::getSkillModifier(character_id, 'athletics')` (Strength-based)
- **Expected:** Skill modifier includes −3 armor check penalty because Strength 10 < strength_req 18
- **Roles:** n/a (service layer)

### TC-EQ-18 — Only character owner can equip/unequip items
- **AC:** Authenticated user behavior: only the character's owner can equip/unequip items
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `InventoryManagementServiceTest::testUnauthorizedPlayerCannotEquipItems()`
- **Setup:** Player A owns character; Player B attempts to call equip for Player A's character
- **Expected:** 403 or authorization exception; equip action not performed
- **Roles:** authenticated player (other's character)

### TC-EQ-19 — Admin can modify character inventory for moderation
- **AC:** Admin behavior: admin can modify character inventory for moderation
- **Suite:** `module-test-suite` (unit) or `role-url-audit`
- **Test class/method:** `InventoryManagementServiceTest::testAdminCanModifyAnyCharacterInventory()`
- **Setup:** Admin-role call to add/remove item from any character's inventory
- **Expected:** Operation succeeds; no 403
- **Roles:** admin

### TC-EQ-20 — Equipping an item the character does not own returns an explicit error
- **AC:** `[NEW]` Attempting to equip an item the character does not own returns an explicit error
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `InventoryManagementServiceTest::testEquipItemNotOwnedReturnsExplicitError()`
- **Setup:** Character has empty inventory; attempt to equip item_id for an item not in their inventory
- **Expected:** Structured error returned with explicit message (not a generic PHP exception); HTTP 422 or 404
- **Roles:** n/a (service layer)

### TC-EQ-21 — Inventory state persists correctly across API calls
- **AC:** Inventory state persists correctly; no phantom items or duplicate entries
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `EquipmentApiTest::testInventoryStatePersistsAcrossCalls()`
- **Setup:** Add item to character inventory via service; reload character inventory from DB; verify item appears exactly once
- **Expected:** Item count = 1; no phantom entries; state matches DB
- **Roles:** n/a (data integrity)

### TC-EQ-22 — QA audit still passes after module deployment
- **AC:** QA automated audit must still pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler` (local dev)
- **Expected:** 0 violations, 0 failures; GET /equipment appears as anonymous-accessible in audit
- **Roles:** all 6 roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| "Error messages are clear and actionable" (`[TEST-ONLY]`) | Quality criterion; automation verifies error is returned with a structured key — human review needed for "actionable" judgment. |
| Equipment catalog visual presentation (UI) | The functional tests verify JSON API responses. Any UI rendering of catalog items (item cards, icons, tooltips) requires Playwright once the equipment UI is built. |
| `GET /classes/{id}/starting-equipment` cross-feature dependency | Requires dc-cr-character-class in scope at same Stage 0. If character-class is excluded, this TC must be skipped explicitly in the verification report. |
| Armor check penalty on existing items after strength increase | Edge case: if a character's Strength increases mid-game, the penalty should lift automatically — behavioral correctness requires a game-state integration test that spans character leveling and inventory; defer to dc-cr-character-leveling scope. |

---

## Regression risk areas

1. `dc-cr-conditions` coupling: encumbrance TCs (TC-EQ-14, TC-EQ-15) depend on `ConditionManager::applyCondition()` — if conditions system changes its interface, encumbrance triggers will silently fail.
2. `dc-cr-character-class` coupling: `GET /classes/{id}/starting-equipment` (TC-EQ-13) requires class content type to exist; scheduling this feature without character-class in the same release will cause TC-EQ-13 to fail with a 404.
3. `dc-cr-encounter-rules` / `dc-cr-action-economy` coupling: equip→damage-dice integration (TC-EQ-11) feeds directly into `resolveAttack()` damage calculation; any refactor of `CharacterCalculator::getAttackDamage()` can silently break combat.
4. Existing `longsword.json` schema: impl notes flag that longsword.json may not fully meet the AC weapon schema (damage, traits, bulk, price, group). If Dev patches it during implementation, TC-EQ-04 catches any regressions.
5. `GET /equipment` route registration: new route must be added to `qa-permissions.json` at Stage 0 preflight (`GET /equipment` — anonymous 200, auth 200, non-gm player 200; it is a public read-only catalog).
6. Worn vs held state corruption: if `InventoryManagementService` does not cleanly separate worn/equipped state from carried state in the DB, AC calculations will include bonuses from carried items, causing silent combat stat inflation.

### Acceptance criteria (reference)

# Acceptance Criteria (PM-owned)
# Feature: dc-cr-equipment-system

## Gap analysis reference

Gap analysis performed against `InventoryManagementService.php`, `GameObjectInventoryService.php`, `ItemCombatDataService.php`, `content/items/`.

Coverage findings:
- Inventory CRUD (add/remove/transfer items for character and containers) — **Full** (`InventoryManagementService` with DB persistence)
- Bulk calculation and encumbrance tracking — **Full** (BULK_MAP constant and bulk-sum logic present)
- Equipment content catalog (weapons, armor, shields, adventuring gear with stats) — **Partial** (content/items/ directory exists; completeness of weapon/armor catalog unclear)
- Weapon stats (damage dice, damage type, traits, weapon group) — **Partial** (`ItemCombatDataService` likely has partial data)
- Armor stats (AC bonus, dex cap, check penalty, speed penalty, bulk, category) — **Partial** (needs verification)
- Starting equipment by class — **None** (no class starting equipment mapping found)
- `equipped[]` subset tracking — **Partial** (InventoryManagementService has item states but equipped/worn distinction needs verification)

Feature type: **enhancement** (inventory layer complete; verify and complete equipment catalog, add starting equipment by class, confirm equipped item stat integration)

## Happy Path
- [ ] `[EXTEND]` The equipment catalog includes at minimum: 5 simple weapons, 5 martial weapons, 3 light armors, 2 medium armors, 1 heavy armor, and 10 adventuring gear items (per PF2E Core Rulebook Chapter 6).
- [ ] `[EXTEND]` Each weapon item has: name, category (simple/martial/advanced), group, damage (dice + type), bulk, price, traits[].
- [ ] `[EXTEND]` Each armor item has: name, category (light/medium/heavy/unarmored), AC_bonus, max_dex_bonus, check_penalty, speed_penalty, bulk, price, strength_req.
- [ ] `[NEW]` `GET /equipment?type=weapon` (and `armor`, `shield`, `gear`) returns filtered equipment catalog.
- [ ] `[NEW]` Character creation step 6 provides a `GET /classes/{id}/starting-equipment` endpoint listing the starting equipment package for each class.
- [ ] `[EXTEND]` Equipping a weapon or armor updates the character's combat stats: weapon damage dice feeds into attack actions; armor AC bonus is included in AC calculation.
- [ ] `[TEST-ONLY]` A character that equips a Breastplate (medium armor) has AC = 10 + Dex mod (capped at +3) + 4 (AC bonus).

## Edge Cases
- [ ] `[EXTEND]` Encumbrance: total bulk ≥ (Strength score ÷ 2) + 5 applies the Encumbered condition; ≥ Strength score + 5 applies Immobilized.
- [ ] `[EXTEND]` Worn items (not just held/carried) apply their stat bonuses; held-only items do not.
- [ ] `[NEW]` Attempting to equip armor the character lacks the Strength requirement for applies the armor check penalty to all Strength/Dexterity skill checks.

## Failure Modes
- [ ] `[TEST-ONLY]` Error messages are clear and actionable.
- [ ] `[NEW]` Attempting to equip an item the character does not own returns an explicit error.

## Permissions / Access Control
- [ ] Anonymous user behavior: equipment catalog is readable by anonymous users (reference data).
- [ ] Authenticated user behavior: only the character's owner can equip/unequip items.
- [ ] Admin behavior: admin can modify character inventory for moderation.

## Data Integrity
- [ ] Inventory state persists correctly across API calls; no phantom items or duplicate entries.
- [ ] Rollback path: inventory changes are stored in DB tables; character item state can be reconstructed from DB.

## Knowledgebase check
- Related lessons/playbooks: see `docs/dungeoncrawler/INVENTORY_MANAGEMENT_SYSTEM.md` for existing design doc.

## Test path guidance (for QA)
| Requirement | Test path |
|---|---|
| Equipment catalog completeness | `tests/src/Unit/Service/ItemCombatDataServiceTest.php` |
| `GET /equipment` filtering | `tests/src/Functional/EquipmentApiTest.php` (new) |
| Starting equipment by class | `tests/src/Functional/EquipmentApiTest.php` (new) |
| Equip → AC/damage integration | `tests/src/Unit/Service/CharacterCalculatorTest.php` |
| Encumbrance condition | `tests/src/Unit/Service/InventoryManagementServiceTest.php` (extend) |
- Agent: qa-dungeoncrawler
- Status: pending
