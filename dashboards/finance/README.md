# Finance Dashboard Artifacts

This directory is the book-of-record workspace for Forseti finance reporting.

- Owner: `accountant-forseti`
- Primary developer: `accountant-forseti`

## Goals
- Keep monthly finance outputs repeatable instead of ad hoc
- Make every reported number traceable to a named source system
- Separate income, expenses, cash timing, and estimates clearly

## Core artifacts
- `current-dashboard-YYYY-MM.md` - single current-status view for the active month
- `daily-p-and-l-YYYY-MM.md` - daily flash P&L for the active month
- `monthly-summary-YYYY-MM.md` - CEO-facing summary for a closed month
- `expense-ledger-YYYY-MM.md` - expense detail grouped by category and vendor
- `income-ledger-YYYY-MM.md` - income detail grouped by revenue stream and source
- `vendor-reconciliation-YYYY-MM.md` - source-to-source reconciliation notes
- `renewal-calendar.md` - recurring commitments, due dates, owners, and expected amounts
- `anomaly-log.md` - unresolved or notable finance exceptions

## Templates and starter files
- `daily-p-and-l-template.md`
- `monthly-summary-template.md`
- `expense-ledger-template.md`
- `income-ledger-template.md`
- `vendor-reconciliation-template.md`
- `renewal-calendar.md`
- `anomaly-log.md`

## Minimum monthly summary contents
- Period and currency
- Actual income
- Actual expenses
- Net cash movement
- Largest drivers of change
- Open questions or missing data
- Recommendation for the CEO

## Standard categories
- **Income:** subscriptions, services, sponsorships/donations, other operating income
- **Infrastructure expense:** compute, storage, bandwidth, domains/DNS, email/workspace, observability, CI/CD
- **Operating expense:** contractors, software subscriptions, payment fees, taxes/filing, professional services, other G&A

If a recurring line item does not fit these cleanly, add a new category deliberately and use it consistently across periods.

## Working style
- Start with `current-dashboard-YYYY-MM.md` when you need the fastest high-level view of accounting status for the active month.
- Keep one active `daily-p-and-l-YYYY-MM.md` for the current month and update it on each daily reconciliation pass.
- Start new month-end artifacts from the templates.
- Keep closed-month files immutable except for documented corrections.
- Use `anomaly-log.md` for unresolved issues instead of burying them in monthly summaries.
