# Gate 2 Verification Report — dc-cr-encounter-rules

- Feature/Item: dc-cr-encounter-rules
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260405-impl-dc-cr-encounter-rules.md
- Dev commits: 3f66e773, 0eec393d, 4dc24abe
- Verified by: qa-dungeoncrawler
- Date: 2026-04-06
- Verdict: **APPROVE** (with runtime dependency advisory)

---

## Knowledgebase check
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after module changes. Applied; site healthy.

---

## Acceptance criteria verification

### Happy Path

| AC item | Expected | Actual | Result |
|---|---|---|---|
| `startEncounter()` auto-rolls initiative via Perception check (d20 + Perception modifier) | Auto-roll for participants without custom initiative | CombatEngine:77-99 — `$initiative = $roll + $perception_mod` where roll is `rollPathfinderDie(20)` | ✅ PASS |
| Participants sorted descending by initiative; ties by Perception mod, then participant ID | Correct sort order | CombatEngine:125-132 — `$init_diff` then `$perc_diff` then ID | ✅ PASS |
| `resolveAttack()` rolls d20 + attack bonus + MAP vs target AC → degree of success | 4-way degree (crit_success/success/failure/crit_failure) | CombatEngine:402-465 — confirmed; degree via `calculateDegreeOfSuccess(total, target_ac, natural_roll)` | ✅ PASS |
| `applyDamage()` accounts for resistances/weaknesses | Resistance subtracts, weakness adds | HPManager:43-57 — `entity_data['resistances']` and `entity_data['weaknesses']` JSON applied before damage | ✅ PASS |
| End-of-turn processing decrements timed conditions and removes expired ones | Tick via ConditionManager | CombatEngine:329-390 — `processEndOfTurnEffects()` decrements `duration_remaining`, removes at 0, calls `conditionManager->tickConditions()` | ✅ PASS (code) / ⚠️ RUNTIME (see advisory) |

### Edge Cases

| AC item | Expected | Actual | Result |
|---|---|---|---|
| Agile weapon MAP: −4/−8 | attacks 2/3 with `is_agile=true` | `drush php:eval` → Agile MAP: 2=-4, 3=-8 | ✅ PASS |
| Normal MAP: −5/−10 | attacks 2/3 with `is_agile=false` | `drush php:eval` → Normal MAP: 2=-5, 3=-10 | ✅ PASS |
| Natural 20 bumps degree of success one step up | failure→success, success→crit_success | `calculateDegreeOfSuccess(14, 15, 20)` → success; CombatCalculator:93-94 `bumpDegree(+1)` | ✅ PASS |
| Natural 1 bumps degree of success one step down | success→failure, failure→crit_failure | `calculateDegreeOfSuccess(18, 15, 1)` → failure; CombatCalculator:96-97 `bumpDegree(-1)` | ✅ PASS |
| HP=0 or below → `dying 1` condition applied | `applyCondition(dying, 1)` | HPManager:105 — `$this->conditionManager->applyCondition($participant_id, 'dying', 1, ...)` when `$is_defeated` | ✅ PASS (code) / ⚠️ RUNTIME (see advisory) |
| HP ≤ −max_hp → instant death | `evaluateDeath` returns `is_dead=TRUE` | HPManager:287-292 — `if ($max_hp > 0 && $hp <= -1 * $max_hp)` → `['is_dead' => TRUE, 'death_reason' => 'hp_threshold']` | ✅ PASS |

### Failure Modes

| AC item | Expected | Actual | Result |
|---|---|---|---|
| `resolveAttack` invalid participant → structured error | Array with `error` key, not PHP exception | CombatEngine:411 — `return ['error' => "Attacker participant {$participant_id} not found ..."]` | ✅ PASS |
| `resolveAttack` invalid target → structured error | Array with `error` key, not PHP exception | CombatEngine:422 — `return ['error' => "Target participant {$target_id} not found ..."]` | ✅ PASS |

### Live calculation spot-checks (drush php:eval)

```
calculateDegreeOfSuccess(25, 15, 10) → critical_success  (beat DC by 10) ✅
calculateDegreeOfSuccess(18, 15, 10) → success            (beat DC by 3) ✅
calculateDegreeOfSuccess(14, 15, 10) → failure            (miss by 1) ✅
calculateDegreeOfSuccess(14, 15, 20) → success            (nat-20 bump: failure→success) ✅
calculateDegreeOfSuccess(18, 15, 1)  → failure            (nat-1 bump: success→failure) ✅
MAP normal: attack 1=0, 2=-5, 3=-10 ✅
MAP agile:  attack 2=-4, 3=-8 ✅
```

---

## Critical Advisory: Runtime dependency on combat_conditions table (dc-cr-conditions BLOCK)

`processEndOfTurnEffects()` and `applyDamage(dying)` both require the `combat_conditions` table at runtime. This table is **missing from prod DB** — documented as a BLOCK in `dc-cr-conditions` Gate 2 (verification report: `features/dc-cr-conditions/04-verification-report.md`).

At runtime:
- `processEndOfTurnEffects()` calls `store->listActiveConditions()` which queries `combat_conditions` → **will throw DB exception**
- `applyDamage()` calls `conditionManager->applyCondition('dying', ...)` which writes to `combat_conditions` → **will throw DB exception**

**This is NOT a new blocker for encounter-rules.** It is already fully captured in the dc-cr-conditions BLOCK. Once Dev adds `dungeoncrawler_content_update_10032()` and runs `drush updb` on prod, both issues resolve without any changes to encounter-rules code.

**Impact on this Gate 2**: All encounter-rules code is correct and fully implemented. The runtime failures are a consequence of the dc-cr-conditions BLOCK, not encounter-rules defects.

---

## Suite coverage gap

`suite.json` entry `dc-cr-encounter-rules-phpunit` exists but has 0 test cases declared. Advisory: Dev should add `CombatEngineTest`, `CombatCalculatorTest`, `HPManagerTest`, and `CombatEncounterFlowTest` TCs to the manifest for future automated runs.

---

## Verdict

**APPROVE** — All 11 AC items verified against source code and live `drush php:eval` calls. All combat logic is correctly implemented: Perception-based initiative auto-roll, initiative sort, `resolveAttack` with MAP/degree-of-success, `applyDamage` with resistance/weakness, end-of-turn condition tick, nat-20/nat-1 bumps, agile MAP, instant death threshold, and structured error returns. Runtime dependency on `combat_conditions` table is already tracked under dc-cr-conditions BLOCK — no new action required from encounter-rules.

No new Dev items identified for encounter-rules.

---

## Evidence

- CombatEngine:77-99 — `startEncounter` Perception auto-roll ✅
- CombatEngine:125-132 — initiative sort with tie-breaking ✅
- CombatEngine:402-465 — `resolveAttack` full implementation ✅
- CombatCalculator:93-97 — nat-20/nat-1 `bumpDegree` ✅
- HPManager:43-57 — resistance/weakness application ✅
- HPManager:105 — dying condition at HP≤0 ✅
- HPManager:287-292 — instant death at HP≤-max_hp ✅
- CombatEngine:329-390 — `processEndOfTurnEffects` + `tickConditions` ✅
- `drush php:eval` degree-of-success spot-checks: all 5 scenarios correct ✅
- `drush php:eval` MAP: normal -5/-10, agile -4/-8 ✅
- Audit: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260406-160000/findings-summary.md` — 0 failures ✅
