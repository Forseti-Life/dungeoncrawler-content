# Feature Brief

- Work item id: forseti-langgraph-console-release-panel
- Website: forseti.life
- Module: copilot_agent_tracker
- Status: ready
- Release: 20260411-forseti-release-b
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti-agent-tracker
- QA owner: qa-forseti
- Priority: high
- Source: PM grooming 2026-04-11 (LangGraph UI roadmap release-h: Release Control Panel)
- Roadmap reference: features/forseti-langgraph-ui/roadmap.md

## Summary

The LangGraph Console has a `/langgraph-console/release` section that currently serves static stubs. The roadmap (release-h) calls for wiring this section to real release management data: current active release IDs, PM signoff status (per site), feature count in scope, time elapsed since release start, and links to relevant artifacts. This is read-only observability — no mutations to release state from this panel. The existing `tmp/release-cycle-active/` state files and `sessions/pm-*/artifacts/release-signoffs/` are the data sources. Access is admin-only (`administrator` Drupal role).

## Goal

- The CEO/Board can view current release state for all sites directly in the Drupal admin UI.
- Release panel shows: active release ID per site, PM signoff status (SIGNED / PENDING), feature count in scope, hours elapsed since release start.
- All data is read from filesystem state files (same data the `release-signoff-status.sh` script reads). No DB writes.

## Acceptance criteria

- AC-1: Route `/langgraph-console/release` returns 200 for users with `administrator` Drupal role. Authenticated non-admin → 403. Anonymous → 403.
- AC-2: Panel shows one row per site (forseti, dungeoncrawler) with: `release_id`, `pm_signoff_status` (SIGNED/PENDING), `feature_count` (count of `in_progress` features for that site), `hours_elapsed` (time since `tmp/release-cycle-active/<site>.started_at`).
- AC-3: Data is read live on each page request from `tmp/release-cycle-active/` state files and `sessions/pm-*/artifacts/release-signoffs/`. No caching that would stale more than 60 seconds.
- AC-4: If a state file is missing (no active release for a site), that site row shows "No active release" — not a PHP error or blank row.
- AC-5: `COPILOT_HQ_ROOT` PHP constant (already defined in `DashboardController`) is used to resolve all filesystem paths. No hardcoded server paths.
- AC-6: Page title is "Release Control Panel" and is linked from the LangGraph console navigation.

## Non-goals

- Triggering or mutating release state from this panel (no push buttons, no signoff triggers).
- Historical release data / release history table.
- Email notifications or alerts from this panel.

## Security acceptance criteria

- Authentication/permission surface: Route requires `_role: 'administrator'`. No other roles have access. Anonymous → 403.
- CSRF expectations: Read-only GET route — no CSRF token required.
- Input validation requirements: No user input. All data is read from filesystem state files controlled by the server.
- PII/logging constraints: No user-specific data is displayed (release state is org-level). Nothing written to watchdog on normal render.

## Implementation notes

- Follow the `LangGraphConsoleStubController` pattern for the route definition.
- Use `DashboardController::langgraphPath()` helper for `COPILOT_HQ_ROOT`-aware path resolution.
- Feature count: `glob(COPILOT_HQ_ROOT . '/features/*/feature.md')` + `grep` for `Status: in_progress` + `Website: <site>`.
- Hours elapsed: `time() - filemtime(COPILOT_HQ_ROOT . '/tmp/release-cycle-active/<site>.started_at')` / 3600.
- PM signoff check: `file_exists(COPILOT_HQ_ROOT . '/sessions/pm-<site>/artifacts/release-signoffs/<release_id>.md')`.
