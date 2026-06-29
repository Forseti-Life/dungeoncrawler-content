# Acceptance Criteria — Release KPI Stagnation (2026-03-26 instance)

Note: Pipeline health tracking item. Tags reflect PM-owned remediation type.

## Happy Path (pipeline moving)
- [ ] `[EXTEND]` dc-cr-clan-dagger reaches `done`: QA returns APPROVE for `20260327-004055-unit-test-20260320-impl-dc-cr-clan-dagger`; feature.md status updated to `done`. Verify: `grep -rl "^- Status: done" features/dc-cr-*/feature.md | wc -l` >= 1.
- [ ] `[EXTEND]` CEO testgen decision received and documented. Verify: CEO outbox contains decision OR manual test plans exist in `features/dc-cr-clan-dagger/03-test-plan.md` (already present) plus `features/dc-cr-ancestry-system/03-test-plan.md` and `features/dc-cr-dice-system/03-test-plan.md`.
- [ ] `[EXTEND]` Gate 2 waiver policy documented. Verify: `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` contains Gate 2 waiver section, or `runbooks/shipping-gates.md` updated.
- [ ] `[EXTEND]` Scoreboard updated: `knowledgebase/scoreboards/dungeoncrawler.md` time-to-verify field populated with actual value for dc-cr-clan-dagger.

## Edge Cases
- [ ] `[EXTEND]` If testgen still 0 after CEO decision (this cycle): PM writes manual test plans for `dc-cr-ancestry-system` and `dc-cr-dice-system` as top-2 next features after dc-cr-clan-dagger.
- [ ] `[EXTEND]` If QA returns BLOCK on dc-cr-clan-dagger: PM triages; dev fixes within same cycle.

## Failure Modes
- [ ] `[TEST-ONLY]` If dc-cr-clan-dagger QA unit test is not actioned within one cycle: PM re-escalates to CEO and considers writing a manual verification against drush ev evidence (already in dev outbox).
- [ ] `[TEST-ONLY]` If testgen remains stalled past 2 cycles total: pipeline KPI will show 0 throughput for 14+ days; PM must escalate to Board via CEO.

## Permissions / Access Control
- [ ] N/A — pipeline tracking item.

## Data Integrity
- [ ] No data loss risk. Feature.md status updates are reversible.

## Knowledgebase check
- `knowledgebase/scoreboards/dungeoncrawler.md` — update when dc-cr-clan-dagger reaches done.
- `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation-followup-20260326/` — prior stagnation analysis (same root causes).
