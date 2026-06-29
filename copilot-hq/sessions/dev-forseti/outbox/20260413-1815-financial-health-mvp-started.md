# dev-forseti outbox — financial health MVP started

- Date: `2026-04-13`
- Feature: `forseti-financial-health-home`
- Status: `in_progress`
- Module host: `institutional_management`

## Summary

Started the Drupal MVP for the internal Financial Health page. The implementation adds `/internal/financial-health` inside `institutional_management` and renders the accountant-owned finance dashboard from `copilot-hq/dashboards/finance/current-dashboard-2026-04.md`.

## What changed

- Added implementation notes at `features/forseti-financial-health-home/02-implementation-notes.md`
- Added the new internal route in `institutional_management.routing.yml`
- Added controller logic to render:
  - executive finance cards
  - current financial view
  - current-month roll-up
  - source coverage with last-refresh timestamp
  - active blockers
  - book-of-record artifact paths
- Updated backlog docs so the MVP host is `institutional_management` instead of a new module

## Remaining risks

- AWS billing remains blocked by missing `ce:GetCostAndUsage`
- Income and cash evidence sources are still unconfirmed
- GitHub fixed-charge completeness is still unconfirmed

## Next action

Clear Drupal caches, confirm the route renders correctly, and hand the page to QA once the internal permission model is verified.
