# Test Plan (Tester-owned)

Policy note:
- The canonical runnable test metadata must live in either the live product manifest (`qa-suites/products/<product>/suite.json`) or a feature overlay manifest (`qa-suites/products/<product>/features/<feature-id>.json`).
- This test plan is a planning artifact and must point to the automated suite location + runner command(s).

## Scope
- In scope:
- Out of scope:

## Test Matrix
- Browsers/devices (if UI):
- Roles/permissions:
- Environments:

## Central automated test-case suite (SoT)
- Overlay manifest path:
- Live release manifest path:
- How to run (commands):
- Reporting (where PASS/FAIL is recorded):

## Feature suite overlay requirements
- Overlay file: `qa-suites/products/<product>/features/<feature-id>.json`
- Each suite entry must declare:
  - `owner_seat`
  - `source_path`
  - `env_requirements`
  - `release_checkpoint`

## Standard source locations
- Unit tests:
- Functional tests:
- E2E tests:
- Audit/static checks:

## Manual Tests (non-SoT)
- Only allowed as exploratory guidance.
- Any manual-only test that is release-critical must be converted into automated PASS/FAIL coverage or explicitly accepted as a documented exception.
- [ ]

## Automated Tests
- Existing suites to run:
- New tests expected (if any):

## Pass/Fail Criteria
- 

## Knowledgebase references
- Related lesson(s) or proposal(s) (or 'none found'):

## What I learned (QA)
- 

## What I'd change next time (QA)
- 
