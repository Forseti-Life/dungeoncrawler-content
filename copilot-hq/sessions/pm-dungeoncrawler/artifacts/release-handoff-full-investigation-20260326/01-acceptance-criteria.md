# Acceptance Criteria — Release Handoff Full Investigation (2026-03-26)

Note: This is a PM pipeline investigation and handoff document. Tags reflect PM-owned remediation type.

## Happy Path (handoff complete, 20260326-dungeoncrawler-release-b unblocked)
- [ ] `[EXTEND]` CEO testgen decision documented and actionable: qa-dungeoncrawler either drains the 12-item queue or PM writes manual test plans for top-3 features. Verify: `ls sessions/qa-dungeoncrawler/outbox/ | grep testgen | wc -l` >= 8 OR manual test plans exist in `features/<id>/03-test-plan.md`.
- [ ] `[EXTEND]` qa-permissions.json fix applied (inbox `20260326-222717-fix-qa-permissions-dev-only-routes`). Verify: next production audit `other_failures` count = 0.
- [ ] `[EXTEND]` Gate 2 waiver policy documented in `pm-dungeoncrawler.instructions.md` or `runbooks/shipping-gates.md`. Verify: file contains explicit waiver/block decision for throughput-constrained cycles.
- [ ] `[EXTEND]` pm-forseti signoff gap addressed: either (a) pm-forseti adds signoff retroactively for `20260322-dungeoncrawler-release-b` or (b) CEO documents that orchestrator override is intentional policy. Verify: `scripts/release-signoff-status.sh 20260322-dungeoncrawler-release-b` exits 0 or CEO records explicit exception.
- [ ] `[EXTEND]` `20260326-dungeoncrawler-release-b` scoping started: at least `dc-cr-clan-dagger` at Stage 0 (dev inbox item created with ROI + AC). Verify: dev-dungeoncrawler inbox contains `20260326-*-dc-cr-clan-dagger` item.
- [ ] `[EXTEND]` At least 1 dungeoncrawler feature reaches `done` status by end of `20260326-dungeoncrawler-release-b`. Verify: `grep -rl "^- Status: done" features/dc-cr-*/feature.md | wc -l` >= 1.

## Edge Cases
- [ ] `[EXTEND]` If testgen still 0 after CEO decision (this cycle), PM writes manual test plans for `dc-cr-clan-dagger`, `dc-cr-ancestry-system`, `dc-cr-dice-system` as fallback — top-3 by grooming completeness.
- [ ] `[EXTEND]` dev-dungeoncrawler improvement round items (`20260326-improvement-round-20260322-dungeoncrawler-release-b`, `20260326-improvement-round-20260326-dungeoncrawler-release-b`) actioned before Stage 1 dev work starts.

## Failure Modes
- [ ] `[TEST-ONLY]` pm-forseti signoff gap not addressed → pattern repeats: orchestrator ships coordinated releases with only 1 of 2 PM signoffs.
- [ ] `[TEST-ONLY]` Gate 2 waiver policy not codified → repeated `needs-info` loop on every throughput-constrained release.
- [ ] `[TEST-ONLY]` qa-permissions fix not applied before next preflight → Gate 2 BLOCKs on same 30 false positives (evidence: `20260322-193507`).

## Permissions / Access Control
- [ ] N/A — process investigation, no product code changes.

## Data Integrity
- [ ] No data loss risk. All changes are artifact/config/instruction updates.
- [ ] Rollback: qa-permissions.json change is `git revert` if it introduces regressions.

## Knowledgebase check
- `knowledgebase/scoreboards/dungeoncrawler.md` — update when first feature reaches `done`.
- `knowledgebase/lessons/` — QA permissions coverage gap lesson to be written after fix applied.
- Prior stagnation artifacts: `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation-followup-20260326/`.
