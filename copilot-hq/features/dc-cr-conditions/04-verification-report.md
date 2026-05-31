# Gate 2 Verification Report — dc-cr-conditions

- QA seat: qa-dungeoncrawler
- Date: 2026-04-06
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260405-impl-dc-cr-conditions.md
- Dev commit reviewed: `3a39ca30`
- **VERDICT: BLOCK**

---

## Summary

Code implementation of the conditions system is complete and logically correct. However, two required DB tables (`combat_conditions`, `combat_round_actions`) are **absent from the production database**, and no update hook exists to create them. Every runtime path that reads or writes condition state (`applyCondition()`, `tickConditions()`, `processDying()`, `getActiveConditions()`) will throw a database exception. No unit test files exist for `ConditionManager` or `RulesEngine` conditions path.

---

## AC verification results

| AC item | Evidence | Result |
|---|---|---|
| Condition catalog constant — 35 conditions, is_valued, max_value, effects, end_trigger | `ConditionManager.php:33-69` — 35 entries, all fields present | ✅ PASS |
| `applyCondition()` validates catalog before insert | `:87-99` — `array_key_exists` check throws `InvalidArgumentException` on unknown type | ✅ PASS |
| `applyCondition()` stacking: valued condition increases value (capped), non-valued is idempotent | `:107-125` — existing row check; non-valued returns FALSE; valued updates via `min(max_value, existing+new)` | ✅ PASS |
| `tickConditions()` decrements valued conditions at end of turn | `:192` — method exists; reads active conditions, decrements `end_of_turn` valued conditions, soft-removes at 0 | ✅ PASS (code only) |
| `processDying()` flat DC 10 — 4 outcomes (crit fail +2, fail +1, success -1, crit success -2) | `:252-320` — `mt_rand(1,20)`; roll=1→+2, ≤9→+1, ≤19→-1, 20→-2; dying≥4→dead | ✅ PASS (code only) |
| `processDying()` dying-4 transitions participant to dead | `:300-315` — updates `combat_participants.is_defeated`, removes dying row | ✅ PASS (code only) |
| `RulesEngine::checkConditionRestrictions()` — paralyzed/unconscious/petrified/dying block act; grabbed/immobilized/restrained block move | `RulesEngine.php:248-283` — fully implemented with `blocking_act` and `blocking_move` lists | ✅ PASS |
| `combat_conditions` table exists in production DB | `drush schema->tableExists('combat_conditions')` → **MISSING** | ❌ **BLOCK** |
| `combat_round_actions` table exists in production DB | `drush schema->tableExists('combat_round_actions')` → **MISSING** | ❌ **BLOCK** |
| Unit tests: `ConditionManagerTest.php` | `find … -name ConditionManagerTest.php` → **not found** | ⚠️ ADVISORY |
| Unit tests: `RulesEngineTest.php` | `find … -name RulesEngineTest.php` → **not found** | ⚠️ ADVISORY |

---

## Block details

### BLOCK 1 — Missing DB tables in production

**Root cause**: `combat_conditions` and `combat_round_actions` are defined in `hook_schema()` (`.install:376, ~430`), which only runs on a fresh module install. The module was already installed before these tables were added to the schema. There are 31 update hooks present (`_update_10001` through `_update_10031`); none create these tables. `drush updatedb-status` confirms: "No database updates required." The tables have never existed in production.

**Impact**: All of these will throw `Drupal\Core\Database\DatabaseExceptionWrapper` at runtime:
- `applyCondition()` — INSERT into `combat_conditions`
- `tickConditions()` — SELECT + UPDATE on `combat_conditions`
- `processDying()` — SELECT + UPDATE on `combat_conditions`
- `getActiveConditions()` — SELECT on `combat_conditions`
- Any `RulesEngine` method that calls `checkConditionRestrictions()` with a DB-backed participant

**Fix required**: Dev must add `dungeoncrawler_content_update_10032()` (or next available) that creates `combat_conditions` and `combat_round_actions` using the schema already defined in `hook_schema()`. Then run `drush updb` on production.

### ADVISORY — No unit test files

The acceptance criteria specifies `ConditionManagerTest.php` and `RulesEngineTest.php` as test path targets (marked `[TEST-ONLY]`). Neither file exists. This is advisory (not a Gate 2 hard blocker per se) but is a regression risk for future combat changes. Recommend Dev create these before Gate 4.

---

## Prod DB state verification

```
drush ev "foreach(['combat_encounters','combat_participants','combat_conditions','combat_round_actions'] ...)"
combat_encounters:     exists
combat_participants:   exists
combat_conditions:     MISSING
combat_round_actions:  MISSING
```

---

## Required fix before APPROVE

1. Dev adds `dungeoncrawler_content_update_10032()` to create `combat_conditions` + `combat_round_actions` from existing schema definitions.
2. Dev runs `drush updb` on production (or documents in release runbook).
3. QA re-verifies: `drush ev "print $schema->tableExists('combat_conditions') ? 'OK' : 'FAIL';"` on prod.

---

## Re-verification path

```bash
cd /var/www/html/dungeoncrawler
./vendor/bin/drush ev "
  \$schema = \Drupal::database()->schema();
  foreach (['combat_conditions', 'combat_round_actions'] as \$t) {
    print \"\$t: \" . (\$schema->tableExists(\$t) ? 'OK' : 'MISSING') . PHP_EOL;
  }
"
# Expected: both OK
```
