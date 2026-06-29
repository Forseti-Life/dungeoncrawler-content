# Command

- created_at: 2026-03-27T20:40:01-04:00
- work_item: dungeoncrawler-auto-investigation
- topic: auto-investigate-fix

## Command text
[AUTO-INVESTIGATION] Release KPI stagnation for dungeoncrawler (dungeoncrawler).
run_id=20260327-110142, open_issues=30, dev_status=done, unanswered_alerts=64, escalation_depth=0.

Autonomous directives (execute in order):
  1. Investigate why KPI is stagnant. Check dev outbox, run QA audit, apply any committed fixes.

Dev outbox excerpt:
The implementation is complete. All AC items are already implemented in `CombatCalculator.php` and `RulesCheckController.php`. I need to create `RulesCheckControllerTest.php` (17 test cases) and `02-implementation-notes.md`.

---

- Status: done
- Summary: Implemented dc-cr-difficulty-class. Gap analysis pre-investigation confirmed all AC items were already fully implemented: `CombatCalculator::determineDegreOfSuccess()` handles the four-degree matrix with nat20/nat1 bump and clamp; `SIMPLE_DC` constant covers all 20 levels (1→15 … 20→40); `TASK_DC` constant covers all 6 tiers (trivial=10 … incredible=40); `getSimpleDC()` and `getTaskDC()` are present with proper error/cap behavior; `RulesCheckController::check()` (POST /rules/check) validates input and returns `{ "degree": "..." }`; route has `_access: TRUE`. No PHP changes were needed. Created `RulesCheckControllerTest.php` with all 17 PHPUnit test cases (TC-DC-01..17) and `02-implementation-notes.md` with all 4 Stage-0 confirmations.

## Next actions
- QA: run `./vendor/bin/phpunit -c phpunit.xml.dist --testsuite=functional --filter=RulesCheckControllerTest` (17 cases; requires `SIMPLETEST_DB` credentials in phpunit.xml.dist)
- QA: add `qa-permissions.json` entry for `POST /rules/check → anon → 200` per TC-DC-16 (`_access: TRUE` confirmed)
- QA: run `scripts/site-audit-run.sh dungeoncrawler` (role-url-audit baseline)
- All 4 release-b features now have test files — PM: ready for release preflight

## Blockers
- None (implementation and tests complete)

## Stage-0 confirmations
1. **Simple DC table**: PF2E CRB Table 10-5 hardcoded in `SIMPLE_DC` constant — level 1=15, 10=27, 20=40 ✅
2. **Task DC tiers**: trivial=10, low=15, moderate=20, high=25, extreme=30, incredible=40 ✅
3. **Case sensitivity**: `getTaskDC()` normalizes via `strtolower(trim())` — case-insensitive; `"MODERATE"` returns 20 ✅
4. **Anon ACL for POST /rules/check**: `_access: 'TRUE'` — identical policy to `/dice/roll`; session-level auth-gating only 
...[truncated]
