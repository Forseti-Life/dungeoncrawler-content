# Problem Statement — Release KPI Stagnation (2026-03-26 instance)

## Context
- What is changing? This is a 2026-03-26 re-queue of the KPI stagnation tracking item (original: `20260322-release-kpi-stagnation`, artifacts: `sessions/pm-dungeoncrawler/artifacts/20260322-release-kpi-stagnation/` or `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation/`). As of 2026-03-27 01:02, the pipeline state has improved marginally: GAP-DC-B-02 (30 qa-audit false positives) is confirmed resolved (run `20260326-203507` shows 0 other failures). `dc-cr-clan-dagger` entered Stage 0: feature.md updated `ready` → `in_progress`, QA unit test delegated (`20260327-004055-unit-test-20260320-impl-dc-cr-clan-dagger`, ROI=8). However, the testgen stall continues: 12 items queued since 2026-03-20, 0 test plans returned (day 7). Two CEO decisions remain outstanding.
- Why now? This re-queue captures updated state. The pipeline is moving for the first time (dc-cr-clan-dagger in Stage 0), but 4 other `ready` features remain blocked on testgen throughput.

## Current Pipeline State
- Features: 11 in_progress, 4 ready, 0 done, 24 deferred
- `dc-cr-clan-dagger`: in_progress — dev done (commits `5bc95ffe4`, `efc7eef2a`), QA unit test delegated
- QA testgen: 12 items queued since 2026-03-20, 0 test plans returned (day 7)
- Site audit: PASS (run `20260326-203507`) — 0 violations, 0 other failures ✓ resolved
- CEO escalation: 2 decisions pending (testgen path, Gate 2 waiver policy)

## Goals (Outcomes)
- dc-cr-clan-dagger reaches `done` (dev-complete + QA APPROVE) within `20260326-dungeoncrawler-release-b`.
- CEO provides testgen path decision — unblocks `dc-cr-action-economy`, `dc-cr-ancestry-system`, `dc-cr-dice-system`, `dc-cr-difficulty-class` (all `ready`, pending test plans).
- Gate 2 waiver policy documented (CEO decision).
- Scoreboard `knowledgebase/scoreboards/dungeoncrawler.md` updated when first feature reaches `done`.

## Non-Goals (Explicitly out of scope)
- Deferred features (24 features).
- New feature scoping.
- Forseti pipeline.

## Users / Personas
- pm-dungeoncrawler: needs first feature to reach `done` and pipeline to move.
- qa-dungeoncrawler: needs testgen decision and test suite activation for dc-cr-clan-dagger.
- dev-dungeoncrawler: waiting for QA verify signal.

## Constraints
- Security: none for this item.
- Performance: QA audits at localhost:8080.
- Accessibility: N/A.
- Backward compatibility: N/A.

## Success Metrics
- `grep -rl "^- Status: done" features/dc-cr-*/feature.md | wc -l` >= 1
- CEO testgen decision received and documented.
- Scoreboard updated with actual time-to-verify for dc-cr-clan-dagger.

## Dependencies
- qa-dungeoncrawler: action `20260327-004055-unit-test-20260320-impl-dc-cr-clan-dagger` (ROI=8).
- CEO: testgen throughput decision (GAP-DC-01, day 7).
- CEO: Gate 2 waiver policy (GAP-DC-B-01).

## Risks
- Even with dc-cr-clan-dagger progressing, 4 other ready features remain stuck without testgen output.
- If first `done` takes more than one release cycle, pipeline throughput KPI will remain at 0 indefinitely.

## Knowledgebase check
- Prior stagnation artifacts: `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation-followup-20260326/`
- Handoff investigation: `sessions/pm-dungeoncrawler/artifacts/release-handoff-full-investigation-20260326/`
