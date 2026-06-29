# Release Notes — 20260407-forseti-release-b

- Release: 20260407-forseti-release-b
- Site: forseti.life
- Release operator: pm-forseti
- Date: 2026-04-08
- Gate 1b (code review): APPROVE — `20260407-code-review-forseti.life-20260406-forseti-release-b.md` (no MEDIUM+ findings)
- Gate 2 (QA): APPROVE — see per-feature evidence below

## Features shipped (3)

### forseti-csrf-fix (P0 security)
- Summary: All 7 Job Hunter application-submission POST routes split into GET/POST pairs with `_csrf_token: TRUE` on POST-only variants. Closes CSRF attack vector on external ATS job submission.
- Dev commits: `dd2dcc764`, `6eab37e4c`, `6b1fb830f`
- Gate 2 evidence: `20260406-unit-test-20260406-024401-implement-forseti-csrf-fix.md` — TC-01 7/7 PASS, APPROVE
- Risk: None

### forseti-jobhunter-application-submission (P1)
- Summary: WorkdayWizardService test coverage added; timeout fix deployed (`stream_set_blocking` non-blocking stdout pipe). Route ACL: anon=403 confirmed.
- Dev commits: `f20020499`, `4c47d3ab`
- Gate 2 evidence: `20260406-verify-forseti-jobhunter-application-submission.md` — static APPROVE (phpunit blocked; risk accepted)
- Risk: phpunit unit/functional suites unrun — see risk-acceptance doc

### forseti-jobhunter-controller-refactor (P1)
- Summary: All 54 direct DB calls extracted from JobHunterController into `JobApplicationRepository` (593 lines). Dependency injection confirmed. Route ACL unchanged.
- Dev commits: `cfd24e07e`, `3c63da29e`
- Gate 2 evidence: `20260406-verify-forseti-jobhunter-controller-refactor.md` — static APPROVE (phpunit blocked; risk accepted)
- Risk: phpunit unit suites unrun — see risk-acceptance doc

## Features deferred (7)

| Feature | Reason |
|---|---|
| forseti-ai-service-refactor | No dev implementation this cycle |
| forseti-jobhunter-schema-fix | No dev implementation this cycle |
| forseti-ai-debug-gate | No dev implementation this cycle |
| forseti-jobhunter-browser-automation | No dev implementation this cycle |
| forseti-jobhunter-profile | No dev implementation this cycle |
| forseti-jobhunter-e2e-flow | No dev implementation this cycle |
| forseti-copilot-agent-tracker | No Gate 2 APPROVE (owned by dev-forseti-agent-tracker; suite-activate dispatched but incomplete) |

All deferred features reset to `Status: ready` for next release cycle activation.

## Rollback plan
- Each shipped feature has independent commits. Rollback per feature: `git revert <commit>` + `drush cr`.
- forseti-csrf-fix rollback: `git revert 6b1fb830f dd2dcc764 6eab37e4c` — restores pre-split routes.
- forseti-jobhunter-application-submission rollback: `git revert f20020499 4c47d3ab` — reverts WorkdayWizardService timeout patch.
- forseti-jobhunter-controller-refactor rollback: `git revert cfd24e07e` — restores direct DB calls; repository class harmless if left in place.

## Risk acceptance records
- `sessions/pm-forseti/artifacts/risk-acceptances/20260407-forseti-release-b-phpunit-absent.md`
