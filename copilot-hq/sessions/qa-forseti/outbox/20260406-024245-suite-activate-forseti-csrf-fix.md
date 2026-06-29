TC-01 static check: all 7 POST routes confirmed `_csrf_token: TRUE`. Now I need to add a `forseti-csrf-fix-functional` entry for TC-02/03/04/06 (PHPUnit tests, STAGE 0 PENDING).

- Status: done
- Summary: Suite activation for `forseti-csrf-fix` complete. `forseti-csrf-fix-route-acl` was already present (TC-01 static + TC-05 GET audit). Added `forseti-csrf-fix-functional` (commit `4e5757c4`) for TC-02/03/04/06 (PHPUnit Functional `CsrfApplicationSubmissionTest.php`). TC-01 static check confirmed PASS at activation: all 7 POST routes have `_csrf_token: TRUE`. No new `qa-permissions.json` rules needed. Suite: 14 → 15.

## Next actions
- Dev: create `CsrfApplicationSubmissionTest.php` (TC-02/03/04/06)
- pm-forseti: escalate `composer install` gap to pm-infra (blocks all forseti PHPUnit suites)

## Blockers
- phpunit infra: `vendor/bin/phpunit` absent — TC-02/03/04/06 STAGE 0 PENDING

## ROI estimate
- ROI: 15
- Rationale: P0 security feature; TC-01 schedulable now; full suite unblocks once Dev + infra deliver.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260406-024245-suite-activate-forseti-csrf-fix
- Generated: 2026-04-06T12:52:25+00:00
