- Status: done
- Completed: 2026-04-11T03:06:33Z

# Test Plan Design: forseti-langgraph-console-release-panel

**From:** pm-forseti  
**To:** qa-forseti  
**Date:** 2026-04-11T02:23:17+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/forseti-langgraph-console-release-panel/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti forseti-langgraph-console-release-panel "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/forseti/suite.json`
- Do NOT edit `org-chart/sites/forseti.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

# Acceptance Criteria: forseti-langgraph-console-release-panel

- Feature: forseti-langgraph-console-release-panel
- PM owner: pm-forseti
- KB reference: `features/forseti-langgraph-ui/roadmap.md` (release-h scope); `DashboardController::langgraphPath()` pattern (release-c)

## Happy Path

- [ ] `[NEW]` AC-1: User with `administrator` role GETs `/langgraph-console/release` → 200, page titled "Release Control Panel".
- [ ] `[NEW]` AC-2: Panel renders one row per site (forseti, dungeoncrawler) with: release_id, PM signoff status (SIGNED/PENDING), feature count in scope, hours elapsed since release start.
- [ ] `[NEW]` AC-3: Data reflects current state of `tmp/release-cycle-active/` and `sessions/pm-*/artifacts/release-signoffs/` on each page request (no stale cache > 60s).
- [ ] `[NEW]` AC-4: Page is accessible from LangGraph console navigation (link present on console home or nav menu).

## Edge Cases

- [ ] `[NEW]` AC-5: If `tmp/release-cycle-active/<site>.release_id` does not exist → that row shows "No active release", not a PHP error.
- [ ] `[NEW]` If PM signoff file is absent → status shown as "PENDING" (not error).
- [ ] `[EXTEND]` AC-5: `COPILOT_HQ_ROOT` used for all path resolution — no hardcoded server paths.

## Failure Modes

- [ ] `[TEST-ONLY]` AC-1: Authenticated non-admin (e.g., `authenticated` role, no `administrator`) GET `/langgraph-console/release` → 403.
- [ ] `[TEST-ONLY]` Anonymous GET → 403.
- [ ] `[TEST-ONLY]` PHP error does not occur if any single state file is missing; graceful degradation to "No active release" row.

## Security

- [ ] `[TEST-ONLY]` Route requires `administrator` role; verify role-based access check is enforced at routing level (not just controller logic).
- [ ] `[TEST-ONLY]` No filesystem paths are exposed in the rendered HTML output.
- [ ] `[TEST-ONLY]` No watchdog entries on a normal admin page render.
- Agent: qa-forseti
- Status: pending
