- Status: needs-info
- Summary: Expense source decisions are now in place for April 2026: AWS Billing and GitHub billing are the authoritative expense sources for the accounting project. I moved forward with live integration pulls against both sources. The AWS attempt used the active IAM credentials successfully enough to identify the caller as `arn:aws:iam::647731524551:user/forseti`, but the pull was blocked by missing `ce:GetCostAndUsage`. The GitHub attempt authenticated as `keithaumiller` using `/home/ubuntu/github.token`, but the available token does not currently have the org billing access needed for `Forseti-Life`, and the tested org billing endpoints returned `410` moved responses. April expense reporting therefore remains blocked by access, not by source ambiguity.

## Source systems used
- AWS Billing / Cost Explorer
- GitHub billing for `Forseti-Life`

## Assumptions
- Open-month AWS expense should come from Cost Explorer until a closed-month invoice/export is available.
- GitHub org billing for `Forseti-Life` is the correct expense authority once access is unblocked.

## Material variances, anomalies, or missing data
- No live April AWS amount could be posted because the IAM user lacks `ce:GetCostAndUsage`.
- No live April GitHub amount could be posted because the available token lacks the org billing access needed for `Forseti-Life`.
- Income and cash sources remain unconfirmed.

## Recommendation for CEO
- Action: grant AWS Cost Explorer access, provide GitHub org billing access for `Forseti-Life`, and confirm the income/cash sources.
- Why: the accountant can now use the chosen expense sources, but actual ledger population still depends on access.
- ROI: 21

## Next actions
- Re-run the AWS and GitHub expense pulls immediately after access is unblocked.
- Post the first April expense entries into `dashboards/finance/expense-ledger-2026-04.md`.
- Update `daily-p-and-l-2026-04.md` and `vendor-reconciliation-2026-04.md` with pulled amounts.

## Blockers
- AWS IAM user `forseti` lacks `ce:GetCostAndUsage`.
- GitHub token lacks the org billing access needed for `Forseti-Life`.
- Income and cash sources remain unconfirmed.

## Needs from CEO
- Decision / action needed:
  1. Grant or provide an AWS expense pull path that exposes April Cost Explorer or invoice data.
  2. Provide GitHub org billing access for `Forseti-Life` or an equivalent export path.
  3. Confirm the authoritative income and cash evidence sources.
- Evidence: `sessions/accountant-forseti/artifacts/20260413-expense-pull-attempts.md`
