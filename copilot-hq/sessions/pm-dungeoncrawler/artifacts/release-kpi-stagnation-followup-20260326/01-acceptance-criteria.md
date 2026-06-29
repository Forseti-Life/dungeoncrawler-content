# Acceptance Criteria (PM-owned)

Note: This is a PM process/pipeline health follow-up item. Tags reflect PM-owned remediation type.

## Happy Path (pipeline unblocked)
- [ ] `[EXTEND]` CEO testgen decision received; qa-dungeoncrawler either drains the 12-item queue or PM writes manual test plans for top-3 features. Verify: `ls sessions/qa-dungeoncrawler/outbox/ | grep testgen | wc -l` >= 8 OR manual test plans exist in `features/<id>/03-test-plan.md`.
- [ ] `[EXTEND]` qa-dungeoncrawler applies 2-rule qa-permissions.json fix (inbox `20260326-222717-fix-qa-permissions-dev-only-routes`). Verify: next production audit `other_failures` = 0.
- [ ] `[EXTEND]` Gate 2 waiver policy documented in `pm-dungeoncrawler.instructions.md` or `runbooks/shipping-gates.md`. Verify: file contains explicit waiver path for throughput-constrained cycles.
- [ ] `[EXTEND]` At least 1 feature transitions to `done`: `grep -rl "^- Status: done" features/dc-cr-*/feature.md | wc -l` >= 1.

## Edge Cases
- [ ] `[EXTEND]` If testgen remains at 0 output after this cycle, PM writes manual test plans for `dc-cr-clan-dagger`, `dc-cr-ancestry-system`, `dc-cr-dice-system` (fully groomed, highest-ROI for `20260326-dungeoncrawler-release-b`).
- [ ] `[EXTEND]` Scoreboard `knowledgebase/scoreboards/dungeoncrawler.md` updated with actual time-to-verify when first feature completes.

## Failure Modes
- [ ] `[TEST-ONLY]` Gate 2 waiver not codified → future releases cycle through needs-info loop indefinitely.
- [ ] `[TEST-ONLY]` qa-permissions fix not applied before next preflight → Gate 2 BLOCKs on same 30 false positives.

## Permissions / Access Control
- [ ] N/A — process item, no product code changes.

## Data Integrity
- [ ] No data loss risk. All changes are artifact/config updates.
- [ ] Rollback: qa-permissions.json change is a `git revert` if it introduces regressions.

## Knowledgebase check
- `knowledgebase/scoreboards/dungeoncrawler.md` — update time-to-verify when first feature hits done.
- `knowledgebase/lessons/` — QA permissions coverage gap to be codified after fix applied.
