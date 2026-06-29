# QA Verification — forseti-jobhunter-application-submission

- Status: blocked
- Summary: Dev implementation is complete (commits `f2002049`, `4c47d3ab`). Both test files are confirmed on disk with no syntax errors — `WorkdayWizardServiceTest.php` (15 unit TCs) and `ApplicationSubmissionRouteTest.php` (10 functional TCs). The `WorkdayPlaywrightRunner` stdout non-blocking pipe fix (`stream_set_blocking($pipes[1], FALSE)` at line 68) is confirmed deployed. Route ACL passes: live production returns anon=403 on `/jobhunter/application-submission` and Gate 4 audit `20260406-115511` shows 0 violations. However, PHPUnit cannot be executed — `vendor/bin/phpunit` is absent from `sites/forseti/vendor/` and no `composer install` alternative is available on this host. Unit and functional test suites remain unrun.

## What passed (static / live checks)

| Check | Result |
|---|---|
| `php -l WorkdayWizardServiceTest.php` | PASS — no syntax errors |
| `php -l ApplicationSubmissionRouteTest.php` | PASS — no syntax errors |
| `WorkdayPlaywrightRunner` stdout pipe fix deployed | PASS — `stream_set_blocking($pipes[1], FALSE)` confirmed at line 68 |
| Route ACL: `GET /jobhunter/application-submission` anon | PASS — HTTP 403 |
| Gate 4 audit `20260406-115511` | PASS — 0 violations, 0 missing assets |

## What is blocked (needs phpunit)

| Test file | TCs | Status |
|---|---|---|
| `WorkdayWizardServiceTest.php` | 15 unit | BLOCKED — phpunit absent |
| `ApplicationSubmissionRouteTest.php` | 10 functional | BLOCKED — phpunit absent |

## Next actions
- pm-infra provisions `vendor/` via `composer install` at `/home/ubuntu/forseti.life/sites/forseti/` (or equivalent CI runner path)
- After provision: run unit suite:
  ```
  vendor/bin/phpunit web/modules/custom/job_hunter/tests/src/Unit/Service/WorkdayWizardServiceTest.php --testdox
  ```
- After provision: run functional suite (requires running Drupal + DB):
  ```
  vendor/bin/phpunit web/modules/custom/job_hunter/tests/src/Functional/Controller/ApplicationSubmissionRouteTest.php --testdox
  ```
- If all 25 TCs green → write Gate 2 APPROVE for this feature and close suite entries

## Blockers
- `vendor/bin/phpunit` absent at `/home/ubuntu/forseti.life/sites/forseti/vendor/`
- `composer install` is not executable on this host (times out / not provisioned)
- This is a known infra limitation, previously documented in dev seat instructions

## Needs from Supervisor
- pm-forseti: escalate to pm-infra to provision `composer install` (with phpunit) on the forseti site's dev/test working copy at `/home/ubuntu/forseti.life/sites/forseti/`
- Once phpunit is available, route a follow-on verification inbox item to qa-forseti

## Decision needed
- Who owns provisioning `vendor/` for the forseti site's dev/test path — pm-infra (preferred) or dev-forseti running composer manually?

## Recommendation
- Assign to pm-infra as an infra task: `composer install --no-dev` is insufficient; full dev dependencies needed for phpunit. pm-infra should run `composer install` at `/home/ubuntu/forseti.life/sites/forseti/` and confirm `vendor/bin/phpunit` is present. This unblocks QA for all future forseti unit test suites, not just this one feature.

## ROI estimate
- ROI: 40
- Rationale: Unblocking phpunit provision is a one-time infra fix that unblocks QA test runs for all current and future forseti features. Without it, every unit/functional test suite entry in suite.json is permanently untestable, making the QA gate meaningless for PHP test coverage. High leverage relative to effort.
