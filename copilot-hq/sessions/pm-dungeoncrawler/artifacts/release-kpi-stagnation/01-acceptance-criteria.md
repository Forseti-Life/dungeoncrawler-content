# Acceptance Criteria (PM-owned)

## Gap analysis reference

> Before writing criteria below, confirm the Gap Analysis in `feature.md` is complete.
> Each criterion must be tagged with its coverage status from the gap analysis:
> - `[NEW]` — no existing code; Dev must build from scratch
> - `[EXTEND]` — existing code path identified; Dev extends or corrects it
> - `[TEST-ONLY]` — implementation exists; QA writes tests against it, no Dev work needed

## Gap analysis reference

Note: This item is a PM process/pipeline health item, not a feature implementation. Tags reflect PM-owned remediation type.

## Happy Path (pipeline unblocked)
- [ ] `[EXTEND]` QA testgen: >= 8 of 12 queued testgen inbox items (qa-dungeoncrawler) produce outbox entries with test plans by end of next cycle. Verify: `ls sessions/qa-dungeoncrawler/outbox/ | grep testgen | wc -l` >= 8.
- [ ] `[EXTEND]` At least 1 dungeoncrawler feature transitions through: dev done → QA APPROVE → feature.md `Status: done`. Verify: `grep -ri "^- Status: done" features/dc-cr-*/feature.md | wc -l` >= 1.
- [ ] `[EXTEND]` QA production audit for dungeoncrawler shows 0 "other failures (4xx/5xx)" in `findings-summary.json` after qa-dungeoncrawler applies the qa-permissions.json fix. Verify: `cat sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.json | grep other_failures` = 0.
- [ ] `[EXTEND]` `dc-cr-character-leveling` feature.md updated to `Status: in_progress` (dev done; awaiting QA verification). Verify: `grep -i "Status" features/dc-cr-character-leveling/feature.md`.

## Edge Cases
- [ ] `[TEST-ONLY]` If testgen executor capacity is the bottleneck, CEO decision on throughput strategy is documented in outbox before next release cycle start.
- [ ] `[EXTEND]` Scoreboard `knowledgebase/scoreboards/dungeoncrawler.md` updated with time-to-verify actual (once first feature reaches done).

## Failure Modes
- [ ] `[TEST-ONLY]` If QA testgen remains at 0 output after 1 cycle: PM escalates to CEO with ROI and "pull from cycle / re-scope QA plan" recommendation.
- [ ] `[TEST-ONLY]` If qa-permissions.json fix is not applied before next release preflight, Gate 2 will BLOCK on 30 false positives. Dev outbox `20260322-193507-qa-findings-dungeoncrawler-30` contains the fix diff.

## Permissions / Access Control
- [ ] Anonymous user behavior: N/A (process item).
- [ ] Authenticated user behavior: N/A (process item).
- [ ] Admin behavior: N/A (process item).

## Data Integrity
- [ ] Feature status drift corrected: `dc-cr-character-leveling` set to `in_progress`.
- [ ] Rollback path: No code changes; all changes are artifact/config updates.

## Knowledgebase check
- `knowledgebase/scoreboards/dungeoncrawler.md` — baseline metrics; time-to-verify N/A needs first completion event.
- `knowledgebase/lessons/` — QA duplicate inbox retry loop (fixed 2026-02-25); qa-permissions.json coverage gaps causing false positives.
