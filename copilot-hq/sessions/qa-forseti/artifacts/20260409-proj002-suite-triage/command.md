# PROJ-002 Phase 1 — Suite Triage

- Project: PROJ-002 (dashboards/PROJECTS.md)
- Phase: 1 — Triage
- Dispatched by: pm-forseti
- Date: 2026-04-09

## Task

Audit all empty suites in `qa-suites/products/forseti/suite.json` and produce a triage report.

**Report output path:** `sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md`

## Definition of done

- Every suite entry without `test_cases` is classified as `fill`, `retire`, or `defer`
- Triage report is a markdown table: `suite_id | disposition | reason`
- Pre-classifications below are validated (correct or corrected with reason)
- Report identifies the final confirmed `fill` list (≥ 20 suites expected)

## Pre-classified by CEO (validate or correct each — do NOT skip)

### Likely `fill` — feature shipped, needs test_cases

- forseti-jobhunter-application-status-dashboard-static
- forseti-jobhunter-application-status-dashboard-functional
- forseti-jobhunter-application-status-dashboard-regression
- forseti-jobhunter-google-jobs-ux-static
- forseti-jobhunter-google-jobs-ux-functional
- forseti-jobhunter-google-jobs-ux-regression
- forseti-jobhunter-profile-completeness-static
- forseti-jobhunter-profile-completeness-functional
- forseti-jobhunter-profile-completeness-regression
- forseti-jobhunter-resume-tailoring-display-static
- forseti-jobhunter-resume-tailoring-display-functional
- forseti-jobhunter-resume-tailoring-display-regression
- forseti-ai-conversation-user-chat-static
- forseti-ai-conversation-user-chat-acl
- forseti-ai-conversation-user-chat-csrf-post
- forseti-ai-conversation-user-chat-regression
- forseti-jobhunter-application-submission-route-acl
- forseti-jobhunter-application-submission-unit
- forseti-copilot-agent-tracker-route-acl
- forseti-copilot-agent-tracker-api
- forseti-copilot-agent-tracker-happy-path
- forseti-copilot-agent-tracker-security
- forseti-jobhunter-browser-automation-unit
- forseti-jobhunter-controller-extraction-phase1-static
- forseti-jobhunter-controller-extraction-phase1-regression
- forseti-csrf-seed-consistency
- role-url-audit

### Likely `retire` — superseded refactors (confirm before retiring)

- forseti-jobhunter-controller-refactor-static
- forseti-jobhunter-controller-refactor-unit
- forseti-jobhunter-controller-refactor-phase2-unit-db-calls
- forseti-jobhunter-controller-refactor-phase2-unit-service-methods
- forseti-jobhunter-controller-refactor-phase2-unit-services-yml
- forseti-jobhunter-controller-refactor-phase2-unit-lint-controller
- forseti-jobhunter-controller-refactor-phase2-unit-lint-service
- forseti-jobhunter-controller-refactor-phase2-unit-no-new-routes
- forseti-jobhunter-controller-refactor-phase2-e2e-post-flows
- forseti-ai-service-refactor-static
- forseti-ai-service-refactor-functional
- forseti-ai-service-refactor-unit
- forseti-ai-debug-gate-route-acl
- forseti-ai-debug-gate-static
- forseti-ai-debug-gate-functional
- forseti-jobhunter-profile-e2e (superseded by jobhunter-e2e)
- forseti-jobhunter-browser-automation-e2e (merged into jobhunter-e2e)
- forseti-jobhunter-browser-automation-functional (superseded)

### Likely `defer` — groomed but not yet shipped

- forseti-jobhunter-application-status-dashboard-e2e
- forseti-jobhunter-google-jobs-ux-e2e
- forseti-jobhunter-profile-completeness-e2e
- forseti-jobhunter-resume-tailoring-display-e2e
- forseti-ai-conversation-user-chat-e2e
- forseti-langgraph-ui-auth
- forseti-langgraph-ui-regression
- forseti-langgraph-ui-build
- forseti-langgraph-ui-test

## All remaining suites not in above lists

Classify the remaining empty suites (those not pre-classified above) — any suite with no `test_cases`. Check each against production `https://forseti.life` to determine shipped status.

## Output format

```markdown
# PROJ-002 Suite Triage Report

Date: YYYY-MM-DD
Auditor: qa-forseti

## Summary
- Total suites audited: N
- fill: N
- retire: N
- defer: N

## Triage table

| suite_id | disposition | reason |
|---|---|---|
| forseti-jobhunter-... | fill | Shipped in release-f; module active in production |
...
```

## Acceptance criteria

- [ ] All empty suites classified
- [ ] Pre-classifications validated (corrections noted where pre-class was wrong)
- [ ] Report saved to `sessions/qa-forseti/artifacts/proj002-suite-triage/triage-report.md`
- [ ] Report committed to HQ repo
- [ ] Outbox written with summary counts and link to report

## Constraints

- Do NOT modify `suite.json` during triage — that is Phase 2 work
- Only classify; do not attempt to fill test_cases yet
- Check `features/*/feature.md` Status field and production URL to verify "shipped" status
