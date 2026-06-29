# Daily P&L

- Date: `2026-04-13`
- Month: `2026-04`
- Currency: `USD`
- Status: `blocked`
- Owner: `accountant-forseti`
- Primary developer: `accountant-forseti`
- Prepared by: `accountant-forseti`
- Extraction date(s): `2026-04-13`

## Daily flash summary
The April finance workspace is open and expense sources are now selected: AWS Billing and GitHub billing. The GitHub org usage pull is now working and returned no April usage items for `Forseti-Life`, but AWS Cost Explorer remains blocked and Forseti's income and cash sources still have not been confirmed. Until those issues are cleared, the month-to-date totals below are placeholders only and should not be treated as actual zero activity.

## Today

| Metric | Amount | Notes |
| --- | ---: | --- |
| Income today | 0.00 | Placeholder only; live income source not yet confirmed. |
| Expenses today | 0.00 | GitHub usage-based expense is source-backed at 0.00; AWS expense still blocked. |
| Net today | 0.00 | Not authoritative while source hookup is unresolved. |

## Month to date

| Metric | Amount | Notes |
| --- | ---: | --- |
| Income MTD | 0.00 | Placeholder only; do not read as actual zero. |
| Expenses MTD | 0.00 | Includes source-backed GitHub usage expense of 0.00; excludes blocked AWS expense. |
| Net MTD | 0.00 | Placeholder only; blocked pending live source hookup. |

## Cash and timing notes
- Cash status is unknown until the authoritative bank, payout, or deposit evidence source is confirmed.

## Open issues affecting confidence
- Authoritative income source not yet identified in HQ artifacts.
- AWS Billing is confirmed as the expense source, but the current IAM user lacks `ce:GetCostAndUsage`.
- GitHub org billing usage is accessible and returned no April usage items, but any fixed subscription or seat billing still needs confirmation.
- Authoritative cash evidence source not yet identified for payout and payment reconciliation.
- See `dashboards/finance/anomaly-log.md` and `runbooks/finance/expense-pulls.md` for the current expense pull blockers.

## Source-backed expense signals now available
- GitHub org usage expense for April 2026: `0.00` as of 2026-04-13.
- AWS expense remains unavailable pending Cost Explorer access or invoice/export evidence.

## Recommendation
- Action: Grant AWS Cost Explorer access and confirm both the income/cash sources and whether GitHub has any fixed subscription billing outside the org usage report.
- Why: GitHub usage-based expense is now observable, but the daily P&L still cannot become authoritative while AWS expense and the remaining source questions are unresolved.
- ROI: 21
