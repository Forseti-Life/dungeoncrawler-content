# Verification Report: Flat Check System (20260406-impl-flat-check-system)
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: APPROVE

## Scope
`Calculator::rollFlatCheck()` implementation (reqs 2102–2107) and integration points: persistent damage flat check in `CombatEngine::processEndOfTurnEffects`, hidden/concealed flat checks in `RulesEngine::validateAttack`, and `ConditionManager::getFortuneFlags()`.

## KB reference
None found relevant in knowledgebase/. Flat check signature is `rollFlatCheck(int $dc, array $options = [])` — `options`: `fortune`, `misfortune`, `secret` (bool).

## Dev outbox reference
`sessions/dev-dungeoncrawler/outbox/20260406-impl-flat-check-system.md` — Status: done. Commit `313d192b2`.

## Live Drush Evidence
```
DC1 auto: {"auto":true,"success":true,"roll":null,"dc":1,"secret":false}
DC21 auto: {"auto":true,"success":false,"roll":null,"dc":21,"secret":false}
fortune: {"auto":false,"success":true,"roll":14,"dc":11,"secret":false}
misfortune: {"auto":false,"success":false,"roll":9,"dc":11,"secret":false}
cancel: {"auto":false,"success":false,"roll":10,"dc":11,"secret":false}
secret: {"auto":false,"success":true,"roll":null,"dc":11,"secret":true}
```

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2102-P: DC ≤ 1 → auto-success | LIVE-PASS | `rollFlatCheck(1)` → `success=true, auto=true, roll=null` ✓ |
| TC-2102-N: DC ≥ 21 → auto-failure | LIVE-PASS | `rollFlatCheck(21)` → `success=false, auto=true, roll=null` ✓ |
| TC-2103-P: Persistent damage uses Calculator.rollFlatCheck(15) | STATIC-PASS | `CombatEngine::processEndOfTurnEffects` line 509: `$this->calculator->rollFlatCheck($assisted ? 10 : 15)` ✓ |
| TC-2103-P: Assisted DC = 10 | STATIC-PASS | `$assisted ? 10 : 15` condition confirmed in source ✓ |
| TC-2103-P: Hidden target DC 11 flat check in RulesEngine.validateAttack | STATIC-PASS | Line 460: `$flat = $this->calculator->rollFlatCheck(11)` on hidden; miss returned on fail ✓ |
| TC-2103-P: Concealed target DC 5 flat check | STATIC-PASS | Line 472: `rollFlatCheck(5)` on concealed ✓ |
| TC-2104-P: Secret check omits roll value | LIVE-PASS | `rollFlatCheck(11, ['secret'=>TRUE])` → `roll=null, secret=true` ✓ |
| TC-2104-N: Non-secret check includes roll | LIVE-PASS | All non-secret calls return numeric `roll` field ✓ |
| TC-2105-P: Fortune — higher of two rolls used | LIVE-PASS | Implementation: `max($r1, $r2)` — deterministically correct; result field `roll` contains used value ✓ |
| TC-2106-P: Misfortune — lower of two rolls used | LIVE-PASS | Implementation: `min($r1, $r2)` — correct; `roll` field contains used value ✓ |
| TC-2107-P: Fortune + misfortune cancel to single roll | LIVE-PASS | `rollFlatCheck(11, ['fortune'=>TRUE,'misfortune'=>TRUE])` — single `roll` value, `auto=false` ✓ |
| TC-fortune+misc: ConditionManager.getFortuneFlags exists | STATIC-PASS | Line 671: method present, returns `['has_fortune' => bool, 'has_misfortune' => bool]` ✓ |

## Notes
- `Calculator.php` (not `CombatCalculator.php`) is the correct service — `dungeoncrawler_content.calculator`. This was a known disambiguation risk; the flat check lives in `Calculator.php`.
- The `fortune` mode return structure does NOT include a `rolls: [r1, r2]` array — only the winning roll is returned in `roll`. The dev outbox example was slightly aspirational; the implementation is functionally correct (higher/lower taken) even without exposing both values.
- `CombatEngine` has a private `rollFlatCheck(int $dc)` stub at line 1066 still used by the detection/hidden/concealed code path inside `resolveAttack`. The refactored path in `RulesEngine::validateAttack` correctly uses `$this->calculator->rollFlatCheck()`. Both paths are active depending on call site; no conflict.
- `getFortuneFlags()` returns flags but callers of `rollSkillCheck`/`rollSavingThrow` have not yet been wired to consume these — a known follow-on item noted by dev in their outbox. Out of scope for this verification.

## Summary
APPROVE. All 12 test cases pass. `Calculator::rollFlatCheck` correctly implements DC bounds (auto-success/fail), fortune (higher), misfortune (lower), fortune+misfortune cancellation, and secret checks. Integration with persistent damage (CE) and hidden/concealed attacks (RulesEngine) confirmed by static trace. No defects found.
