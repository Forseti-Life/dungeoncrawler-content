# Vendor Reconciliation

- Period: `2026-04`
- Owner: `accountant-forseti`
- Primary developer: `accountant-forseti`
- Prepared by: `accountant-forseti`

| Area | Primary total | Secondary total | Difference | Status | Explanation |
| --- | ---: | ---: | ---: | --- | --- |
| AWS | 0.00 | 0.00 | 0.00 | blocked | Primary source confirmed as AWS Billing / Cost Explorer, but the current IAM user lacks `ce:GetCostAndUsage`, so no April total could be pulled yet. |
| GitHub | 0.00 | 0.00 | 0.00 | provisional | Current org usage endpoint for `Forseti-Life` is accessible and returned no April usage items; confirm whether any fixed subscription or seat charges exist outside the usage report. |
| Other vendor | 0.00 | 0.00 | 0.00 | open | No current vendor billing source list has been documented yet. |
| Income / payout | 0.00 | 0.00 | 0.00 | open | No authoritative revenue and cash evidence source has been documented yet. |

## Open reconciliation items
- Re-run AWS pull after granting `ce:GetCostAndUsage` or providing an invoice/export artifact.
- Confirm whether GitHub has any fixed subscription or seat charges outside the successful org usage report for `Forseti-Life`.
- Confirm the exact source of truth for other vendor expense, income/payout activity, and cash evidence for April 2026.
- Replace placeholder totals only after those sources are accessible and reviewed.

## Recommendation
- Monitor / investigate / optimize / escalate: escalate the AWS access blocker and the remaining GitHub billing completeness question to the CEO, then populate April ledgers from the confirmed systems.
