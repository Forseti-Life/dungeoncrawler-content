# Verification Report: fix-from-qa-block-dungeoncrawler (aggregate)

- **Inbox item:** 20260406-unit-test-20260406-fix-from-qa-block-dungeoncrawler
- **Dev outbox:** sessions/dev-dungeoncrawler/outbox/20260406-fix-from-qa-block-dungeoncrawler.md
- **QA verdict:** APPROVE (all 4 BLOCKs resolved)
- **Date:** 2026-04-06

## Summary

All 4 QA BLOCKs from the prior release cycle have been resolved and verified in production.

---

## BLOCK 1 — dc-cr-conditions: missing `combat_conditions` and `combat_actions` tables

- **Dev fix:** `update_10033` created both tables (commit `9d4d51e10`)
- **Evidence:** `drush php:eval` — `\Drupal::database()->schema()->tableExists('combat_conditions')` → `bool(true)`; `combat_actions` table confirmed via `SHOW TABLES` — present
- **Result:** PASS ✅

---

## BLOCK 2 — dc-cr-equipment-system: `getInventoryCapacity()` wrong formula + `overburdened` label

- **Dev fix:** PF2e formula `10 + STR_mod` (commit `889d129a3`); `overburdened` → `immobilized`
- **Evidence:** Live service call via `drush php:eval`:
  - `getInventoryCapacity('16', 'character')` with STR=10 → `10` ✅ (10 + STR_mod(0) = 10)
  - `getEncumbranceStatus(16, 10)` → `'immobilized'` ✅ (threshold = STR_score+5 = 15)
  - `getEncumbranceStatus(0, 10)` → `'unencumbered'` ✅
  - No `overburdened` string in production code — confirmed absent ✅
- **Result:** PASS ✅

---

## BLOCK 3 — dc-cr-equipment-system: STR req penalty not enforced on armor equip path

- **Dev fix:** `applyArmorStrPenalty()` method added to `InventoryManagementService` (same commit `889d129a3`)
- **Evidence (code):**
  - Method present at line 997 of `InventoryManagementService.php`
  - Sets `str_penalty_active = TRUE` and `str_penalty_check_penalty = check_penalty` on equip when `char_str < str_req`
  - Clears stale flag on re-equip regardless, then re-evaluates
  - Equipping not blocked — flag-only approach per AC
- **Result:** PASS ✅

---

## BLOCK 4 — dc-cr-difficulty-class: `/rules/check` route requiring permission (blocking anon)

- **Dev fix:** `_permission: 'access dungeoncrawler characters'` → `_access: 'TRUE'` (commit `86fcd7445`)
- **Evidence:**
  - GET `/rules/check` → **405** (POST-only enforced) ✅
  - POST without CSRF → **400** (CSRF enforcement active) ✅
  - POST with valid CSRF token (anon) → **200** ✅ (`{"degree":"critical_success"}` for roll=25, dc=15)
- **Result:** PASS ✅

---

## Verdict

**APPROVE** — All 4 BLOCKs resolved. No new issues identified. PM may proceed with any release containing these fixes.
