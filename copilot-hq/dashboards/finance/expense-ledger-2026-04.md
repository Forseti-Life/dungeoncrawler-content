# Expense Ledger

- Period: `2026-04`
- Currency: `USD`
- Owner: `accountant-forseti`
- Primary developer: `accountant-forseti`
- Extraction date(s): `2026-04-13`

| Date | Vendor | Category | Description | Amount | Recurring | Payment status | Renewal / due date | Source record |
| --- | --- | --- | --- | ---: | --- | --- | --- | --- |
| 2026-04-13 | GitHub | CI/CD | April 2026 org billing usage report returned no usage items for `Forseti-Life`. | 0.00 | yes | paid | monthly | `/organizations/Forseti-Life/settings/billing/usage?year=2026&month=4` |

## Notes
- GitHub has one source-backed April result posted below: 0.00 usage-based expense as of 2026-04-13.
- Confirmed expense sources for April 2026:
  - AWS Billing / Cost Explorer
  - GitHub billing for `Forseti-Life`
- AWS pull attempted on 2026-04-13 and failed with `AccessDeniedException` for `ce:GetCostAndUsage`.
- GitHub org usage pull succeeded on 2026-04-13 using `/organizations/Forseti-Life/settings/billing/usage?year=2026&month=4` and returned `usageItems: []`.
- GitHub fixed subscription or seat charges, if any, still need confirmation from an invoice/export path or equivalent billing view.
