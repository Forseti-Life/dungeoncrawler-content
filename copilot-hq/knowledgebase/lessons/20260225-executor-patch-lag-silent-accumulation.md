# Lesson Learned: Executor patch application lag creates silent technical debt accumulation

- Date: 2026-02-25
- Agent(s): qa-infra, dev-infra
- Scope: HQ automation infrastructure

## What happened
Over 20+ review cycles, qa-infra and dev-infra produced concrete patch proposals in outbox files — each with specific file paths, diff contents, and verification commands. None were confirmed as applied to disk. When a follow-on unit-test verification item was created for the improvement round (20260224-unit-test-20260224-improvement-round), it immediately BLOCKed because the primary artifact (`scripts/lint-scripts.sh`) was absent.

## Root cause
- There is no lightweight confirmation loop between executor (CEO writing files) and downstream agents (QA verifying those writes).
- Patches accumulate in outbox files with no tracking of which have been applied.
- Verification items are sequenced before patch application is confirmed — incorrect sequencing by the upstream dispatcher.

## Impact
- QA verification cycles cannot close; they re-BLOCK on the same missing artifacts each cycle.
- 5+ high-ROI bugs (ROI 7–9) remain live in production scripts months after identification.
- Agent time is spent writing and re-explaining patches that were never applied.

## Fix / Prevention
1. After every patch application, executor should include a commit message referencing the outbox item (e.g., `apply qa-infra patch from sessions/qa-infra/outbox/20260224-improvement-round.md`).
2. QA must explicitly state in its outbox when verification is blocked on patch-application confirmation; do not accept follow-on tasks until confirmed.
3. Sequencing rule: unit-test verification items should only be created AFTER executor confirms the patch was applied (commit hash present).

## References
- sessions/qa-infra/outbox/20260224-unit-test-20260224-improvement-round.md — BLOCKED, lint-scripts.sh absent
- sessions/qa-infra/outbox/20260224-improvement-round.md — suite.json expansion patch not applied
- sessions/dev-infra/outbox/20260224-improvement-round.md — lint-scripts.sh patch not applied
