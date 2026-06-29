# Problem Statement (PM-owned)

## Context
- What is changing? This is a follow-up to `20260322-release-kpi-stagnation` (artifacts: `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation/`). As of 2026-03-26, the three root causes identified on 2026-03-22 remain: (1) QA testgen throughput: 12 items still queued in qa-dungeoncrawler inbox since 2026-03-20, 0 test plans returned in 6 days; (2) QA production audit false positives: 30 "other failures" in run `20260322-193507` from dev-only module 404s — a QA inbox item `20260326-222717-fix-qa-permissions-dev-only-routes` (ROI=9) was created on 2026-03-26 but not yet actioned; (3) Feature status drift: corrected on 2026-03-23 (dc-cr-character-leveling set to `in_progress`). Additionally, a new gap was surfaced: GAP-DC-B-01 — `dc-cr-ancestry-traits` and `dc-cr-character-leveling` shipped without QA APPROVE signals; Gate 2 waiver policy decision is pending from CEO.
- Why now? Six days into the testgen stall with no movement. The pipeline is producing zero feature completions and the CEO escalation has no recorded response.

## Goals (Outcomes)
- CEO provides testgen throughput decision (drain/batch/manual bypass) to unblock QA verification pipeline.
- QA applies qa-permissions.json fix (30 false positives resolved) before next release preflight.
- CEO confirms Gate 2 waiver policy for throughput-constrained cycles.
- At least 1 dungeoncrawler feature reaches status `done` (dev-complete + QA APPROVE) within the next release cycle.

## Non-Goals (Explicitly out of scope)
- Deferred features (24 features) — no changes this cycle.
- New feature scoping beyond what is already `ready`/`in_progress`.
- Forseti pipeline.

## Users / Personas
- PM-dungeoncrawler: needs pipeline unblocked and clear gate policy.
- QA-dungeoncrawler: needs testgen throughput decision and qa-permissions fix confirmed.
- Dev-dungeoncrawler: needs clean QA audit signal and test plans to verify against.

## Constraints
- Security: none for this item.
- Performance: QA audits must run against localhost:8080.
- Accessibility: N/A.
- Backward compatibility: N/A.

## Success Metrics
- CEO testgen decision received and documented within next execution cycle.
- QA inbox item `20260326-222717-fix-qa-permissions-dev-only-routes` completed: production audit shows 0 "other failures".
- Gate 2 waiver policy documented in pm-dungeoncrawler.instructions.md or runbooks.
- At least 1 feature transitions to `done` status.

## Dependencies
- CEO: testgen throughput decision (6 days pending).
- CEO: Gate 2 waiver policy decision.
- qa-dungeoncrawler: qa-permissions.json fix (inbox item exists, ROI=9).

## Risks
- If CEO testgen decision is not received before Stage 0 of `20260326-dungeoncrawler-release-b`, no features beyond `dc-cr-clan-dagger` can be verified, and the release will ship at minimum scope.
- If qa-permissions.json fix is not applied before next release preflight, Gate 2 will BLOCK on 30 false positives again.

## Knowledgebase check
- `knowledgebase/scoreboards/dungeoncrawler.md` — baseline 2026-02-27; time-to-verify still N/A; no features done yet.
- `knowledgebase/lessons/` — QA duplicate inbox retry loop (fixed 2026-02-25); qa-permissions.json coverage gaps (recurring, now mitigated with explicit handoff).
