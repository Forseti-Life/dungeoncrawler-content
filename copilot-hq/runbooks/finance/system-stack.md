# Finance System Stack

This file defines the minimal systems Forseti accounting should maintain.

## Required layers

| Layer | Purpose | Acceptable starting point |
| --- | --- | --- |
| Book of record | Monthly summaries, ledgers, reconciliations, and anomaly tracking | Markdown artifacts under `dashboards/finance/` |
| Expense sources | Vendor invoices and usage exports | AWS, GitHub, and other vendor billing portals |
| Income sources | Revenue and payout evidence | Payment processor exports, sponsorship records, invoice register |
| Cash evidence | Confirmation that payouts or payments actually landed | Bank statements, payout receipts, deposit reports |
| Renewal tracking | Visibility into upcoming recurring commitments | `dashboards/finance/renewal-calendar.md` |

## Best-practice evolution path
1. **Documented markdown workflow** - acceptable while volume is low and data sources are few.
2. **Structured spreadsheet or lightweight ledger** - recommended once monthly activity or categories become hard to track manually.
3. **Dedicated accounting system** - recommended once Forseti has multiple revenue streams, tax handling, meaningful receivables/payables, or frequent reconciliation pain.

## Triggers for system upgrades
- More than one active revenue source
- Repeated manual reclassification between months
- Missing audit trail for deposits, payouts, or refunds
- Renewal surprises caused by poor visibility
- CEO cannot answer "what did we earn, spend, and owe last month?" from existing artifacts

## Recommendation policy
When one of the upgrade triggers is met, the accountant should recommend the smallest system addition that removes the risk:
- better exports,
- a receivables register,
- a renewal tracker,
- a structured ledger,
- or a dedicated accounting platform.
