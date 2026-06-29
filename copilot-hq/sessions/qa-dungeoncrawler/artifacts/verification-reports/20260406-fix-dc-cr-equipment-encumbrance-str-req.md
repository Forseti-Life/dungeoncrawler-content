# QA Verification Report: 20260406-fix-dc-cr-equipment-encumbrance-str-req

**Date:** 2026-04-06
**Agent:** qa-dungeoncrawler
**Feature:** dc-cr-equipment-system (encumbrance + STR req penalty regression fix)
**Dev commit:** 889d129a3
**Status:** APPROVE

---

## Scope

Re-verification of two Gate 2 BLOCKs identified in `20260406-impl-dc-cr-equipment-system`:

- **BLOCK 1:** `getInventoryCapacity()` formula wrong — was `5+STR_mod`, must be PF2e spec `10+STR_mod`; `getEncumbranceStatus()` used wrong thresholds and wrong condition name (`overburdened` → `immobilized`)
- **BLOCK 2:** `applyArmorStrPenalty()` missing — no STR req check penalty applied on armor equip

Files changed by Dev:
- `web/modules/custom/dungeoncrawler_content/src/Service/InventoryManagementService.php`
- `web/modules/custom/dungeoncrawler_content/src/Controller/InventoryManagementController.php`

---

## AC Verification

### BLOCK 1 — Encumbrance formula

**AC:** `getInventoryCapacity(character)` returns `10 + floor((STR - 10) / 2)` (PF2e base bulk limit)

**Code verified (InventoryManagementService.php line 1091):**
```php
return 10 + $str_mod;  // where $str_mod = floor(($str - 10) / 2)
```

Inline formula check:
- STR 10 → capacity = 10 + 0 = **10** ✅
- STR 18 → capacity = 10 + 4 = **14** ✅
- STR 8 → capacity = 10 + (-1) = **9** ✅

**AC:** `getEncumbranceStatus(bulk, str_score)` returns:
- `unencumbered` when `bulk < floor(STR/2) + 5`
- `encumbered` when `bulk >= floor(STR/2) + 5`
- `immobilized` when `bulk >= STR + 5`

**Live drush probe (prod: /var/www/html/dungeoncrawler):**
```
STR 10, bulk 9.0  → unencumbered  (expect unencumbered) ✅
STR 10, bulk 10.0 → encumbered    (expect encumbered) ✅   [threshold = floor(10/2)+5 = 10]
STR 10, bulk 14.9 → encumbered    (expect encumbered) ✅
STR 10, bulk 15.0 → immobilized   (expect immobilized) ✅  [threshold = 10+5 = 15]
STR 18, bulk 14.0 → encumbered    (expect encumbered) ✅   [threshold = floor(18/2)+5 = 14]
STR 18, bulk 23.0 → immobilized   (expect immobilized) ✅  [threshold = 18+5 = 23]
```

**AC:** `overburdened` label removed, `immobilized` used throughout
- `overburdened` occurrences: **0** ✅
- `immobilized` occurrences: **4** ✅

**AC:** Controller callers updated to pass `str_score` (from `CharacterStateService`)
- `InventoryManagementController.php` line 77-82: reads `$char_state['abilities']['strength']`, falls back to `10.0`, passes to `getEncumbranceStatus()` ✅
- Same pattern at lines 404-410 ✅

### BLOCK 2 — STR requirement penalty enforcement

**AC:** On equip to `worn` or `equipped` location, `applyArmorStrPenalty()` is called; if `char_str < str_req`, sets `str_penalty_active: TRUE` and `str_penalty_check_penalty` on item state_data

**Code verified:**
- `changeItemLocation()` line 923-924: `in_array($new_location, ['worn', 'equipped'], TRUE)` → calls `applyArmorStrPenalty()` ✅
- `applyArmorStrPenalty()` method: protected, exists, correct logic ✅
  - Reads item `state_data`, checks `item_type === 'armor'`
  - Skips if `str_req === 0` (non-armors / armors without STR req)
  - Clears stale penalty flags on every evaluation (re-evaluated on each equip)
  - Sets `str_penalty_active = TRUE` and `str_penalty_check_penalty = check_penalty` when `char_str < str_req`
  - Equip is never blocked (PF2e spec: penalty flag only)
- Method reflection confirmed: `applyArmorStrPenalty` is `protected` ✅

**Source string checks:**
- `applyArmorStrPenalty` call count: **2** (declaration + call site) ✅
- `str_penalty_active` references: present ✅

---

## Site audit

Latest clean audit: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260406-170141/findings-summary.md` — **0 failures**

---

## Result

**APPROVE**

Both BLOCKs from Gate 2 are resolved. Encumbrance formula matches PF2e spec; STR requirement penalty flag is applied correctly on equip/wear transitions. No regressions detected.

Evidence commits:
- Dev fix: `889d129a3`
- Checklist: line 97 (this report)
