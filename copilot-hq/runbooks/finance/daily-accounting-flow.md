# Daily Accounting Flow

Use this process to keep Forseti's income, expenses, and daily P&L current without waiting for month-end close.

## Objective
Produce a **daily flash P&L** that is directionally reliable, source-linked, and easy to reconcile into the monthly close.

## Principles
- Daily reporting is a **flash view**, not a substitute for closed-month actuals.
- Every daily number should tie back to a source system, even if the treatment is provisional.
- Income, expenses, fees, refunds, credits, and bank movement must remain visibly separate.
- Unresolved items belong in `dashboards/finance/anomaly-log.md`, not hidden in totals.

## Daily process flow

### 1. Gather new source activity
Check all systems that could have changed since the prior daily run:
- payment processors or revenue systems
- AWS and GitHub usage/billing surfaces
- other vendor invoices, emails, or billing portals
- bank deposits, payouts, or outgoing payments
- new invoices issued or collected

### 2. Post new income activity
Record new revenue activity into the working income ledger for the current month:
- gross income
- processor fees
- refunds / credits
- net payout or cash status

If the revenue is earned today but cash will settle later, record it as income with a pending cash note.

### 3. Post new expense activity
Record new expense activity into the working expense ledger for the current month:
- vendor
- category
- amount
- whether it is recurring
- payment status
- renewal / due date when relevant

If a bill exists but has not been paid, record the obligation as unpaid or pending rather than pretending the expense does not exist.

### 4. Reconcile cash movement
Compare known payouts, deposits, and payments against bank or cash evidence when available:
- payout issued vs payout received
- invoice collected vs deposit received
- bill paid vs bank confirmation

If cash evidence lags, keep the item visible as a timing issue.

### 5. Update the daily flash P&L
Refresh `dashboards/finance/daily-p-and-l-YYYY-MM.md` using the current month's working totals:
- day income
- day expenses
- day net
- month-to-date income
- month-to-date expenses
- month-to-date net

The daily P&L should state whether the day is **complete**, **provisional**, or **blocked by missing sources**.

### 6. Log exceptions immediately
If any number is incomplete, inconsistent, or estimated:
- add an item to `dashboards/finance/anomaly-log.md`
- label the issue type
- state the next step and owner

### 7. Report the decision signal
In the daily summary section, answer:
- what changed today,
- whether today changed the month-to-date trend,
- whether the CEO needs to act now.

## Daily outputs
- current-month income ledger updates
- current-month expense ledger updates
- updated `daily-p-and-l-YYYY-MM.md`
- anomaly log entries when needed
- renewal-calendar updates if a new recurring expense appears

## End-of-day status values
- `complete` - all expected sources reviewed and reflected
- `provisional` - some sources are pending but the daily view is directionally usable
- `blocked` - a material source is missing and the daily view cannot be trusted

## Relationship to month-end close
- The daily flash P&L feeds the monthly close but does not replace it.
- Month-end may reclassify timing items, replace provisional values with invoice/export actuals, and document corrections.
- Any difference between the final month-end numbers and the daily flash trail should be explainable from anomalies, timing, or classification cleanup.
