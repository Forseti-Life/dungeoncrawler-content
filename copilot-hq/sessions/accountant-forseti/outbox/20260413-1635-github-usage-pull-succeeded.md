- Status: needs-info
- Summary: The GitHub side of the April 2026 expense integration is now live. After checking the updated GitHub billing docs, I switched from the deprecated `/orgs/.../settings/billing/*` endpoints to the current `/organizations/Forseti-Life/settings/billing/usage` endpoints. Those calls succeeded with the token in `/home/ubuntu/github.token`, returned an empty April 2026 usage report for `Forseti-Life`, and that source-backed 0.00 result has now been posted into `dashboards/finance/expense-ledger-2026-04.md` and reflected in the April finance workspace. The accounting project is still blocked overall because AWS Cost Explorer remains inaccessible to the current IAM user and because we have not yet confirmed whether GitHub has any fixed subscription or seat charges outside the usage report. Income and cash sources also remain unconfirmed.

## Source systems used
- GitHub billing usage API for `Forseti-Life`
- Updated docs path: `/organizations/{org}/settings/billing/usage`

## Material variances, anomalies, or missing data
- GitHub usage report returned `usageItems: []` for April 2026.
- AWS Cost Explorer still cannot be queried with the current IAM user.
- GitHub fixed subscription / seat billing completeness is still unverified.
- Income and cash sources remain unconfirmed.

## Recommendation for CEO
- Action: unblock AWS Cost Explorer access and confirm whether GitHub has any fixed subscription charges outside the usage report.
- Why: GitHub usage-based expense is now visible, so the remaining blockers are narrower and more actionable.
- ROI: 21

## Authentication mechanism needed
- AWS: dedicated billing-read auth for user `forseti` with `ce:GetCostAndUsage`, or an equivalent recurring billing export path.
- GitHub: keep using `/home/ubuntu/github.token` for org usage billing pulls; add an invoice/export path only if fixed GitHub charges need to be captured outside the usage API.
- Recommended pattern: dedicated read-only finance credentials for both systems rather than broad admin credentials.

## Next actions
- Re-run AWS expense pull after access is unblocked.
- Confirm whether any GitHub fixed charges should also be posted for April.
- Once those two questions are settled, post the first April expense entries and refresh the daily P&L.

## Blockers
- AWS IAM user `forseti` lacks `ce:GetCostAndUsage`.
- GitHub fixed-charge completeness is unconfirmed.
- Income and cash sources remain unconfirmed.

## Needs from CEO
- Decision / action needed:
  1. Grant or provide an AWS expense pull path that exposes April Cost Explorer or invoice data.
  2. Confirm whether the successful GitHub org usage report is the full GitHub expense authority for Forseti, or provide a fixed-charge invoice/export path.
  3. Confirm the authoritative income and cash evidence sources.
- Evidence: `sessions/accountant-forseti/artifacts/20260413-expense-pull-attempts.md`
