# Expense Pull Attempts — 2026-04-13

- Owner: `accountant-forseti`
- Primary developer: `accountant-forseti`

## Scope
- April 2026 month-to-date expense pulls for the confirmed expense sources:
  - AWS Billing / Cost Explorer
  - GitHub billing for `Forseti-Life`

## AWS
- Caller identity: `arn:aws:iam::647731524551:user/forseti`
- Command shape used:

```bash
aws ce get-cost-and-usage \
  --time-period Start=2026-04-01,End=2026-04-14 \
  --granularity MONTHLY \
  --metrics UnblendedCost \
  --group-by Type=DIMENSION,Key=SERVICE \
  --output json
```

- Result: blocked
- Error:

```text
An error occurred (AccessDeniedException) when calling the GetCostAndUsage operation:
User: arn:aws:iam::647731524551:user/forseti is not authorized to perform:
ce:GetCostAndUsage on resource: arn:aws:ce:us-east-1:647731524551:/GetCostAndUsage
because no identity-based policy allows the ce:GetCostAndUsage action
```

## GitHub
- Authenticated user: `keithaumiller`
- Current working commands:

```bash
GH_TOKEN=$(cat /home/ubuntu/github.token) gh api \
  -H "Accept: application/vnd.github+json" \
  -H "X-GitHub-Api-Version: 2026-03-10" \
  "/organizations/Forseti-Life/settings/billing/usage?year=2026&month=4"

GH_TOKEN=$(cat /home/ubuntu/github.token) gh api \
  -H "Accept: application/vnd.github+json" \
  -H "X-GitHub-Api-Version: 2026-03-10" \
  "/organizations/Forseti-Life/settings/billing/usage/summary?year=2026&month=4"
```

- Result: partial success
- Responses:
  - legacy `/orgs/.../settings/billing/*` endpoints returned `410` (`This endpoint has been moved`)
  - current `/organizations/Forseti-Life/settings/billing/usage` endpoint returned:

```json
{ "usageItems": [] }
```

  - current `/organizations/Forseti-Life/settings/billing/usage/summary` endpoint returned:

```json
{
  "timePeriod": { "year": 2026, "month": 4 },
  "organization": "Forseti-Life",
  "usageItems": []
}
```

## Conclusion
- Expense sources are now selected.
- GitHub usage-based billing is observable and currently shows no April 2026 usage items for `Forseti-Life`.
- Finance artifacts remain partially blocked until AWS Cost Explorer permission is available and GitHub fixed-charge completeness is confirmed.

## Additional GitHub context
- `/organizations/Forseti-Life/settings/billing/premium_request/usage?year=2026&month=4` also returned `usageItems: []`
- `/orgs/Forseti-Life/repos?type=all&per_page=100` returned `[]`
- `/orgs/Forseti-Life/members?per_page=100` returned a single member: `keithaumiller`

## Recommended interpretation
- Treat GitHub **usage-based** April expense as `0.00` and keep that posted in the April ledger.
- Keep GitHub **fixed-charge completeness** marked provisional until an invoice/export or equivalent billing view confirms whether any non-usage charges exist.
