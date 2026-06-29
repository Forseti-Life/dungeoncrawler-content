# QA unit test dispatch

## Scope
- QA seat: qa-infra
- Completed dev item: 20260418-syshealth-merge-health-remediation
- Dev outbox evidence: sessions/dev-infra/outbox/20260418-syshealth-merge-health-remediation.md
- Verification mode: targeted regression/unit verification for the changed surface only

## Acceptance criteria
1. QA reviews the completed dev item and derives a targeted verification plan from the dev outbox and any referenced acceptance criteria.
2. The regression checklist contains an evergreen entry for this completed item:
   - org-chart/sites/infrastructure/qa-regression-checklist.md
3. Infrastructure-scoped items use operator-audit checks only; they must not use site-audit, URL, or Playwright flows.
4. The QA outbox states an explicit APPROVE/BLOCK verdict and includes concrete evidence.

## Verification method
- Use the commands in `command.md` for the scoped QA check.
- Record exact commands and outputs in the outbox summary/body.
