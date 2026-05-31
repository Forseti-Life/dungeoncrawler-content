# Feature Brief

- Work item id: forseti-qa-suite-fill-jobhunter-submission
- Website: forseti.life
- Module: qa_suites
- Project: PROJ-002
- Group Order: 1
- Group: e2e-coverage
- Group Title: E2E Test Coverage
- Group Sort: 4
- Status: shipped
- Release: 20260409-forseti-release-h
- Feature type: qa-infrastructure
- PM owner: pm-forseti
- Dev owner: qa-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: PROJ-002 Phase 1 (dispatched 2026-04-09)

## Summary

Populate `test_cases` for the jobhunter application-submission suite shells. These cover ACL audit (route-level auth/anon enforcement) and PHPUnit unit tests for WorkdayWizardService. Both suites are in `suite.json` but have empty `test_cases` arrays.

## Suites in scope

- forseti-jobhunter-application-submission-route-acl
- forseti-jobhunter-application-submission-unit

## Goal

Each suite has executable test_cases covering the key ACs for the submission flow.

## Non-goals

- E2E browser automation for submission (covered by forseti-qa-e2e-auth-pipeline)
- WorkdayWizardService new functionality

## Security acceptance criteria
- Security AC exemption: QA-infrastructure only — no Drupal routes, no user-facing input, no PII handled. All changes are confined to qa-suites/ config/test files.
