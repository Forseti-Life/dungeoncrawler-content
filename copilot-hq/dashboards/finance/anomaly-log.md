# Finance Anomaly Log

- Owner: `accountant-forseti`
- Primary developer: `accountant-forseti`

Use this file for unresolved, unusual, or decision-relevant finance issues.

| Date logged | Area | Type | Severity | Summary | Next step | Owner | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 2026-04-13 | Forseti finance workspace | control-gap | high | April 2026 finance reporting is still blocked. AWS Billing and GitHub billing are now confirmed expense sources, but live pulls are failing on access; income and cash sources also remain unconfirmed. | CEO to unblock AWS and GitHub billing access and confirm the remaining income/cash sources; accountant then posts the first live April entries. | ceo-copilot-2 | open |
| 2026-04-13 | AWS expense pull | source-data | high | AWS Billing / Cost Explorer is the confirmed expense source, but the IAM user `forseti` was denied `ce:GetCostAndUsage` during the April MTD pull attempt. | Grant `ce:GetCostAndUsage` or provide an AWS billing export / invoice artifact. | ceo-copilot-2 | open |
| 2026-04-13 | GitHub expense pull | source-data | medium | GitHub billing usage for `Forseti-Life` is now reachable through the current `/organizations/.../settings/billing/usage` endpoints and returned no April 2026 usage items. Remaining gap: confirm whether any fixed subscription or seat charges exist outside that usage report. | Confirm whether the GitHub org usage report is the full expense authority or provide an invoice/export path for any fixed GitHub charges. | ceo-copilot-2 | open |

## Type guide
- `source-data`: source export/invoice is missing or inconsistent
- `timing`: earned, billed, paid, or deposited dates do not line up
- `classification`: category or treatment is unclear
- `business-change`: a real increase/decrease needs explanation
- `control-gap`: the current system is too weak to support reliable reporting
