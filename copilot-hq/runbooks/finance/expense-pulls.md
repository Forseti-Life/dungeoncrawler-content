# Finance Expense Pulls

- Owner: `accountant-forseti`
- Primary developer: `accountant-forseti`

Use this runbook for repeatable AWS and GitHub expense pulls into the monthly finance artifacts.

## Current expense source decisions
- **AWS expense:** AWS Billing / Cost Explorer
- **GitHub expense:** GitHub billing for `Forseti-Life`

## Authentication model

### Recommended operating model
- Use **dedicated read-only finance credentials** for each billing system.
- Keep accountant credentials separate from broad admin or engineering credentials.
- Prefer the narrowest permission set that still allows repeatable month-to-date pulls and closed-month export access.

### Minimum authentication required now

| System | Current auth state | Minimum auth needed | Purpose |
| --- | --- | --- | --- |
| AWS Billing | IAM access keys exist for user `forseti` | `ce:GetCostAndUsage` on `*` | Pull open-month Cost Explorer spend |
| GitHub Billing | `/home/ubuntu/github.token` works for org usage API | Current token is sufficient for usage-based pulls | Pull org usage-based GitHub billing for `Forseti-Life` |
| GitHub fixed charges | Not yet proven from API output | Invoice/export path or equivalent billing view if fixed seat/subscription charges exist | Confirm whether non-usage GitHub charges must also be posted |

### What we do **not** need right now
- We do **not** need broader AWS admin access if `ce:GetCostAndUsage` or an equivalent billing export path is granted.
- We do **not** need a new GitHub token for usage-based billing if `/home/ubuntu/github.token` remains valid for `/organizations/Forseti-Life/settings/billing/usage`.
- We do **not** need application-level authentication changes in the product codebase for this accounting workflow.

## AWS pull

### Open-month pull
Use Cost Explorer for month-to-date expense on an open month:

```bash
aws ce get-cost-and-usage \
  --time-period Start=YYYY-MM-01,End=YYYY-MM-DD \
  --granularity MONTHLY \
  --metrics UnblendedCost \
  --group-by Type=DIMENSION,Key=SERVICE \
  --output json
```

### Closed-month pull
- Prefer the closed-month invoice export when available.
- Use Cost Explorer only as the operational view for intra-month tracking.

### Last attempt
- Date: `2026-04-13`
- Caller identity: `arn:aws:iam::647731524551:user/forseti`
- Result: blocked
- Error: `AccessDeniedException` for `ce:GetCostAndUsage`

### Requirement to unblock
- Grant the IAM user `forseti` permission for `ce:GetCostAndUsage`, or provide an AWS billing export / invoice artifact the accountant can treat as authoritative.

### Minimal AWS policy to unblock
If the intent is to keep accountant access narrow, the smallest useful starting policy is:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "ce:GetCostAndUsage"
      ],
      "Resource": "*"
    }
  ]
}
```

### Re-run command after access is granted
```bash
aws ce get-cost-and-usage \
  --time-period Start=2026-04-01,End=2026-04-14 \
  --granularity MONTHLY \
  --metrics UnblendedCost \
  --group-by Type=DIMENSION,Key=SERVICE \
  --output json
```

## GitHub pull

### Authenticated identity
- Current token authenticates as: `keithaumiller`

### Pull path
Use the GitHub billing usage API or an invoice/export path for the `Forseti-Life` org.

Current working endpoints:

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

### Last attempt
- Date: `2026-04-13`
- Result: partial success
- Endpoint behavior:
  - legacy `/orgs/.../settings/billing/*` endpoints returned `410` moved responses
  - current `/organizations/Forseti-Life/settings/billing/usage` and `/usage/summary` endpoints succeeded
  - both current endpoints returned empty April 2026 usage data (`usageItems: []`)

### Requirement to unblock
- Decide whether the successful org usage endpoint is the full GitHub expense authority for Forseti, or provide a second export / invoice path if fixed subscription or seat charges exist outside the usage report.

### Current evidence supporting 0.00 GitHub usage expense
- `Forseti-Life` org billing usage returned `usageItems: []` for April 2026.
- `Forseti-Life` premium request usage returned `usageItems: []` for April 2026.
- `Forseti-Life` currently has:
  - `0` repositories returned by `/orgs/Forseti-Life/repos`
  - `1` org member returned by `/orgs/Forseti-Life/members`
  - org creation date `2026-04-10`

This is enough to treat **usage-based GitHub expense** as `0.00` for April 2026. It is **not** enough to prove the absence of any fixed subscription or seat charge without an invoice/export or equivalent billing view.

## Posting rule
Do not post AWS or GitHub expense amounts into the April ledger until the numbers are source-backed by one of the approved pull paths above.
