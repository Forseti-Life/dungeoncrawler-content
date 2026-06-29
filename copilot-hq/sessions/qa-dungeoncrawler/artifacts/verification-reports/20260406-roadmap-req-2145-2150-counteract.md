# Verification Report: Reqs 2145–2150 — Counteract Rules
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: **BLOCK** — DEF-2145: `Calculator::calculateDegreeOfSuccess()` undefined; `attemptCounteract()` fatal at runtime

## Scope
Counteract rules (reqs 2145–2150): check formula, counteract level calculation, and four-degree outcome table.

Inbox marks all 6 as "pending" — `CounteractService` is fully implemented and registered in services.yml, but `attemptCounteract()` throws a fatal error at runtime due to an API mismatch (see DEF-2145 below).

## KB reference
None found relevant to counteract in knowledgebase/.

## Static Analysis Results (all PASS)

| TC | Verdict | Notes |
|---|---|---|
| TC-2145-P: `attemptCounteract()` uses `spell_attack_bonus` + condition mods + d20 | STATIC-PASS | Lines 108–110: `$spell_mod = $caster['spell_attack_bonus']`; `$condition_mod = conditionManager->getConditionModifiers(…,'spell_attack',…)`; `$check_total = $natural_roll + $spell_mod + $condition_mod` |
| TC-2145-P: result array has required keys | STATIC-PASS | Returns: `natural_roll`, `check_total`, `degree`, `counteract_level`, `target_level`, `target_dc`, `success` |
| TC-2146-P: `getCounteractLevel('spell', 3)` = 3 | LIVE-PASS | Drush: `3` |
| TC-2146-P: `getCounteractLevel('ability', 5)` = 3 (ceil(5/2)) | LIVE-PASS | Drush: `3` |
| TC-2146-P: `getCounteractLevel('creature', 7)` = 4 (ceil(7/2)) | LIVE-PASS | Drush: `4` |
| TC-2147-P: crit_success → target_level ≤ caster_level+3 | STATIC-PASS | `match: 'critical_success' => $target_level <= ($caster_counteract_level + 3)` |
| TC-2147-N: target=7, caster_cl=3 → crit_success still cannot counteract level 7 | LIVE-PASS | 7 <= 6 = FALSE ✓ |
| TC-2148-P: success → target_level ≤ caster_level+1 | STATIC-PASS | `match: 'success' => $target_level <= ($caster_counteract_level + 1)` |
| TC-2148-N: target=5, caster_cl=3 → success cannot counteract level 5 | LIVE-PASS | 5 <= 4 = FALSE ✓ |
| TC-2149-P: failure → target_level < caster_level | STATIC-PASS | `match: 'failure' => $target_level < $caster_counteract_level` |
| TC-2149-N: target=3, caster_cl=3 → failure cannot counteract equal level | LIVE-PASS | 3 < 3 = FALSE ✓ |
| TC-2150-P: crit_failure → success=FALSE | LIVE-PASS | `'critical_failure' => FALSE` ✓ |

## Live Drush Results (`getCounteractLevel` only)
```
REQ-2146 spell(3)=3 ability(5)=3 creature(7)=4 spell(0)=0
REQ-2147 crit_success: target=6 (PASS) target=7 (PASS)
REQ-2148 success: target=4 (PASS) target=5 (PASS)
REQ-2149 failure: target=2 (PASS) target=3 (PASS)
REQ-2150 crit_failure: FALSE=PASS
```

`attemptCounteract()` live call: **fatal** — `Call to undefined method Calculator::calculateDegreeOfSuccess()` (confirmed in production).

## Defects

### DEF-2145 (CRITICAL) — Calculator API mismatch blocks attemptCounteract()
- **File**: `CounteractService.php` line 112
- **Code**: `$degree = $this->calculator->calculateDegreeOfSuccess($check_total, $target_dc, $natural_roll);`
- **Problem**: `Calculator` exposes `determineDegreeOfSuccess($roll, $dc, $is_natural_1, $is_natural_20)` but NOT `calculateDegreeOfSuccess()`. The latter lives on `CombatCalculator`.
- **Impact**: `attemptCounteract()` throws fatal PHP error on any live call; counteract system is entirely non-functional at runtime.
- **Retroactive impact**: Same call (`$this->calculator->calculateDegreeOfSuccess()`) exists in `AfflictionManager::applyAffliction()` line 67 — afflictions live runtime path also broken. Prior afflictions APPROVE was based on static analysis only.
- **Fix options**:
  1. Add a `calculateDegreeOfSuccess(int $result, int $dc, ?int $naturalRoll)` proxy to `Calculator` delegating to `$this->combatCalculator`.
  2. Change both callers to use `determineDegreeOfSuccess($total, $dc, $naturalRoll === 1, $naturalRoll === 20)`.
  - Option 1 preferred: normalizes the API without changing call sites; avoids dual natural-roll booleans.

## Summary
Logic for all 6 counteract requirements is correct (static + partial live pass). `getCounteractLevel` and the degree-based level comparison match are both correct. The service is blocked from production use by DEF-2145 — a single missing proxy method on `Calculator`. Recommend dev-dungeoncrawler add `Calculator::calculateDegreeOfSuccess()` and re-verify `AfflictionManager` live runtime.
