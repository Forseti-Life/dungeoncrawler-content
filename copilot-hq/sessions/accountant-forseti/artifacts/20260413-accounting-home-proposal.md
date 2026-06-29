# Proposal — Forseti Accounting Home

- Owner: `accountant-forseti`
- Primary developer: `accountant-forseti`
- Product: `forseti.life`
- Status: `proposed`

## Purpose

Create a Drupal-based accounting home on `forseti.life` that represents the financial health of the institution in one place for leadership and authorized internal users.

## Problem statement

The accounting system now has working artifacts in `copilot-hq/dashboards/finance/`, but the financial-health view lives only in HQ markdown files. That is useful for accounting operations, but it is not yet surfaced as a first-class product home inside Drupal where leadership can quickly see institutional status, source coverage, blockers, and trend direction.

## Goal

Provide a dedicated Forseti accounting home that answers:

1. What is our current financial health?
2. What are current month income, expenses, and net position?
3. Which numbers are source-backed versus blocked or provisional?
4. What is leadership waiting on right now?

## Proposed page model

### Page title
- `Financial Health`

### Primary audience
- CEO / leadership
- Accountant / finance operator
- Possibly other authorized staff if permissions are added

### Access model
- Default recommendation: authenticated internal-only page
- Do not expose raw financial detail publicly by default
- If a public transparency page is later desired, create a separate reduced-scope public view

### Suggested route
- Internal page route suggestion: `/internal/financial-health`
- Menu placement suggestion: internal operations / leadership navigation, not public main nav

## Information architecture

### Page order
1. Page header and last-refresh status
2. Executive health band
3. Source coverage
4. Active blockers
5. Current month financial detail
6. Underlying artifact links

### Header metadata
The header should visibly show:
- reporting month
- last refresh timestamp
- overall confidence status
- accountant owner

## MVP sections

### 1. Executive health band
Top summary cards:
- Income MTD
- Expense MTD
- Net MTD
- Cash status
- Overall confidence status (`complete`, `provisional`, `blocked`)

#### Card behavior
- Each card should show the current value, status, and one-line explanation.
- If a card is blocked, the reason should be visible without clicking deeper.
- Values should never imply certainty when the source is still missing.

### 2. Source coverage
Simple status table:
- GitHub usage billing
- GitHub fixed charges
- AWS billing
- Income source
- Cash evidence source

Each line should show:
- current status
- last refresh date
- blocker summary when relevant

#### Status vocabulary
- `live` - source connected and reviewed
- `source-backed` - current value is grounded in a named source record
- `provisional` - partially known but not fully complete
- `blocked` - source or access gap prevents trustable reporting

### 3. Active blockers
Leadership-focused list of what is preventing authoritative accounting:
- AWS Cost Explorer permission gap
- unconfirmed GitHub fixed charges
- unconfirmed income source
- unconfirmed cash evidence source

### 4. Current month details
Roll-up table for:
- current month income
- current month expenses
- current month net
- source-backed vs missing components

#### Detail table design
Recommended rows:
- income subtotal
- expense subtotal
- net subtotal
- source-backed expense subtotal
- blocked / missing expense subtotal
- blocked / missing income subtotal

### 5. Artifact links
Links to the underlying accounting records:
- daily P&L
- income ledger
- expense ledger
- vendor reconciliation
- anomaly log

## Metric semantics

### Income MTD
- Sum of source-backed current-month income entries
- Must not show placeholder zero as actual zero

### Expense MTD
- Sum of source-backed current-month expense entries
- Should explicitly note excluded blocked sources

### Net MTD
- `Income MTD - Expense MTD`
- Confidence cannot exceed the weaker of the two inputs

### Cash status
- Based on confirmed bank / payout / deposit evidence
- If cash evidence source is missing, show blocked rather than guessed cash

### Overall confidence
- `complete` only if income, expense, and cash sources are all connected and reviewed
- `provisional` if some values are directionally useful but not fully reconciled
- `blocked` if a material source is missing

## Data source model

The Drupal home should be fed from the accountant book of record, not hand-maintained separately.

Recommended source of truth for the first version:
- `dashboards/finance/current-dashboard-YYYY-MM.md`
- `dashboards/finance/daily-p-and-l-YYYY-MM.md`
- `dashboards/finance/income-ledger-YYYY-MM.md`
- `dashboards/finance/expense-ledger-YYYY-MM.md`
- `dashboards/finance/vendor-reconciliation-YYYY-MM.md`
- `dashboards/finance/anomaly-log.md`

### MVP data contract
For MVP, the page may consume a single normalized summary generated from the current accountant dashboard:
- reporting month
- last updated
- income MTD
- expense MTD
- net MTD
- cash status
- overall status
- source coverage rows
- active blockers
- artifact links

### Future data contract
Later versions can move from markdown-backed summaries to structured storage if needed:
- Drupal config/content entity
- JSON export generated from HQ
- API endpoint backed by the finance system

## Permissions model

### MVP permission recommendation
- visible only to authenticated internal roles
- editable only through accountant-owned source artifacts, not through manual Drupal page editing

### Governance rule
- Drupal should render the accounting state
- HQ finance artifacts remain the source of truth
- avoid a second manual bookkeeping surface inside Drupal

## UX states

The page should support three obvious states:

### 1. Healthy / complete
- all sources connected
- cards show actual values
- blockers section is empty or informational

### 2. Provisional
- some values available
- page still useful for direction
- provisional tags visible on affected sections

### 3. Blocked
- material source missing
- blocker callout shown near the top
- missing sources are clearly named

## MVP content state for today

If implemented immediately, the page would currently show:
- GitHub usage expense: source-backed `0.00`
- AWS expense: blocked
- Income: blocked / unconfirmed
- Cash: blocked / unconfirmed
- Overall financial health view: blocked due to incomplete source coverage

## Acceptance criteria

1. A dedicated Drupal page exists for Forseti accounting health.
2. The page has a clear executive summary for current financial status.
3. The page distinguishes source-backed, provisional, and blocked numbers.
4. The page surfaces active blockers and required follow-up.
5. The page links to underlying accounting artifacts or their Drupal-rendered equivalents.
6. Access is restricted to authorized internal users unless a separate public transparency scope is later approved.
7. The page never presents missing-source placeholder values as authoritative actuals.
8. The page visibly shows last refresh time and current confidence state.
9. The page can be updated by refreshing accountant-owned source artifacts rather than hand-editing page content.

## Delivery phases

### Phase 1 - MVP internal page
- Render the executive view, source coverage, blockers, and artifact links
- Use accountant dashboard as the backing summary

### Phase 2 - richer financial detail
- Add month-over-month comparison
- Add renewal calendar visibility
- Add anomaly highlights

### Phase 3 - structured integration
- Replace markdown interpretation with a structured summary feed if needed
- Add permission-aware drilldowns for deeper financial detail

## Recommended implementation path

1. PM/BA define feature scope and permission model.
2. Dev creates a Drupal route/page for `Financial Health`.
3. First version renders finance status from the current HQ finance dashboard model.
4. Later version can move to structured data ingestion if markdown-backed rendering becomes too brittle.

## Suggested PM/BA handoff questions

1. Which Drupal roles may view the page?
2. Should the route be internal-only or hidden behind a stronger admin permission?
3. Is the MVP allowed to render from accountant-owned markdown-derived summary data?
4. Should the first release include drilldowns, or only executive roll-up plus artifact links?

## Risks

- Building the page before income and cash sources are connected could create a polished UI with incomplete financial truth.
- Public exposure of institutional finance data would need a deliberate transparency/privacy decision.
- Direct markdown parsing in Drupal may be acceptable for MVP but may need a structured data layer later.

## Recommendation

Proceed with this as an internal-only Drupal feature request now, using the accountant dashboard as the MVP data model. This will give the institution a visible financial-health home without waiting for the full finance system to mature first.
