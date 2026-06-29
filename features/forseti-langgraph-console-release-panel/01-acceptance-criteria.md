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
