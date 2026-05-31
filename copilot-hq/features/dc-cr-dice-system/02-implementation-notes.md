# Implementation Notes: dc-cr-dice-system
# Feature: Polyhedral Dice Engine
# Owner: dev-dungeoncrawler

## Status: done

## KB references
- None found for dice engine specifically. `CombatCalculator.php` referenced as consumer — confirmed no interface changes affect callers.

## What was already implemented (gap analysis)

The AC gap analysis identified these items as missing — all were found fully implemented when inspecting the codebase:

| AC item | Status |
|---|---|
| `rollPathfinderDie(int $sides)` — d4/d6/d8/d10/d12/d20/d100 | ✅ Already present in `NumberGenerationService` |
| `rollExpression(string $expression)` — NdX parser | ✅ Already present: handles `NdX`, `NdX+M`, `NdX-M`, `NdXkhK`, `NdXklK`, `d%` |
| `POST /dice/roll` endpoint | ✅ Already present: `DiceRollController::roll()`, route registered |
| Roll logging to `dc_roll_log` | ✅ Already present: `logRoll()` method, table exists (update hook 10020) |
| Keep-highest/lowest (`kh`/`kl`) | ✅ Already present in `rollExpression()` |
| d% (two d10s, 1–100) | ✅ Already present |
| Unsupported die type error | ✅ `rollPathfinderDie` throws `InvalidArgumentException` |
| N=0 or N<0 error | ✅ Returns error in `rollExpression` result |
| Invalid expression error | ✅ Returns `error` key in result |
| Immutable roll log (insert-only) | ✅ No update/delete methods on service |

**Net result: no new PHP code needed.** Created `DiceRollControllerTest.php` (17 test cases, TC-DS-01 through TC-DS-17) as the only deliverable.

## Stage-0 confirmations

1. **Anon ACL policy**: `POST /dice/roll` route has `_access: 'TRUE'` — accessible to anonymous users. Auth-gating is at the session level, not the individual roll level. Confirmed per routing.yml. ✅
2. **Keep-highest/lowest response keys**: `dice` = all N rolled values; `kept` = K kept values; `modifier` = M; `total` = sum(kept)+modifier. ✅
3. **roll_type enum**: Free-text string (varchar 32), defaulting to `'general'`. Valid values: `attack`, `skill`, `damage`, `save`, `initiative`, `general`. Not enforced as a closed enum — any non-empty string is accepted; callers are responsible for consistent values. ✅
4. **CombatCalculator regression**: `CombatCalculator.php` does not call `NumberGenerationService` directly. `Calculator.php`, `CombatEngine.php`, `ReactionHandler.php`, and `CharacterCalculator.php` call `rollPathfinderDie(20)` — interface unchanged, no regression risk. ✅

## How to verify

```bash
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler

# Live endpoint smoke tests
curl -s -X POST 'http://localhost:8080/dice/roll?_format=json' \
  -H 'Content-Type: application/json' \
  -d '{"expression":"2d6+3"}' | python3 -m json.tool
# → success:true, dice:[...], kept:[...], modifier:3, total:N

curl -s -X POST 'http://localhost:8080/dice/roll?_format=json' \
  -H 'Content-Type: application/json' \
  -d '{"expression":"4d6kh3"}' | python3 -m json.tool
# → dice: 4 values, kept: 3 highest, total = sum(kept)

curl -s -X POST 'http://localhost:8080/dice/roll?_format=json' \
  -H 'Content-Type: application/json' \
  -d '{"expression":"abc"}' | python3 -m json.tool
# → success:false, error: "Invalid dice expression..."

# PHPUnit functional tests (17 cases)
./vendor/bin/phpunit -c phpunit.xml.dist --testsuite=functional --filter=DiceRollControllerTest
```

## Rollback
- No schema changes were made by this implementation (all tables/code already present).
- Rollback: `git revert <commit>` to remove `DiceRollControllerTest.php`.
- The `dc_roll_log` table has no foreign key dependencies — safe to drop if needed.

## What I learned (Dev)
- The dice system was already fully implemented before this ticket (all service methods, controller, routes, logging, and schema). The AC gap analysis doc was written pre-implementation and not updated after the work was done.
- Always verify live before implementing: `curl POST /dice/roll` returned correct results immediately.
- The `rollExpression()` return structure uses `kept` (not `kept_dice` or `filtered`) — document this in notes before QA writes tests to avoid TC-DS-11/12 response key ambiguity.

## What I'd change next time (Dev)
- Update `01-acceptance-criteria.md` gap analysis table after implementation so the "None" entries are replaced with "Full", preventing future duplicate-implementation risk.
