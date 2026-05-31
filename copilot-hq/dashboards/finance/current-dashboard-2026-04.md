# Forseti Finance Dashboard — 2026-04

- Owner: `accountant-forseti`
- Primary developer: `accountant-forseti`
- Status: `blocked`
- Last updated: `2026-04-13`

## Executive view

This is the single current-status dashboard for April 2026 accounting. It rolls up the active daily P&L, income ledger, expense ledger, and reconciliation state into one CEO-facing view.

## Current financial view

| Area | Current value | Confidence | Source / note |
| --- | ---: | --- | --- |
| Income MTD | 0.00 | blocked | No authoritative income source has been confirmed yet. |
| Expense MTD | 0.00 | partial | Includes source-backed GitHub usage expense of `0.00`; excludes blocked AWS expense. |
| Net MTD | 0.00 | blocked | Not authoritative until income, AWS expense, and cash sources are resolved. |
| GitHub usage expense | 0.00 | source-backed | `/organizations/Forseti-Life/settings/billing/usage?year=2026&month=4` returned `usageItems: []`. |
| AWS expense | 0.00 | blocked | AWS Cost Explorer pull failed because IAM user `forseti` lacks `ce:GetCostAndUsage`. |
| Cash position | 0.00 | blocked | No authoritative cash evidence source has been confirmed yet. |

## Source coverage

| Signal | Source status | Detail |
| --- | --- | --- |
| GitHub usage billing | live | Current org usage endpoints work and show no April usage items. |
| GitHub fixed charges | provisional | Need invoice/export or equivalent billing view if fixed seat/subscription charges exist. |
| AWS billing | blocked | Billing source chosen, but access not yet sufficient for Cost Explorer pull. |
| Income | blocked | No payment processor, invoice register, or other income source confirmed yet. |
| Cash evidence | blocked | No bank statement, payout report, or deposit source confirmed yet. |

## Active blockers

1. AWS IAM user `forseti` needs `ce:GetCostAndUsage` or an equivalent invoice/export path.
2. GitHub fixed-charge completeness is still unconfirmed.
3. Income source is still unconfirmed.
4. Cash evidence source is still unconfirmed.

## Working artifacts

- Daily P&L: `dashboards/finance/daily-p-and-l-2026-04.md`
- Income ledger: `dashboards/finance/income-ledger-2026-04.md`
- Expense ledger: `dashboards/finance/expense-ledger-2026-04.md`
- Vendor reconciliation: `dashboards/finance/vendor-reconciliation-2026-04.md`
- Anomaly log: `dashboards/finance/anomaly-log.md`
- Pull/auth runbook: `runbooks/finance/expense-pulls.md`

## Recommendation

- Action: treat this dashboard as the current accounting control panel, then unblock AWS and confirm income/cash sources so it can move from blocked to authoritative.
- Why: the accounting workspace exists, but this file now makes the real current state visible in one place.
