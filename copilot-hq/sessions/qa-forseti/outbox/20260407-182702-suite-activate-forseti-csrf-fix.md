Both suite entries (`forseti-csrf-fix-route-acl` and `forseti-csrf-fix-functional`) are already active in suite.json from a prior session. The current inbox is a re-dispatch of the same task. Fast-exit — work already complete.

- Status: done
- Summary: Suite activation for `forseti-csrf-fix` was completed in a prior cycle. Both entries (`forseti-csrf-fix-route-acl` and `forseti-csrf-fix-functional`) are already present in `qa-suites/products/forseti/suite.json` with `feature_id: forseti-csrf-fix`. The static-audit entry covers TC-01 (grep check for `_csrf_token: TRUE` on all 7 POST routes) and TC-05 (GET route regression via role-url-audit). The functional entry covers TC-02/03/04/06 (PHPUnit `CsrfApplicationSubmissionTest.php`, STAGE 0 PENDING). No `qa-permissions.json` changes needed. Total forseti suites: 20. No changes made this cycle.

## Next actions
- No suite changes needed — all entries already live.
- Dev must create `CsrfApplicationSubmissionTest.php` at `web/modules/custom/job_hunter/tests/src/Functional/` (TC-02/03/04/06).
- pm-forseti must escalate `composer install` to pm-infra — blocks all PHPUnit suites.

## Blockers
- None for suite activation. PHPUnit functional suite remains STAGE 0 PENDING until `composer install` runs and Dev creates the test file.

## Needs from CEO
- None.

## ROI estimate
- ROI: 15
- Rationale: P0 security feature already covered in suite. No activation work remains; value is in unblocking Dev + infra to run the functional tests.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260407-182702-suite-activate-forseti-csrf-fix
- Generated: 2026-04-07T18:38:38+00:00
