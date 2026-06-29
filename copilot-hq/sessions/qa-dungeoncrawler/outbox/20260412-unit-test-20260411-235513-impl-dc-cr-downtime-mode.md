Site audit is clean. Now commit the code changes and update the regression checklist:

Now update the regression checklist to mark dc-cr-downtime-mode as APPROVE:

Both commits done. Now write the outbox:

- Status: done
- Summary: Targeted QA verification for dc-cr-downtime-mode (AC-005: subsist, treat_disease, run_business) is complete with APPROVE verdict. Dev commit `96f4ddb18` implementation verified in DowntimePhaseHandler.php — all three methods present, all three in getLegalIntents() and processIntent() switch. Two defects found and fixed by QA: (1) DowntimePhaseHandlerTest.php had constructor arity mismatch at 5 call sites (NpcPsychologyService 5th arg missing, causing 9 test errors); (2) processIntent() was silently swallowing success=FALSE from processEarnIncome, processTreatDisease, and processRunBusiness — callers always received success=TRUE regardless of inner failure. Both fixed in QA commit `a1a19de0f`. Added 6 new AC-005 unit tests (subsist success/crit-success/failure, treat_disease success/missing-affliction-id, run_business success). Final test run: 15/15 pass. Site URL audit 20260412-055009: 0 violations, 0 failures, 0 permission expectation violations, config drift clean. Regression checklist updated and committed (`8bf054d61`).

## Next actions
- No new Dev items identified — existing tests now cover AC-005 happy path and failure modes.
- PM may proceed to release gate for dc-cr-downtime-mode.
- Remaining inbox: suite activation items for dc-cr-feats-ch05, dc-cr-hazards, and targeted unit tests for dc-cr-magic-ch11, dc-cr-rest-watch-starvation, dc-cr-skills-society-create-forgery, dc-cr-skills-survival-track-direction.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 40
- Rationale: Fixing the error-propagation bug is a correctness regression that would affect any caller checking success=FALSE on earn_income/treat_disease/run_business — release-blocking if shipped. All 15 tests passing means the feature is releasable; downtime-mode is a core gameplay loop.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260412-unit-test-20260411-235513-impl-dc-cr-downtime-mode
- Generated: 2026-04-12T05:53:33+00:00
