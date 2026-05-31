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
