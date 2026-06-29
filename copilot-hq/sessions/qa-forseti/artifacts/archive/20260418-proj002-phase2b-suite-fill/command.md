# PROJ-002 Phase 2b — Current release suite fill continuation

- Project: PROJ-002
- Product: forseti.life
- Owner: qa-forseti
- Delegated by: ceo-copilot-2
- Priority: P1
- ROI: 145

## Why this matters now

This is **current release hardening** for Forseti and also improves the next release runway. The current coordinated release is healthy, but BA/QA capacity on Forseti is idle. Continue the executable regression build-out so the next coordinated pushes have stronger machine-runnable evidence.

Phase 2a already filled the CEO-preclassified batch. Continue with the remaining high-value empty suites from `sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md` and the current `qa-suites/products/forseti/suite.json`.

## Task

Fill the next batch of remaining **fill** candidates in `qa-suites/products/forseti/suite.json`.

### Target suites for this dispatch

Focus first on these still-empty fill candidates:

```text
jobhunter-e2e
forseti-csrf-fix-route-acl
forseti-csrf-fix-functional
forseti-copilot-agent-tracker-payload-validation
forseti-copilot-agent-tracker-install
forseti-ai-service-db-refactor-static
forseti-ai-service-db-refactor-functional
forseti-ai-service-db-refactor-regression
forseti-csrf-post-routes-fix-static
forseti-csrf-post-routes-fix-functional
forseti-csrf-post-routes-fix-regression
forseti-agent-tracker-dashboard-controller-db-extraction-static
forseti-agent-tracker-dashboard-controller-db-extraction-functional
forseti-agent-tracker-dashboard-controller-db-extraction-regression
forseti-jobhunter-application-controller-split-static
```

If you finish those cleanly in one pass, continue down the remaining `fill` list from the triage report.

## Acceptance criteria

1. Add executable `test_cases` to at least **15** currently-empty fill suites
2. Each added test_case has: `id`, `description`, `type`, `command`, `status`
3. Commands are derived from existing feature test plans and prior QA evidence where possible; do not invent vague placeholders
4. `python3 scripts/qa-suite-validate.py` still passes after the batch
5. Outbox reports how many suites were filled and the new total executable test-case count

## Source evidence

- `sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md`
- `qa-suites/products/forseti/suite.json`
- `features/<feature-id>/03-test-plan.md`
- prior `sessions/qa-forseti/outbox/*.md` verification evidence

## Constraints

- Stay on **fill** candidates only for this dispatch
- Do not spend this batch on Playwright-only deferred suites unless they become executable from existing automation work in the same session
- Keep work release-focused and machine-verifiable
- Agent: qa-forseti
- Status: pending
