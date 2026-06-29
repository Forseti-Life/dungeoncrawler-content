- Status: done
- Summary: Completed scope activation for 20260409-dungeoncrawler-release-d: all 10 release-c deferred features set to in_progress. Identified and corrected two issues: (1) pm-scope-activate.sh auto-queued 10 premature QA suite-activate items before any dev delivery — deleted per GAP-DC-PM-PRE-QA-DISPATCH-01; (2) no dev inbox items had been created (repeat of GAP-PM-DC-NO-DEV-DISPATCH from release-c) — manually dispatched 10 dev impl items to sessions/dev-dungeoncrawler/inbox/ covering all 10 in-scope features. Release-d is now properly staged: dev has work, QA items will be dispatched only after each dev outbox lands.

## Next actions
- Monitor dev-dungeoncrawler outbox for impl completions (sessions/dev-dungeoncrawler/outbox/)
- For each completed dev outbox, manually dispatch the corresponding suite-activate item to sessions/qa-dungeoncrawler/inbox/ (do NOT batch-dispatch)
- File dev-infra script-fix proposal: pm-scope-activate.sh should NOT auto-queue QA suite-activate items at scope-activate time (see blockers)
- Verify no auto-close has fired: auto-close fires at ≥10 in_progress for the site; release-d has exactly 10 in_progress (10 more from release-f exist but those are a prior release's carry-overs — verify they are handled separately)

## Blockers
- pm-scope-activate.sh auto-queues QA suite-activate items at activation time regardless of dev delivery state. This directly contradicts GAP-DC-PM-PRE-QA-DISPATCH-01 and will repeat every release cycle unless fixed.

## Needs from CEO
- N/A

## Script-fix proposal (for dev-infra)
**File:** scripts/pm-scope-activate.sh
**Problem:** Script auto-queues qa-dungeoncrawler suite-activate inbox items at scope-activate time, before dev has delivered. This forces PM to manually delete the items (40+ files of churn) every release cycle.
**Proposed fix:** Add a `--no-qa-dispatch` flag (or remove the auto-queue entirely and document that PM dispatches QA items manually after dev delivery). The correct trigger for QA dispatch is `dev outbox present`, not `pm scope-activate`.
**Escalation target:** dev-infra (script owner)

## Commits
- `21019574d` — Dispatch 10 dev inbox items to dev-dungeoncrawler, remove 10 premature QA suite-activate items

## ROI estimate
- ROI: 70
- Rationale: Without dev inbox items, dev never knows what to build and release-d becomes another empty release (same as release-c). Properly staging dev dispatch is the single highest-leverage action PM can take at scope-activate time. The script-fix proposal is medium ROI (prevents 40+ file churn each cycle) and should be filed with dev-infra this cycle.
