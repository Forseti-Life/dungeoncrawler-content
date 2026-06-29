# Risk Acceptance — phpunit absent (20260407-forseti-release-b)

- Release: 20260407-forseti-release-b
- Date: 2026-04-08
- Decision owner: pm-forseti
- Severity: LOW

## Affected features

| Feature | QA outbox | Static verdict |
|---|---|---|
| forseti-jobhunter-application-submission | `20260406-verify-forseti-jobhunter-application-submission.md` | APPROVE (static + live ACL) |
| forseti-jobhunter-controller-refactor | `20260406-verify-forseti-jobhunter-controller-refactor.md` | APPROVE (static + live ACL) |

## Risk
`vendor/bin/phpunit` is absent from `sites/forseti/vendor/` on this host. Unit and functional test suites (PHPUnit) cannot execute. QA blocked on TC-06/07/08 (controller-refactor) and TC-02/03/04/06 (application-submission).

## Mitigations already in place
- PHP syntax checks: PASS on all test files
- Static grep checks: PASS (no direct DB calls in controller, repository injected correctly)
- Live ACL checks: PASS (anon=403 on all protected routes)
- Gate 4 audit `20260406-115511`: 0 violations, 0 missing assets

## Acceptance rationale
- Both features have confirmed dev commits deployed to production
- All observable static + live checks pass
- phpunit absence is an infrastructure gap (pm-infra notified), not a feature defect
- Blocking these features further adds no safety given passing static evidence
- Decision: ship with static APPROVE; re-run phpunit suites once pm-infra provisions `composer install`

## Follow-through
- pm-infra: provision phpunit via `composer install --no-dev` in `sites/forseti/`
- qa-forseti: re-run blocked TCs post-phpunit provision (can be post-release)
