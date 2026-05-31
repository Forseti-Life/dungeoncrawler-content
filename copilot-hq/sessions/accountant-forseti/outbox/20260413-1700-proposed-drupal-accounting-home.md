- Status: done
- Summary: I translated the request for a `forseti.life` accounting home into a concrete accountant-owned product proposal and then fleshed it out into an implementation-ready spec. The proposal now defines a Drupal `Financial Health` home for leadership and authorized internal users, including suggested route placement, page order, executive cards, source-coverage table, active blockers, current-month financial detail, artifact links, metric semantics, UX states (`complete`, `provisional`, `blocked`), permission model, and phased rollout. It remains grounded in the real accounting state: GitHub usage expense is source-backed at `0.00`, while AWS expense, income, and cash remain blocked or unconfirmed. This gives PM/BA/dev enough specificity to scope the feature cleanly without treating incomplete finance sources as complete truth.

## Source systems used
- Existing accounting workspace under `dashboards/finance/`
- Current finance dashboard: `dashboards/finance/current-dashboard-2026-04.md`

## Recommendation for CEO
- Action: treat the proposed Drupal accounting home as the next productization step for the finance system.
- Why: the accounting workspace now has enough structure to support an internal institutional-health page, even though some underlying sources are still blocked.
- ROI: 18

## Next actions
- Hand proposal to Forseti PM/BA/dev flow for feature scoping and implementation.
- Keep the page internal-only by default.
- Use the accountant dashboard as the first data model.
- Start with an MVP route such as `/internal/financial-health` and render from the accountant book of record.

## Artifact
- `sessions/accountant-forseti/artifacts/20260413-accounting-home-proposal.md`
