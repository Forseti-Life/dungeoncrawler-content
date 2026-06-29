# Verification Report: 20260406-impl-afflictions

**Decision: APPROVE**
**Score: 9/10 core reqs PASS (1 wiring gap, 1 minor defect — neither blocks service launch)**
**Date:** 2026-04-07
**Dev commit verified:** `56d8905bd`
**Runtime enabler (DEF-2145 fix):** `Calculator::calculateDegreeOfSuccess()` proxy confirmed present

---

## Source verified

- `AfflictionManager.php` (380 lines) — `/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/AfflictionManager.php`
- `dungeoncrawler_content.install` — `update_10036` creates `combat_afflictions` table
- `dungeoncrawler_content.services.yml` — service registered as `dungeoncrawler_content.affliction_manager`
- `Calculator.php` — `calculateDegreeOfSuccess()` proxy at line 553 confirmed

---

## REQ matrix (reqs 2135–2144)

| REQ | Description | Verdict | Evidence |
|---|---|---|---|
| 2135 | Poison/disease/curse data structure with save DC, type, stages_json | ✅ PASS | `combat_afflictions` table EXISTS; all 10+ fields stored in `applyAffliction()` lines 95–117 |
| 2136 | Initial save: crit_success/success=stage 0 (not applied); failure=stage 1; crit_fail=stage 2 | ✅ PASS | `match ($degree)` block lines 73–79; early-return at stage 0 lines 81–89 |
| 2137 | Onset delay: conditions skipped until onset_elapsed | ✅ PASS | `onset_elapsed=0` if onset set (line 107); `applyStageConditions` skipped when onset pending (lines 120–122) |
| 2138 | Stage progression: crit_success=-2, success=-1, failure=+1, crit_fail=+2 | ✅ PASS | `$stage_delta = match($degree)` lines 185–191; non-virulent values correct |
| 2139 | Stage clamped at max | ✅ PASS | `min($new_stage, $max_stage)` line 203 |
| 2140 | Stage 0 = cured (end affliction) | ✅ PASS | `$new_stage <= 0 → $this->endAffliction()` lines 196–200 |
| 2141 | Virulent: 2 consecutive non-crit successes required for -1 stage | ✅ PASS | `$consec` tracking lines 178–183; `($is_virulent && $consec < 2) ? 0 : -1` line 187 |
| 2142 | Virulent crit_success: always -1 (never -2) | ✅ PASS | `$is_virulent ? -1 : -2` line 186 |
| 2143 | Disease/curse re-exposure: no stage change | ✅ PASS | `in_array($type, ['disease', 'curse'])` → early return lines 252–255 |
| 2144 | Poison re-exposure: failure=+1 stage, crit_fail=+2 stage | ✅ PASS | `match($save_degree)` block lines 263–267; max_stage clamped line 274 |

---

## Defects found

### DEF-AFFLICTION-2 (LOW) — Undefined `$encounter_id` in `handleReExposure()`
- **Location:** `AfflictionManager.php` line 282
- **Code:** `$this->applyStageConditions($participant_id, ['stages' => $stages_def], $new_stage, $encounter_id ?? 0);`
- **Issue:** `handleReExposure()` signature is `(int $participant_id, int $affliction_id, array $affliction_def, string $save_degree)` — no `$encounter_id` parameter. PHP emits undefined-variable notice; `?? 0` fallback means conditions from poison re-exposure stage advance are applied to encounter_id=0 instead of the actual encounter.
- **Impact:** Low — conditions are still applied to the participant; only encounter_id is wrong (affects queries scoped by encounter, not condition itself).
- **Fix:** Add `int $encounter_id` as 5th parameter to `handleReExposure()`.

---

## Wiring gap (not blocking this item)

### GAP-AFFLICTION-1 (MEDIUM) — Periodic saves not called from CombatEngine
- `CombatEngine::processEndOfTurnEffects()` (line 494) handles persistent_damage and ticks conditions but does NOT call `AfflictionManager::processPeriodicSave()`.
- Afflictions correctly store state and can be manually progressed, but will never auto-progress during combat rounds.
- **Dev explicitly listed this as Next Actions in their outbox.** Not a defect in the AfflictionManager itself — it is a follow-on integration task.
- **Recommendation:** Dev should add affliction periodic save loop after persistent_damage block in `processEndOfTurnEffects()`.

---

## Site audit

- Run: 20260407-011600
- 404s: 0
- Permission violations: 0
- Other 4xx/5xx: 0
- Config drift: none
- **Site audit: CLEAN**

---

## Regression checklist

Existing entry at line 109 of `qa-regression-checklist.md`:
```
- [ ] 20260406-impl-afflictions — targeted regression check
```
Updated to checked in this report (see checklist update commit).

---

## Summary

AfflictionManager.php is a complete, correct service implementation for all 10 REQs (2135–2144). The Calculator::calculateDegreeOfSuccess() runtime fix (DEF-2145) is confirmed present, enabling live execution. One minor undefined-variable defect (DEF-AFFLICTION-2, LOW) in `handleReExposure()` and one known wiring gap (GAP-AFFLICTION-1, MEDIUM) for CombatEngine integration. Neither blocks APPROVE on this item.

**Decision: APPROVE** with DEF-AFFLICTION-2 (LOW) and GAP-AFFLICTION-1 (MEDIUM) filed for next dev cycle.
