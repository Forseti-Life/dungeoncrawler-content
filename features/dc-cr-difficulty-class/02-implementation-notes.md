# Implementation Notes: dc-cr-difficulty-class
# Feature: Difficulty Class (DC) System
# Owner: dev-dungeoncrawler

## Status: done

## KB references
- None found for DC/degree-of-success specifically. AC notes `CombatCalculator.php` references PF2E Core Rulebook p. 445 — same pattern followed here.
- Regression dependency: `calculateMultipleAttackPenalty()` verified unchanged (TC-DC-17).

## What was already implemented (gap analysis)

AC gap analysis identified some items as missing or partial — all were found fully implemented when inspecting the codebase:

| AC item | Gap analysis claim | Actual status |
|---|---|---|
| `calculateDegreeOfSuccess()` — partial stub | "Partial (may be stubbed with TODO)" | ✅ Fully implemented: handles all 4 degrees |
| `determineDegreOfSuccess()` — new wrapper | `[EXTEND]` | ✅ Already present: delegates to `calculateDegreeOfSuccess` with nat20/nat1 bool support |
| Natural 20/1 bumps + clamp at max/min | `[EXTEND]` | ✅ `bumpDegree()` helper clamps at index bounds (0 = critical_failure, 3 = critical_success) |
| `SIMPLE_DC` constant (levels 1–20) | `[NEW]` | ✅ Present: full 20-level table as class constant |
| `getSimpleDC(int $level)` | `[NEW]` | ✅ Present: error on ≤0, capped at 20 for >20 |
| `TASK_DC` constant (6 tiers) | `[NEW]` | ✅ Present: trivial→10, low→15, moderate→20, high→25, extreme→30, incredible→40 |
| `getTaskDC(string $difficulty)` | `[NEW]` | ✅ Present: case-insensitive via `strtolower(trim())`; error on unknown tier |
| `POST /rules/check` endpoint | `[NEW]` | ✅ `RulesCheckController::check()` present; route registered |
| Input validation (non-integer roll/dc) | `[NEW]` | ✅ Controller validates `is_numeric()` for roll and dc; returns HTTP 400 |

**Net result: no new PHP code needed.** Created `RulesCheckControllerTest.php` (17 test cases, TC-DC-01 through TC-DC-17) as the only deliverable.

## Stage-0 confirmations

1. **Simple DC table values (TC-DC-09)**: Hardcoded in `CombatCalculator::SIMPLE_DC`. Canonical PF2E Table 10-5 values:
   - Level 1→15, 2→16, 3→18, 4→19, 5→20
   - Level 6→22, 7→23, 8→24, 9→26, 10→27
   - Level 11→28, 12→30, 13→31, 14→32, 15→34
   - Level 16→35, 17→36, 18→38, 19→39, 20→40 ✅

2. **Task DC tier values (TC-DC-12)**: Hardcoded in `CombatCalculator::TASK_DC`. Values are monotonically increasing:
   - trivial=10, low=15, moderate=20, high=25, extreme=30, incredible=40 ✅

3. **Difficulty case-sensitivity (TC-DC-13)**: `getTaskDC()` normalizes via `strtolower(trim())` — **case-insensitive**. `"MODERATE"` resolves to 20 (success, not error). TC-DC-13 test reflects this. ✅

4. **Anon ACL for POST /rules/check (TC-DC-16)**: Route has `_access: 'TRUE'` — identical policy to `POST /dice/roll`. Anonymous accessible at route level; auth-gating is at game session level, not individual check level. Confirmed via `dungeoncrawler_content.routing.yml`. ✅

## How to verify

```bash
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler

# Live endpoint smoke tests
curl -s -X POST 'http://localhost:8080/rules/check?_format=json' \
  -H 'Content-Type: application/json' \
  -d '{"roll":25,"dc":15}' | python3 -m json.tool
# → success:true, degree:"critical_success"

curl -s -X POST 'http://localhost:8080/rules/check?_format=json' \
  -H 'Content-Type: application/json' \
  -d '{"roll":14,"dc":15,"natural_twenty":true}' | python3 -m json.tool
# → success:true, degree:"success" (failure bumped to success by nat20)

curl -s -X POST 'http://localhost:8080/rules/check?_format=json' \
  -H 'Content-Type: application/json' \
  -d '{"roll":"abc","dc":15}' | python3 -m json.tool
# → success:false, error:"Missing or invalid field: roll (integer required)."

# PHPUnit functional tests (17 cases)
./vendor/bin/phpunit -c phpunit.xml.dist --testsuite=functional --filter=RulesCheckControllerTest
```

## Rollback
- No schema changes were made (DC tables are PHP constants, not DB).
- Rollback: `git revert <commit>` removes `RulesCheckControllerTest.php` and this notes file only.
- No FK dependencies; no DB migrations involved.

## What I learned (Dev)
- The DC system was already fully implemented before this ticket (all service methods, controller, routes). The AC gap analysis doc was written pre-implementation and not updated after the work was done — same pattern as dc-cr-dice-system.
- Always verify live before implementing: `curl POST /rules/check` returned correct results immediately.
- `getTaskDC()` is case-insensitive (strtolower normalization) — important to document so QA tests don't incorrectly expect `"MODERATE"` to fail.

## What I'd change next time (Dev)
- Update `01-acceptance-criteria.md` gap analysis table after implementation so "None"/"Partial" entries are replaced with "Full" — prevents future duplicate-implementation risk across the whole release-b feature set.
