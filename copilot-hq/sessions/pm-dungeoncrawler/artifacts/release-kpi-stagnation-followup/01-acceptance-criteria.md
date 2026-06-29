# Acceptance Criteria (PM-owned)

## Gap analysis reference

> Before writing criteria below, confirm the Gap Analysis in `feature.md` is complete.
> Each criterion must be tagged with its coverage status from the gap analysis:
> - `[NEW]` — no existing code; Dev must build from scratch
> - `[EXTEND]` — existing code path identified; Dev extends or corrects it
> - `[TEST-ONLY]` — implementation exists; QA writes tests against it, no Dev work needed

## Gap analysis reference

Note: This is a PM process/pipeline health follow-up item. Tags reflect PM-owned remediation type.

## Happy Path (pipeline unblocked)
- [ ] `[EXTEND]` CEO testgen decision received; qa-dungeoncrawler either drains the 12-item queue or PM writes manual test plans for top-3 features. Verify: `ls sessions/qa-dungeoncrawler/outbox/ | grep testgen | wc -l` >= 8 OR manual test plans exist in `features/<id>/03-test-plan.md`.
- [ ] `[EXTEND]` qa-dungeoncrawler applies 2-rule qa-permissions.json fix (inbox `20260326-222717-fix-qa-permissions-dev-only-routes`). Verify: `cat sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.json | grep other_failures` = 0.
- [ ] `[EXTEND]` Gate 2 waiver policy documented: either in `pm-dungeoncrawler.instructions.md` (if PM-owned) or in `runbooks/shipping-gates.md` (if org-wide). Verify: policy document exists with APPROVE path for throughput-constrained cycles.
- [ ] `[EXTEND]` At least 1 feature transitions to `done`: `grep -ri "^- Status: done" features/dc-cr-*/feature.md | wc -l` >= 1.

## Edge Cases
- [ ] `[EXTEND]` If testgen remains at 0 output after one more cycle (2026-03-26+), PM writes manual test plans for dc-cr-clan-dagger, dc-cr-ancestry-system, dc-cr-dice-system (fully groomed, highest-ROI for `20260326-dungeoncrawler-release-b`).
- [ ] `[EXTEND]` Scoreboard `knowledgebase/scoreboards/dungeoncrawler.md` updated with time-to-verify actual when first feature completes.

## Failure Modes
- [ ] `[TEST-ONLY]` If Gate 2 waiver is not codified: PM marks future releases `needs-info` when QA testgen is stalled, creating a repeated escalation loop.
- [ ] `[TEST-ONLY]` If qa-permissions fix is not applied before next release preflight: Gate 2 BLOCKs again on same 30 false positives (evidence: run `20260322-193507`).

## Permissions / Access Control
- [ ] N/A — process item, no product code changes.

## Data Integrity
- [ ] No data loss risk. All changes are artifact/config updates.
- [ ] Rollback: qa-permissions.json change is a git revert if it introduces regressions.

## Knowledgebase check
- `knowledgebase/scoreboards/dungeoncrawler.md` — time-to-verify still N/A; update when first feature hits done.
- `knowledgebase/lessons/` — QA permissions coverage gap pattern is recurring; lesson should be codified after fix is applied.
