# Feature Brief

- Work item id: forseti-qa-suite-fill-controller-extraction
- Website: forseti.life
- Module: qa_suites
- Status: ready
- Release: TBD (release-g or release-h)
- Feature type: qa-infrastructure
- PM owner: pm-forseti
- Dev owner: qa-forseti
- QA owner: qa-forseti
- Priority: medium
- Source: PROJ-002 Phase 1 (dispatched 2026-04-09)

## Summary

Populate `test_cases` for the controller extraction phase 1 suite shells. The controller extraction refactor is shipped; these suites verify the static structural constraints and regression guard.

## Suites in scope

- forseti-jobhunter-controller-extraction-phase1-static
- forseti-jobhunter-controller-extraction-phase1-regression

## Goal

Static suite verifies no direct DB calls in controller files; repository pattern is in place. Regression suite verifies no behavioral regressions after extraction.

## Non-goals

- Phase 2 controller extraction work

## Security acceptance criteria
- Security AC exemption: QA-infrastructure only — no Drupal routes, no user-facing input, no PII handled. All changes are confined to qa-suites/ config/test files.
