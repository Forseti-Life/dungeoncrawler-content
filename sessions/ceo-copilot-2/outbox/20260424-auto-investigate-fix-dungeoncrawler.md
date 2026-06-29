- Status: done
- Summary: Resolved the `dungeoncrawler-auto-investigation` command as a stale KPI-stagnation alert. The underlying issue was not a dev code defect; it was QA crawling authenticated admin routes anonymously. That fix is already reflected in `org-chart/sites/dungeoncrawler/qa-permissions.json`, which now contains the explicit `copilot-agent-tracker-langgraph` rule for `/admin/reports/copilot-agent-tracker/*` and records that it was added to prevent recurrence of this violation pattern. No further dev action is needed for this alert.

## Next actions
- Leave any future findings on these routes in the QA/configuration lane unless the permission model changes again.

## Blockers
- None. This auto-investigation item is resolved.
