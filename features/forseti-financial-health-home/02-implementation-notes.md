# Implementation Notes — forseti-financial-health-home

- Dev seat: `dev-forseti`
- Date: `2026-04-13`
- Status: `in_progress`
- KB reference: none found

## Impact Analysis

This feature introduces a **new internal Drupal route** and a new institutional reporting surface. That makes it a major functionality change under dev-forseti process rules.

### Existing flows affected
- `institutional_management` gains a new finance-oriented internal page alongside its existing institutional dashboard routes.
- No public route behavior changes are required for MVP.
- No schema changes are required for MVP because the page reads from the accountant book of record in `copilot-hq`.

### Module/ownership impact
- The feature brief proposes `institutional_finance (new)`.
- Current ownership maps show that new custom modules under `/sites/forseti/web/modules/custom/` default to `dev-forseti`, but the existing `institutional_management` module already owns institutional dashboards and permissions.
- Smallest safe diff for MVP: implement the feature inside `institutional_management` rather than creating a separate module immediately.

### Risks
- The page could drift from accounting truth if it becomes manually editable in Drupal.
- The page could mislead leadership if missing-source placeholders are shown as actuals.
- Route access must remain internal-only.

## Implementation approach

### MVP host module
- `sites/forseti/web/modules/custom/institutional_management/`

### Route
- Add route: `/internal/financial-health`
- Initial permission: `view institution reports`

### Data source strategy
- Read from `COPILOT_HQ_ROOT` (fallback `/home/ubuntu/forseti.life/copilot-hq`)
- Primary file for MVP:
  - `dashboards/finance/current-dashboard-2026-04.md`
- Render source-of-truth values from the accountant dashboard rather than duplicating finance logic in Drupal

### MVP render sections
1. Header metadata
2. Executive health cards
3. Current financial view table
4. Source coverage table
5. Active blockers list
6. Book-of-record artifact paths

## Files expected to change

- `sites/forseti/web/modules/custom/institutional_management/institutional_management.routing.yml`
- `sites/forseti/web/modules/custom/institutional_management/src/Controller/InstitutionalController.php`
- Possibly `institutional_management.links.menu.yml` in a later pass if PM wants navigation exposure

## Verification plan

- `php -l` on changed PHP files
- `./vendor/bin/drush cr`
- `curl -I https://forseti.life/internal/financial-health` while authenticated through QA or browser session

## Rollback plan

- Revert the route/controller diff if the page misrenders or exposes incorrect finance state
- Because MVP is read-only and schema-free, rollback is code-only
