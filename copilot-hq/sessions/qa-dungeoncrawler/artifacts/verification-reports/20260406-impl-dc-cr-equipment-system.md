# Gate 2 Verification Report — dc-cr-equipment-system

- Feature/Item: dc-cr-equipment-system
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260406-impl-dc-cr-equipment-system.md
- Dev commits: 13874355b
- Verified by: qa-dungeoncrawler
- Date: 2026-04-06
- Verdict: **BLOCK**

---

## Knowledgebase check
- `docs/dungeoncrawler/INVENTORY_MANAGEMENT_SYSTEM.md` referenced in AC. `str_req` fields present in catalog data — consistent.

---

## BLOCK issues

### BLOCK 1: Encumbrance formula does not match AC spec

**AC requires** (explicit): "total bulk ≥ (Strength score ÷ 2) + 5 applies the Encumbered condition; ≥ Strength score + 5 applies Immobilized."

For STR 10: encumbered at bulk ≥ 10, immobilized at bulk ≥ 15.

**Actual** (`getInventoryCapacity` + `getEncumbranceStatus`):
- `capacity = 5 + STR_modifier` → for STR 10: capacity = 5
- Encumbered when `bulk > capacity * 0.75` = 3.75
- "Overburdened" when `bulk > capacity` = 5

Observed via `drush php:eval`:
```
getEncumbranceStatus(4.0, 10.0) → unencumbered  (PF2E: should be unencumbered ✓)
getEncumbranceStatus(10.0, 10.0) → encumbered    (PF2E: encumbered threshold = 10 ✓ but formula path is wrong)
getEncumbranceStatus(15.0, 10.0) → overburdened  (PF2E: should be 'immobilized')
```

Note: the capacity passed in from `getInventory()` for a character uses `getInventoryCapacity()` which returns `5 + STR_mod = 5` (for STR 10), NOT 10. So the actual threshold for a STR 10 character would be:
- Encumbered at > 3.75 bulk (PF2E says ≥ 10)
- Overburdened at > 5 bulk (PF2E says ≥ 15)

Two defects:
1. **Formula mismatch**: capacity uses `5 + STR_mod` (PF2E encumbrance threshold) instead of `STR_score / 2 + 5` threshold for Encumbered and `STR_score + 5` for Immobilized
2. **Condition name**: returns `"overburdened"` not `"immobilized"` (downstream condition logic depends on this string)

**Reproduction**:
```php
$svc = \Drupal::service("dungeoncrawler_content.inventory_management");
// STR 10 character: capacity returned = 5 + 0 = 5
// Encumbered threshold should be 10 (STR 10 / 2 + 5), but is 3.75
echo $svc->getEncumbranceStatus(4.0, 5.0); // "encumbered" (WRONG per AC: should be unencumbered)
echo $svc->getEncumbranceStatus(10.0, 5.0); // "overburdened" (WRONG: should be encumbered)
```

**Fix required**: Update `getInventoryCapacity()` to return the max carrying capacity (`10 + STR_mod`), and update `getEncumbranceStatus()` thresholds to match PF2E spec: encumbered at `> (capacity / 2)` (= `STR_score/2 + 5`), immobilized at `> capacity` (= `STR_score + 5`). Also rename `"overburdened"` to `"immobilized"`.

### BLOCK 2: STR requirement check penalty on equip not implemented

**AC requires**: "Attempting to equip armor the character lacks the Strength requirement for applies the armor check penalty to all Strength/Dexterity skill checks."

**Actual**: `str_req` is stored in the catalog data for each armor item (e.g., breastplate `str_req: 16`, full plate `str_req: 18`). However, no code path enforces this penalty when equipping: there is no `str_req` check in `moveItem`, `addItemToInventory`, or any equip path in `InventoryManagementService`. The catalog stores the data but the enforcement is absent.

No implementation found for STR requirement enforcement on equip.

---

## Passing AC items

### Happy Path

| AC item | Expected | Actual | Result |
|---|---|---|---|
| Catalog: ≥5 simple weapons | ≥5 | 6 simple weapons ✅ | ✅ PASS |
| Catalog: ≥5 martial weapons | ≥5 | 7 martial weapons ✅ | ✅ PASS |
| Catalog: ≥3 light armors | ≥3 | 3 light armors ✅ | ✅ PASS |
| Catalog: ≥2 medium armors | ≥2 | 3 medium armors ✅ | ✅ PASS |
| Catalog: ≥1 heavy armor | ≥1 | 1 heavy armor ✅ | ✅ PASS |
| Catalog: ≥10 gear items | ≥10 | 10 gear items ✅ | ✅ PASS |
| Catalog: shields present (dev fixed gap) | ≥1 | 3 shields ✅ | ✅ PASS |
| Weapon fields: name, category, group, damage_dice, damage_type, traits | All present | Confirmed via `drush` + live curl ✅ | ✅ PASS |
| Armor fields: name, category, ac_bonus, max_dex, check_penalty, speed_penalty, str_req | All present | Confirmed via `drush` + live curl ✅ | ✅ PASS |
| `GET /equipment?type=weapon/armor/shield/gear` | 200, filtered items | weapon=13, armor=7, shield=3, gear=10 all return 200 ✅ | ✅ PASS |
| `GET /equipment?type=unknown` | HTTP 400 + error message | `{"error":"Invalid type. Must be one of: weapon, armor, shield, gear"}` HTTP 400 ✅ | ✅ PASS |
| Anonymous access to `/equipment` | 200 | Route `_access: 'TRUE'` confirmed; HTTP 200 ✅ | ✅ PASS |
| `GET /classes/fighter/starting-equipment` | Weapon+armor+gear+currency | `{weapons:[2], armor:[1], gear:[5], currency:{gp:15}}` ✅ | ✅ PASS |
| `GET /classes/unknownclass/starting-equipment` | Error | `{"error":"Class not found: unknownclass"}` ✅ | ✅ PASS |
| CharacterCalculator uses `armor_bonus` from character state | AC formula: 10 + DEX (capped) + armor_bonus | `CharacterCalculator.php:210` confirmed ✅ | ✅ PASS (code path) |

### Edge Cases / Failure Modes

| AC item | Expected | Actual | Result |
|---|---|---|---|
| Encumbrance formula (STR score-based) | Encumbered at ≥ STR/2+5; Immobilized at ≥ STR+5 | Uses capacity-percentage model; wrong thresholds for real characters | ❌ FAIL — **BLOCK 1** |
| Condition name: Immobilized | "immobilized" string | Returns "overburdened" | ❌ FAIL — **BLOCK 1** |
| STR req check penalty on equip | Check penalty applied when char STR < armor str_req | Not implemented in any equip path | ❌ FAIL — **BLOCK 2** |
| Equip item char doesn't own → explicit error | Error response | `addItemToInventory` validates ownership; error returned ✅ | ✅ PASS |

---

## Live endpoint spot-checks

```
GET /equipment?type=weapon   → 200, 13 items ✅
GET /equipment?type=armor    → 200, 7 items  ✅
GET /equipment?type=shield   → 200, 3 items  ✅
GET /equipment?type=gear     → 200, 10 items ✅
GET /equipment?type=unknown  → 400, error message ✅
GET /classes/fighter/starting-equipment → weapons:2, armor:1, gear:5, currency:{gp:15} ✅
GET /classes/unknownclass/starting-equipment → error ✅
Anonymous access /equipment  → 200 ✅
```

## Site audit

- Run: 20260406-165538
- Findings: 0 failures, 0 violations, 0 missing assets ✅

---

## Required fixes

**BLOCK 1 — InventoryManagementService**:
1. Update `getInventoryCapacity()` to return `10 + STR_mod` (max carrying bulk per PF2E, not encumbrance threshold)
2. Update `getEncumbranceStatus()`: encumbered at `bulk > (capacity / 2)`, immobilized at `bulk > capacity`
3. Rename `"overburdened"` → `"immobilized"` in the return value

**BLOCK 2 — InventoryManagementService (equip path)**:
1. When `location_type = 'equipped'` or `'worn'`, look up `armor_stats.str_req` for the item being equipped
2. If character STR < `str_req`, apply `armor_stats.check_penalty` to STR/DEX skill checks (via condition or character state flag)

---

## Verdict

**BLOCK** — 2 defects: (1) Encumbrance formula uses capacity-percentage model; thresholds incorrect for PF2E (Encumbered/Immobilized). (2) STR requirement check penalty on equip is not implemented — `str_req` data exists in catalog but no enforcement in equip path. All catalog completeness, endpoint, and field-presence AC items pass.
