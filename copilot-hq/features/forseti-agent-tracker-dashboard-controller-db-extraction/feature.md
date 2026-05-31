# Feature: forseti-agent-tracker-dashboard-controller-db-extraction

- Feature ID: forseti-agent-tracker-dashboard-controller-db-extraction
- Website: forseti.life
- Module: copilot_agent_tracker
- Project: PROJ-001
- Group Order: 2
- Group: agent-tracker
- Group Title: Agent Tracker
- Group Sort: 1
- Status: shipped
- Priority: P2
- Release: 20260408-forseti-release-j

## Summary
Extract the 20 direct `$this->database` calls from `DashboardController.php` (4789 lines) into a dedicated `DashboardRepository` or existing tracker service. This is the highest-impact DB-layer refactor remaining in the codebase by call count.

## Problem
`DashboardController.php` contains 20 direct `$this->database` calls scattered throughout a 4789-line controller. Controllers should be thin — DB logic belongs in a repository/service layer.

## Acceptance criteria
- AC-1: `grep -c '->database(' sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php` → 0
- AC-2: New `DashboardRepository.php` (or appropriate service) contains all extracted DB logic
- AC-3: Controller injects the new repository/service via DI (constructor injection)
- AC-4: `php -l` passes on all modified files
- AC-5: Site audit 0 failures, 0 violations post-implementation
- AC-6: Dashboard pages render without error (smoke test `/admin/copilot-agent-tracker`)

## Security acceptance criteria
- Authentication/permission surface: Dashboard routes require `administer site configuration` or equivalent admin permission. No permission change.
- CSRF expectations: Dashboard is read-only GET — no CSRF needed. No new POST routes.
- Input validation requirements: Query parameters filtered through existing controller logic — extraction must not bypass existing input sanitization.
- PII/logging constraints: Agent session/outbox data may contain sensitive content. Repository must not add additional logging of data contents.

## Rollback
- `git revert` to previous commit of `DashboardController.php` and remove new repository file.

## Verification method
- Static: `grep -c '->database(' ...DashboardController.php` → 0
- `php -l` on modified files
- Smoke test: HTTP 200 on `/admin/copilot-agent-tracker`
- Site audit clean
