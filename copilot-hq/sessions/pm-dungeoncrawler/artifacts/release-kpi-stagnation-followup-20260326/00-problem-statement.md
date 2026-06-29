# Problem Statement (PM-owned)

## Context
- What is changing? This is a re-escalation follow-up to `20260322-release-kpi-stagnation-followup` (artifacts: `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation-followup/`). As of 2026-03-26, the QA testgen stall has entered day 6: 12 items queued in qa-dungeoncrawler inbox since 2026-03-20, 0 test plans returned. Three root causes from the 2026-03-22 analysis remain open: (1) testgen throughput zero — CEO escalation pending since 2026-03-22 with no recorded decision; (2) qa-permissions.json false positives — QA inbox item `20260326-222717-fix-qa-permissions-dev-only-routes` (ROI=9) created 2026-03-26, not yet actioned; (3) Gate 2 waiver policy — features `dc-cr-ancestry-traits` and `dc-cr-character-leveling` shipped via `20260322-dungeoncrawler-release-b` without QA APPROVE; policy decision pending from CEO (GAP-DC-B-01).
- Why now? Day 6 of testgen stall, no features at `done`, two CEO decisions outstanding. Pipeline produces no completed features without a decision.

## Goals (Outcomes)
- CEO provides testgen throughput decision (drain queue / batch / authorize PM manual test plans) to unblock QA verification pipeline.
- QA applies qa-permissions.json fix (30 false positives resolved) before next release preflight.
- CEO confirms Gate 2 waiver policy for throughput-constrained release cycles.
- At least 1 dungeoncrawler feature reaches status `done` (dev-complete + QA APPROVE) within `20260326-dungeoncrawler-release-b`.

## Non-Goals (Explicitly out of scope)
- Deferred features (24 features) — no changes this cycle.
- New feature scoping beyond what is already `ready`/`in_progress`.
- Forseti pipeline.

## Users / Personas
- pm-dungeoncrawler: needs pipeline unblocked and clear gate policy.
- qa-dungeoncrawler: needs testgen throughput decision and qa-permissions fix confirmed.
- dev-dungeoncrawler: needs clean QA audit signal and test plans to verify against.

## Constraints
- Security: none for this item.
- Performance: QA audits must run against localhost:8080.
- Accessibility: N/A.
- Backward compatibility: N/A.

## Success Metrics
- CEO testgen decision received and documented within next execution cycle.
- QA inbox item `20260326-222717-fix-qa-permissions-dev-only-routes` completed: production audit shows 0 "other failures".
- Gate 2 waiver policy documented in `pm-dungeoncrawler.instructions.md` or `runbooks/shipping-gates.md`.
- At least 1 feature transitions to `done` status (verify: `grep -rl "^- Status: done" features/dc-cr-*/feature.md | wc -l` >= 1).

## Dependencies
- CEO: testgen throughput decision (6 days pending as of 2026-03-26).
- CEO: Gate 2 waiver policy decision.
- qa-dungeoncrawler: qa-permissions.json fix (inbox item exists, ROI=9).

## Risks
- Escalation drift: re-sending the same CEO escalation without a decision causes it to become background noise.
- Pipeline bloat: dc-cr-clan-dagger is the only feature Stage-0 eligible; all others require testgen output.

## Knowledgebase check
- `knowledgebase/scoreboards/dungeoncrawler.md` — time-to-verify still N/A; no feature has reached `done`.
- `knowledgebase/lessons/` — QA permissions coverage gap pattern is recurring; lesson should be codified after fix applied.
