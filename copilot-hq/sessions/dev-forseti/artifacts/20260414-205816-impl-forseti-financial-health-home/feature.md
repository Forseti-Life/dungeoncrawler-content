# Feature: forseti-financial-health-home

- Status: in_progress
- Website: forseti.life
- Module: institutional_management (MVP host)
- Release: 20260412-forseti-release-l
- Owner: pm-forseti
- Project: PROJ-008
- Source: accountant-forseti proposal 2026-04-13
- Roadmap: features/forseti-financial-health-home/roadmap.md
- Architecture: features/forseti-financial-health-home/03-next-phase-architecture.md

## Summary

Forseti now has an accountant-owned finance workspace in `copilot-hq/dashboards/finance/`, including a current-month dashboard, daily P&L, ledgers, reconciliation notes, and anomaly tracking. That operating model exists only in HQ markdown artifacts, which makes it effective for internal accounting work but not yet visible as a first-class institutional-health surface inside Drupal. This feature adds an internal-only Drupal **Financial Health** home that renders the institution's current financial status for leadership and authorized internal users. The page should present executive summary cards, source coverage, active blockers, current-month roll-up, and links to the underlying accounting artifacts without creating a second manual bookkeeping surface.

## Goal

Give Forseti a dedicated in-product institutional finance home where leadership can quickly see current financial health, confidence level, source coverage, and the exact blockers preventing full accounting visibility.

## Acceptance criteria

- AC-1: New internal route exists at `/internal/financial-health` (or equivalent PM-approved internal route) and is accessible only to authorized authenticated users. Anonymous users must not see the page.
- AC-2: The page displays an executive health band with cards for Income MTD, Expense MTD, Net MTD, Cash status, and Overall confidence status. Each card shows both the current value and whether it is `source-backed`, `provisional`, or `blocked`.
- AC-3: The page displays a source-coverage table with at least: GitHub usage billing, GitHub fixed charges, AWS billing, income source, and cash evidence source. Each row shows current status, last refresh timestamp, and blocker summary when relevant.
- AC-4: The page displays an active blockers section near the top when any material source is missing. The current MVP blocker set must support: AWS Cost Explorer permission gap, unconfirmed GitHub fixed charges, unconfirmed income source, and unconfirmed cash evidence source.
- AC-5: The page displays a current-month financial roll-up section with income subtotal, expense subtotal, net subtotal, source-backed expense subtotal, blocked/missing expense subtotal, and blocked/missing income subtotal.
- AC-6: The page links to or renders access paths for the accountant book-of-record artifacts: `current-dashboard-YYYY-MM.md`, `daily-p-and-l-YYYY-MM.md`, `income-ledger-YYYY-MM.md`, `expense-ledger-YYYY-MM.md`, `vendor-reconciliation-YYYY-MM.md`, and `anomaly-log.md`.
- AC-7: Missing-source placeholder values must never be shown as authoritative actuals. If a material input is missing, the page must show `blocked` or `provisional` instead of implying certainty.
- AC-8: The page visibly shows reporting month, last refresh timestamp, and accountant owner so leadership can judge recency and accountability at a glance.

## Definition of done

- Feature is present in the Forseti Drupal experience and is accessible to the intended internal audience.
- The page reflects the accountant source-of-truth model instead of requiring manual page editing in Drupal.
- QA can verify blocked/provisional/complete states render correctly.
- Leadership can identify the current month's financial status and blockers from one page without opening raw finance markdown first.

## Notes

- Default access model: internal-only, not public navigation.
- Suggested data source for MVP: normalized summary derived from `dashboards/finance/current-dashboard-YYYY-MM.md`.
- MVP host decision: ship inside `institutional_management` to avoid unnecessary new-module overhead while the feature remains read-only and internal.
- Current real-world MVP content would show GitHub usage expense as source-backed `0.00`, AWS expense as blocked, and income/cash as blocked or unconfirmed.
- Future versions may move from markdown-derived rendering to a structured JSON/API feed if the finance system matures beyond markdown as the book of record.
- Related accountant artifact: `sessions/accountant-forseti/artifacts/20260413-accounting-home-proposal.md`
