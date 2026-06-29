# Finance Source Map

Use this file to define the authoritative system for each finance signal before publishing numbers.

## Source-of-truth table

| Area | Primary source | Secondary check | Minimum cadence | Notes |
| --- | --- | --- | --- | --- |
| AWS infrastructure expense | AWS Billing / Cost Explorer / invoice export | Prior month summary or vendor reconciliation | Monthly | Use invoice totals for closed months; use Cost Explorer for intra-month estimates |
| GitHub expense | GitHub billing and usage views / invoice export | Prior month summary or vendor reconciliation | Monthly | Separate seat/licensing costs from variable Actions or package charges |
| Other vendor expense | Vendor invoice, receipt, or billing portal export | Bank statement or payment confirmation | Monthly | Domains, workspace/email, SaaS tools, contractors, and similar vendors belong here |
| Processor-based income | Payment processor export or payout report | Bank receipt | Monthly | Keep gross sales, processor fees, refunds, and net payout distinct when possible |
| Sponsorship/donation income | Sponsorship platform export or statement | Bank receipt or payout confirmation | Monthly | Record platform fees separately if visible |
| Invoice-based income | Invoice register or accounting export | Payment receipt or bank deposit | Monthly | Track open receivables separately from collected cash |
| Bank cash movement | Bank statement or transaction export | Ledger totals | Monthly | Required when reconciling timing differences between billed, earned, and collected |

## Rules
- A closed-period total should come from a closed-period system artifact, not a transient dashboard view when an invoice/export exists.
- If only a dashboard screenshot or transient view exists, label the number as provisional.
- If a new finance system appears, add it here before treating it as authoritative.

## Current live source decisions (2026-04-13)
- **AWS infrastructure expense:** confirmed. Use AWS Billing / Cost Explorer as the live source for open-month expense. Current blocker: IAM user `forseti` was denied `ce:GetCostAndUsage` on 2026-04-13.
- **GitHub expense:** confirmed. Use GitHub billing usage for `Forseti-Life` as the live source. The current org usage endpoints are accessible and returned `usageItems: []` for April 2026. Remaining question: whether any fixed subscription or seat charges exist outside that usage report and require an invoice/export path.
- **Income source:** not yet confirmed.
- **Bank / cash evidence source:** not yet confirmed.

## Authentication requirements (current)
- **AWS:** one billing-read credential for Cost Explorer or one repeatable export/invoice path. The active IAM user is `forseti`; it currently lacks `ce:GetCostAndUsage`.
- **GitHub usage-based billing:** the token in `/home/ubuntu/github.token` is currently sufficient for the org usage endpoints under `/organizations/Forseti-Life/settings/billing/usage`.
- **GitHub fixed-charge confirmation:** if fixed seat/subscription charges exist outside the usage report, an invoice/export path or equivalent billing view is still required.
- **Design rule:** prefer dedicated finance-read credentials over broad admin credentials for recurring accountant workflows.

## Recommended systems to add if missing
1. A reliable export path for every income source.
2. A bank statement archive or equivalent payout confirmation trail.
3. An invoice and receivables register if Forseti bills anyone directly.
4. A renewal tracker for recurring commitments and annual services.
5. A monthly summary artifact in `dashboards/finance/` for each closed month.
