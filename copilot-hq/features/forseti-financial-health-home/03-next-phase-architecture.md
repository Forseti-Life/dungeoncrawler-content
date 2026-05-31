# Next-Phase Architecture — forseti-financial-health-home

- Feature: `forseti-financial-health-home`
- Owner: `architect-copilot`
- Date: `2026-04-13`
- Status: `proposed`
- Current MVP host: `institutional_management`

## Problem

The current MVP works by reading the accountant dashboard markdown directly from `copilot-hq`. That is acceptable for a fast internal launch, but it is not the right long-term integration boundary:

- Drupal is parsing presentation markdown instead of consuming a stable finance contract.
- Month-over-month views and richer drilldowns will get brittle if every new field depends on ad hoc markdown interpretation.
- The accountant workflow needs to stay authoritative without turning Drupal into a second bookkeeping surface.

## Architectural decision

**Keep `copilot-hq` as the finance book of record, and add a generated structured summary layer that Drupal consumes.**

This means:

1. Accountant-owned ledgers, reconciliations, and dashboard markdown remain the operational source of truth.
2. A generated finance summary artifact becomes the **application contract** for Drupal.
3. Drupal renders the structured summary and stops parsing markdown directly once parity is proven.

## Recommended target design

## Layer 1 — source evidence

Authoritative upstream evidence remains outside Drupal:

- AWS billing pulls
- GitHub billing pulls
- future income source
- future cash evidence source

These sources feed accountant-controlled monthly artifacts and reconciliation notes.

## Layer 2 — accountant working artifacts

Current active files remain:

- `dashboards/finance/current-dashboard-YYYY-MM.md`
- `dashboards/finance/daily-p-and-l-YYYY-MM.md`
- `dashboards/finance/income-ledger-YYYY-MM.md`
- `dashboards/finance/expense-ledger-YYYY-MM.md`
- `dashboards/finance/vendor-reconciliation-YYYY-MM.md`
- `dashboards/finance/anomaly-log.md`

These continue to serve humans first.

## Layer 3 — generated finance summary contract

Add a generated directory:

- `dashboards/finance/generated/financial-health-current.json`
- `dashboards/finance/generated/financial-health-history.json`

Recommended generator location:

- `scripts/finance/generate-financial-health-summary.py`

The generator should read the accountant-owned markdown artifacts, normalize the current state, and produce a machine-safe summary for Drupal and other internal consumers.

## Layer 4 — Drupal presentation

Drupal route stays:

- `/internal/financial-health`

Drupal module stays in:

- `institutional_management` for the next phase

Drupal should consume only the generated summary once the contract is in place.

## Data contract

## Required top-level fields

```json
{
  "reporting_month": "2026-04",
  "last_updated": "2026-04-13",
  "owner": "accountant-forseti",
  "primary_developer": "accountant-forseti",
  "overall_status": "blocked",
  "overall_confidence": "blocked",
  "data_age": {
    "generated_at": "2026-04-13T18:00:00Z",
    "age_seconds": 0,
    "freshness": "current"
  },
  "executive_cards": [],
  "current_financial_view": [],
  "rollup": [],
  "source_coverage": [],
  "active_blockers": [],
  "artifact_links": []
}
```

## Required enumerations

Status vocabulary should be finite and reused consistently:

- `source-backed`
- `live`
- `provisional`
- `partial`
- `blocked`
- `unknown`

Drupal should map these to presentation states, but should not invent new finance statuses on its own.

## Executive card schema

Each executive card should include:

- `id`
- `label`
- `value`
- `status`
- `explanation`
- `last_refresh`

Required card ids:

- `income_mtd`
- `expense_mtd`
- `net_mtd`
- `cash_status`
- `overall_confidence`

## Source coverage schema

Each source row should include:

- `id`
- `label`
- `status`
- `last_refresh`
- `detail`
- `blocking_reason`

Required source ids for the current feature:

- `github_usage_billing`
- `github_fixed_charges`
- `aws_billing`
- `income`
- `cash_evidence`

## Roll-up schema

Each roll-up row should include:

- `id`
- `label`
- `value`
- `status`
- `detail`

Required roll-up ids:

- `income_subtotal`
- `expense_subtotal`
- `net_subtotal`
- `source_backed_expense_subtotal`
- `blocked_missing_expense_subtotal`
- `blocked_missing_income_subtotal`

## Artifact link schema

Each artifact entry should include:

- `id`
- `label`
- `path`
- `kind`

## Ownership model

## Accountant ownership

The accountant seat remains the owner of:

- source decisions
- monthly ledgers
- reconciliation state
- blocker semantics
- dashboard truth

## Architect/dev ownership

Architect/dev owns:

- generator implementation
- JSON contract stability
- Drupal rendering integration
- caching and access behavior

## Governance rule

No Drupal UI should directly edit the finance summary.

If a number changes, it must change because the accountant artifacts or pull evidence changed and the generator was rerun.

## Caching and freshness

## Generator cadence

Recommended initial cadence:

1. manual run after accountant dashboard updates
2. later, automatic regeneration as part of the finance pull workflow

## Drupal cache policy

For the next phase:

- controller cache max-age may move from `0` to a short TTL like `300`
- page should always display:
  - summary generation timestamp
  - source dashboard last-updated timestamp
  - freshness label such as `current`, `aging`, or `stale`

## Failure behavior

If the generated JSON is missing or invalid:

1. show a prominent internal warning banner
2. fall back to the current markdown-backed path only during the transition window
3. remove fallback once the generator path has proven reliable

## Security and permissions

- Keep the page internal-only
- Keep `view institution reports` as the baseline view permission unless PM defines a more specific finance role later
- Do not expose raw billing exports, credentials, or invoice artifacts through Drupal
- If deeper drilldowns are added later, gate them behind stronger permissions than the summary page

## Module boundary recommendation

Do **not** split to a dedicated `institutional_finance` module yet.

Reason:

- the feature is still a single internal reporting surface
- it already fits the existing `institutional_management` route/permission model
- the main architectural need is data-contract hardening, not module separation

Revisit module extraction only if one of these becomes true:

1. finance grows into multiple routes, forms, and drilldown screens
2. finance needs its own services, plugins, or storage model
3. non-institutional consumers need to reuse the same finance domain code

## Phase plan

## Phase 2A — structured summary sidecar

- Build the generator
- Generate `financial-health-current.json`
- Keep Drupal markdown parsing as the temporary live path
- Compare JSON output to current live page for parity

## Phase 2B — Drupal contract switch

- Update Drupal to prefer JSON input
- Keep markdown fallback behind a guarded code path
- Add explicit freshness and anomaly signals

## Phase 2C — richer operational panels

- anomaly highlights
- renewal calendar summary
- month-over-month deltas
- optional history visualization from `financial-health-history.json`

## Verification strategy

## Generator verification

- validate JSON syntax
- validate required keys
- validate status enums against the allowed vocabulary
- verify generated roll-up values match accountant dashboard values for the month

## Drupal verification

- route still returns `403` for anonymous
- authorized role can render the page
- page shows generation timestamp and dashboard timestamp
- page renders degraded-warning state when the contract is missing or stale

## Risks

- If the generator becomes a second place for finance logic instead of a pure normalization layer, accounting semantics can drift.
- If fallback remains forever, the system will carry two contracts instead of one.
- If freshness metadata is omitted, leaders may trust stale finance state.

## Recommended next action

Implement the structured summary generator in `copilot-hq`, produce the first JSON contract beside the existing April dashboard, and then switch Drupal to prefer that contract while retaining a temporary parity fallback.
