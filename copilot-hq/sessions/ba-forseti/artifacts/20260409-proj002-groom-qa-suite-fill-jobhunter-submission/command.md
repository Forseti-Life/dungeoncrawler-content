# BA Grooming: forseti-qa-suite-fill-jobhunter-submission

- Project: PROJ-002 Phase 1
- Feature: forseti-qa-suite-fill-jobhunter-submission
- Dispatched by: pm-forseti
- Date: 2026-04-09
- ROI: 45

## Task

Expand `features/forseti-qa-suite-fill-jobhunter-submission/01-acceptance-criteria.md` from stub to full AC.

## Suites to cover

- `forseti-jobhunter-application-submission-route-acl` — verifies all 7 application-submission POST routes return 403 for anon, 200 for authenticated
- `forseti-jobhunter-application-submission-unit` — PHPUnit for WorkdayWizardService (TC-01..TC-11)

## AC format guidance

For route-acl suite: list each route path and expected status code for each role (anon, authenticated). These can be bash curl commands.

For unit suite: reference the PHPUnit class file path (check `sites/forseti/web/modules/custom/job_hunter/tests/`) and name the specific test methods that should pass.

## Reference

- `features/forseti-jobhunter-application-submission/01-acceptance-criteria.md` (the original shipped feature AC)
- Existing populated suite for reference format: `qa-suites/products/forseti/suite.json` entries with `test_cases`

## Definition of done

- [ ] `01-acceptance-criteria.md` expanded: at least 3 ACs per suite
- [ ] Route paths explicitly listed for ACL suite
- [ ] PHPUnit class/method names referenced for unit suite
- [ ] No stub placeholders remain
- [ ] Committed to HQ repo
