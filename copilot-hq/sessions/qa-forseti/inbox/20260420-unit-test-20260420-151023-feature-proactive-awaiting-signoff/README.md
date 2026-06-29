# QA unit test dispatch

## Scope
- QA seat: qa-forseti
- Completed dev item: 20260420-151023-feature-proactive-awaiting-signoff
- Dev outbox evidence: sessions/dev-forseti/outbox/20260420-151023-feature-proactive-awaiting-signoff.md
- Verification mode: targeted regression/unit verification for the changed surface only

## Acceptance criteria
1. QA reviews the completed dev item and derives a targeted verification plan from the dev outbox and any referenced acceptance criteria.
2. The regression checklist contains an evergreen entry for this completed item:
   - org-chart/sites/forseti.life/qa-regression-checklist.md
3. Infrastructure-scoped items use operator-audit checks only; they must not use site-audit, URL, or Playwright flows.
4. The QA outbox states an explicit APPROVE/BLOCK verdict and includes concrete evidence.

## Verification method
- Use the commands in `command.md` for the scoped QA check.
- Record exact commands and outputs in the outbox summary/body.
- Agent: qa-forseti
- Status: pending
