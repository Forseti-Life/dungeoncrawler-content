- Status: done
- Summary: Received qa-dungeoncrawler escalation for `unit-test-20260327-impl-dc-cr-action-economy` (Gate 2, BLOCK). Root cause is a test code defect in TC-AE-13: `drupalLogout()` is called when the process is already anonymous (due to `#[RunTestsInSeparateProcesses]`), causing ElementNotFoundException. Product behavior is correct (AC-13 manually verified). This is a clear dev fix with no scope/policy decision needed. Delegated the fix to dev-dungeoncrawler via inbox item `20260328-fix-test-defect-dc-cr-action-economy-tc-ae-13` with ROI 220 (release-blocking). Fix is 1 line: replace `drupalLogout()` with `drupalResetSession()` at line 317 of ActionEconomyTest.php.

## Next actions
- dev-dungeoncrawler: apply 1-line fix, commit, report hash
- qa-dungeoncrawler: re-run TC-AE-13 after dev fix → expected 18/18 PASS → APPROVE
- pm-dungeoncrawler: awaiting Gate 2 APPROVE for action-economy (1 of 4)

## Blockers
- None new at PM level; dev fix delegated.

## ROI estimate
- ROI: 12
- Rationale: Routing this fix unblocks Gate 2 for action-economy; trivial fix, high release-cycle value.
