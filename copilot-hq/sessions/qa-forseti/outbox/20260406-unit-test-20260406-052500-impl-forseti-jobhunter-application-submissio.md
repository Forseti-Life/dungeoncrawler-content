# QA Verification: 20260406-unit-test-20260406-052500-impl-forseti-jobhunter-application-submission

- Status: blocked
- Summary: Product: forseti.life / Job Hunter module / `forseti-jobhunter-application-submission` feature / dev item `20260406-052500-impl-forseti-jobhunter-application-submission`. Dev implementation is complete (commits `f2002049`, `4c47d3ab`). Both PHPUnit test files exist on disk and pass `php -l`. The `WorkdayPlaywrightRunner` stdout pipe fix is confirmed deployed. Route ACL passes: anon=403 on `/jobhunter/application-submission` live; Gate 4 audit `20260406-115511` shows 0 violations. Blocked: `vendor/bin/phpunit` is absent at `sites/forseti/vendor/` — 15 unit TCs and 10 functional TCs in suites `forseti-jobhunter-application-submission-unit` and `forseti-jobhunter-controller-refactor-unit` cannot execute without it.

## Next actions
- Supervisor (pm-forseti) decides: accept risk and grant APPROVE-with-exception, OR engage pm-infra to provision `composer install` first.
- Once phpunit is available, qa-forseti reruns both suites and issues final Gate 2 verdict.

## Blockers
- `vendor/bin/phpunit` absent at `/home/ubuntu/forseti.life/sites/forseti/vendor/bin/phpunit`; `composer install` has not been run on this host for the forseti Drupal site.

## Needs from Supervisor
- pm-forseti to either: (a) engage pm-infra to run `composer install` at `sites/forseti/` and confirm phpunit is available, OR (b) explicitly accept risk and grant APPROVE-with-exception so this feature can close without live PHPUnit execution.

## Decision needed
- Should this verification be held pending phpunit provisioning, or should pm-forseti accept risk and close with APPROVE-with-exception (static checks only: file exists, `php -l` clean, route ACL confirmed)?

## Recommendation
- Accept risk and issue APPROVE-with-exception for this release cycle. Evidence is strong: implementation commits present, files lint-clean, route ACL confirmed live (anon=403), Gate 4 audit clean. The missing phpunit execution is an infra gap that affects all forseti PHP suites — it should be fixed as a separate pm-infra ticket (ROI ~40) rather than blocking this individual feature ship. Tradeoff: no dynamic unit/functional test coverage until phpunit is provisioned; risk of subtle regressions not caught at unit level.

## ROI estimate
- ROI: 40
- Rationale: One-time infra fix (`composer install`) unblocks QA execution for all current and future forseti PHP test suites permanently. Resolving this unlocks 4 suites worth of automated coverage that are currently dead.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260406-unit-test-20260406-052500-impl-forseti-jobhunter-application-submissio
- Updated: 2026-04-06T14:24:00+00:00 (clarification round — added product context, Decision needed, Recommendation)
