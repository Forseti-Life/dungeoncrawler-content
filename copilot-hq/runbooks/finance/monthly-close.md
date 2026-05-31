# Monthly Finance Close

Use this process to close a month for Forseti without mixing estimates, cash timing, and actuals.

## Close objective
Produce a period summary that leadership can trust without re-running the reconciliation manually.

## Workflow
1. Freeze the period being closed and note the currency.
2. Gather source exports from every in-scope expense and income system.
3. Record income by stream and identify refunds, fees, and unpaid invoices separately.
4. Record expenses by category and vendor, noting annual or one-time charges separately.
5. Reconcile processor payouts and invoice collections to bank activity when cash visibility matters.
6. Compare the month to the prior period and explain material variances.
7. Update `dashboards/finance/monthly-summary-YYYY-MM.md` and any supporting ledger or reconciliation artifacts.
8. Log unresolved issues in `dashboards/finance/anomaly-log.md`.
9. Add renewal or payment timing risks to `dashboards/finance/renewal-calendar.md` when applicable.

## Minimum controls
- Do not overwrite a closed-month actual without noting why it changed.
- Do not net refunds or fees into gross income unless the source only provides net payouts.
- Do not merge estimated current-month values into a closed-month actual summary.
- Do not publish a total that cannot name its source and extraction date.

## Required outputs
- Monthly summary
- Income and/or expense ledger if the month has more than trivial activity
- Reconciliation note when totals differ across systems
- Anomaly log entry for anything still unresolved
