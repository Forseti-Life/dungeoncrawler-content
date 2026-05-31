# Feature Brief

- Work item id: forseti-qa-suite-retire-stale
- Website: forseti.life
- Module: qa_suites
- Project: PROJ-002
- Group Order: 1
- Group: e2e-coverage
- Group Title: E2E Test Coverage
- Group Sort: 6
- Status: shipped
- Release: 20260409-forseti-release-i
- Feature type: qa-infrastructure
- PM owner: pm-forseti
- Dev owner: qa-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: PROJ-002 Phase 1 (dispatched 2026-04-09)

## Summary

Delete all confirmed `retire` suite shells from `qa-suites/products/forseti/suite.json`. These are superseded refactor suites whose tests are either merged into higher-level suites or no longer relevant to current code structure. This reduces noise and prevents false "coverage" impressions.

## Suites in scope (pending triage confirmation)

Pre-classified as `retire` — must be confirmed by qa-forseti triage report before deletion:

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
- forseti-jobhunter-profile-e2e
- forseti-jobhunter-browser-automation-e2e
- forseti-jobhunter-browser-automation-functional

## Goal

`suite.json` contains no empty shell entries for superseded features. Each remaining entry is either populated (fill) or deferred with a reason.

## Security acceptance criteria
- Security AC exemption: QA-infrastructure only — deletes stale suite entries from suite.json. No Drupal routes, no user-facing input, no PII handled.

## Non-goals

- Replacing retired suites with new ones (that is handled by the fill features)
- Modifying any Drupal module code
