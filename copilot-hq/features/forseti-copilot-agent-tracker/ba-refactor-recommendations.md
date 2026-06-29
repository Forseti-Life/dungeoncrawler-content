# BA Refactor Recommendations: copilot_agent_tracker

**Date:** 2026-03-08  
**Author:** ba-forseti-agent-tracker  
**Source module:** `web/modules/custom/copilot_agent_tracker`  
**Primary file reviewed:** `src/Controller/DashboardController.php` (4054 lines)

---

## Summary

Six refactors identified, ranked by ROI. R1–R3 are high-priority and should be scheduled in the next dev cycle. R4–R6 are moderate and can follow.

---

## R1 — Hardcoded HQ repo paths (ROI: 15)

**Problem:** Lines 33–37 of `DashboardController.php` define class constants with hardcoded `/home/keithaumiller/copilot-sessions-hq` paths. Additional hardcoded instances appear at lines 1114, 1119, and 1158 (glob calls). Lines 457 and 702 correctly use `getenv('COPILOT_HQ_ROOT') ?: '/home/keithaumiller/copilot-sessions-hq'` — the same pattern should be applied everywhere.

**Affected lines:**
- Lines 33–37: class constants
- Lines 1114, 1119, 1158: glob path construction

**Recommendation:** Replace all hardcoded instances with `getenv('COPILOT_HQ_ROOT') ?: '/home/keithaumiller/copilot-sessions-hq'`. Extract a single `private function hqRoot(): string` helper and call it everywhere.

**PM decision flag:** Confirm `COPILOT_HQ_ROOT` is the canonical env var name (or specify the correct one).

**Dev DoD:**
- [ ] No literal `/home/keithaumiller/copilot-sessions-hq` strings remain in DashboardController.php
- [ ] `COPILOT_HQ_ROOT` env var is documented in module README
- [ ] Existing tests pass; no path regressions in dashboard routes

---

## R2 — God Object: DashboardController is 4054 lines (ROI: 12)

**Problem:** `DashboardController.php` covers 6+ distinct domains: session monitoring, release cycle, LangGraph, operator inbox (`waitingonkeith`), release notes, and agent detail. This creates merge conflicts, hard-to-test logic, and cognitive overhead for every change.

**Recommendation:** Phase extraction over 2–3 release cycles:
- Phase 1: Extract `OperatorInboxController` (operator inbox / waitingonkeith routes — self-contained)
- Phase 2: Extract `ReleaseNotesController`
- Phase 3: Extract remaining domains as natural seams emerge

**PM decision flag:** Confirm phasing is acceptable (avoid big-bang extraction in one cycle).

**Dev DoD (Phase 1):**
- [ ] OperatorInboxController created, routes updated in routing.yml
- [ ] DashboardController no longer contains waitingonkeith methods
- [ ] All operator inbox routes return equivalent responses (no regression)

---

## R3 — `waitingonkeith` personal name in production code (ROI: 10)

**Problem:** Route names, method names, DB column references, and table keys use `waitingonkeith` (a personal name). This is a maintainability and professionalism issue — the name should be `operator-inbox` or `operator_inbox`.

**Affected areas:**
- Route names in `copilot_agent_tracker.routing.yml`
- Method names in DashboardController.php
- Any DB keys referencing the personal name

**Recommendation:** Rename to `operator_inbox` (underscore for PHP/DB) / `operator-inbox` (hyphen for routes). Do as part of Phase 1 extraction (R2) to minimize churn.

**Dev DoD:**
- [ ] No `waitingonkeith` strings in PHP source, routing.yml, or permissions.yml
- [ ] DB migration written if column/key names changed
- [ ] Route redirects in place if old route names were publicly documented

---

## R4 — 21 direct DB calls in controller, bypassing AgentTrackerStorage (ROI: 8)

**Problem:** `DashboardController.php` makes 21 calls to `$this->database->select()/insert()/update()` directly, bypassing `AgentTrackerStorage.php`. This duplicates DB logic, makes it impossible to test controllers in isolation, and defeats the storage abstraction.

**Recommendation:** Move all direct DB calls to `AgentTrackerStorage` service methods. Inject `AgentTrackerStorage` into DashboardController and call service methods.

**Dev DoD:**
- [ ] Zero `$this->database->select/insert/update/delete` calls remain in DashboardController.php
- [ ] All moved logic is covered by a unit test in AgentTrackerStorage
- [ ] No behavioral regressions in dashboard routes

---

## R5 — Unused `post copilot agent telemetry` permission (ROI: 6)

**Problem:** `copilot_agent_tracker.permissions.yml` defines `post copilot agent telemetry`. The API route (`copilot_agent_tracker.api.telemetry`) uses `_access: 'TRUE'` with `_csrf_request_header_mode: TRUE` — the permission is never checked by any route or code path.

**Recommendation:** Either (a) remove the dead permission entry, or (b) enforce it on the telemetry API route as an actual access check.

**PM decision flag:** Decide between remove (a) vs enforce (b). If the telemetry API is intended to be unauthenticated (header-token only), remove the dead permission.

**Dev DoD:**
- [ ] If removed: permission entry deleted, no remaining references
- [ ] If enforced: routing.yml updated to check permission; tests updated

---

## R6 — No payload size limit on telemetry API (ROI: 6)

**Problem:** `ApiController.php` calls `json_decode($content)` on the raw POST body with no size check. A large or malformed payload can consume memory without bound.

**Recommendation:** Add a 64 KB size check before `json_decode`. Return HTTP 413 if exceeded.

**Dev DoD:**
- [ ] `strlen($content) > 65536` check added before json_decode in ApiController
- [ ] Returns `JsonResponse` with status 413 and `{"error": "Payload too large"}`
- [ ] Unit test covers the 413 response path

---

## PM decisions needed

| # | Decision |
|---|---------|
| R1 | Confirm `COPILOT_HQ_ROOT` is the correct env var name |
| R2 | Confirm phased extraction (Phase 1 = operator inbox) is approved for next cycle |
| R5 | Remove dead permission vs enforce on telemetry route |

## Prioritized backlog order

1. R1 (ROI 15) — low risk, high leverage, quick fix
2. R2 (ROI 12) — medium risk, high long-term value; Phase 1 only
3. R3 (ROI 10) — best done in tandem with R2 Phase 1
4. R4 (ROI 8) — higher effort, but addresses tech debt accumulation
5. R5 (ROI 6) — small cleanup, needs PM call first
6. R6 (ROI 6) — defensive hardening, independent of other refactors
