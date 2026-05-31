# Roadmap — forseti-financial-health-home

- Feature: `forseti-financial-health-home`
- Website: `forseti.life`
- Project: `PROJ-008`
- Owner: `pm-forseti`
- Source owner: `accountant-forseti`
- Architecture: `features/forseti-financial-health-home/03-next-phase-architecture.md`
- Last updated: 2026-04-19
- Status: 🟢 Done (Phase 1 implemented)

> See also: `ROADMAP.md` (org-level) for cross-product release context.

## Objective

Ship an internal Drupal **Financial Health** home that surfaces Forseti's institutional financial state from the accountant book of record.

## Why now

Forseti's accounting workspace now exists and has a single current dashboard in `dashboards/finance/current-dashboard-2026-04.md`, but leadership still has to leave the product and inspect HQ markdown to understand financial health. This roadmap turns that operational accounting state into a first-class internal product surface.

## Current Forseti delivery pipeline context (as of 2026-04-19)

### ✅ Shipped since this roadmap was written
All items previously listed as "in progress" or "ready backlog" are now **shipped**:

| Feature | Status |
|---|---|
| `forseti-jobhunter-interview-outcome-tracker` | ✅ Shipped |
| `forseti-jobhunter-offer-tracker` | ✅ Shipped |
| `forseti-jobhunter-application-analytics` | ✅ Shipped |
| `forseti-jobhunter-follow-up-reminders` | ✅ Shipped |
| `forseti-ai-local-llm-provider-selection` | ✅ Shipped |
| `forseti-community-incident-report` | 🟢 Done |
| `forseti-financial-health-home` | 🟢 Done — Phase 1 implemented |
| `forseti-jobhunter-company-interest-tracker` | ✅ Shipped |
| `forseti-jobhunter-company-research-tracker` | 🟢 Done |
| `forseti-jobhunter-contact-referral-tracker` | 🟢 Done |
| `forseti-jobhunter-contact-tracker` | ✅ Shipped |
| `forseti-jobhunter-job-board-preferences` | 🟢 Done |
| `forseti-jobhunter-resume-version-labeling` | 🟢 Done |
| `forseti-jobhunter-resume-version-tracker` | 🟢 Done |
| `forseti-langgraph-console-run-session` | ✅ Shipped |

### 📥 Current backlog (financial-health specific)
- Phase 2 (anomaly highlights, renewal calendar, trend deltas) — awaiting structured data feed maturity
- Dedicated finance module split — deferred until Phase 2 scope is confirmed

## Delivery phases

### ✅ Phase 1 — Internal MVP (done)
- Route `/internal/financial-health` — implemented
- Executive health band — rendered
- Source coverage — rendered
- Active blockers — rendered
- Current-month roll-up — rendered
- Links to underlying accountant artifacts — wired

### 📥 Phase 2 — Richer operational value (backlog)
- Add anomaly highlights
- Add renewal calendar summary
- Add month-over-month trend deltas
- Add explicit last-refresh and data-age indicators

### 📥 Phase 3 — Structured finance feed (deferred)
- Replace markdown-derived rendering with a structured summary feed if needed
- Add more granular drilldowns behind internal permissions

## Dependencies

### Hard dependencies
- Internal access model decision
- Source-of-truth contract between Drupal rendering and accountant artifacts

### Soft dependencies
- AWS billing pull access
- Income source confirmation
- Cash evidence source confirmation
- GitHub fixed-charge completeness decision

## Risks

- If activated too early, the page may be structurally correct but financially sparse because source coverage is still incomplete.
- If Drupal becomes a second manual edit surface, accounting truth will drift from HQ.
- If access controls are too loose, internal institutional finance data may become visible too broadly.

## Recommended next action

Phase 1 is complete. `pm-forseti` should evaluate Phase 2 scope once AWS billing pull access and income/cash source decisions are resolved. Activate Phase 2 when structured data feeds mature enough that the page won't be dominated by blocked/missing values.

**Phase 2 activation criteria:**
- AWS billing access confirmed
- At least 2 months of rolling income data available
- Board confirms financial-health home remains in `institutional_management` (no dedicated module split needed)
