# Problem Statement: Release Handoff Full Investigation
## Context (2026-03-28)

**Release in flight:** `20260327-dungeoncrawler-release-b`
- 4 features implemented by dev (all `in_progress` in feature.md; dev outboxes marked done)
- QA Gate 2 (unit-test verification) is queued in qa-dungeoncrawler inbox — NOT yet processed
- QA preflight complete (20260327 preflight outbox: routes audited, 4 new routes added to qa-permissions.json)
- PM signoff artifact exists (pre-populated by orchestrator referencing prior coordinated release 20260326) — **review required; do not treat as Gate 2 approval**

**Why now:**
- Dev completed all 4 impl tickets; QA has 4 Gate 2 verification items queued
- pm-forseti signoff is false; official push is blocked until pm-forseti signs off
- Next release `20260328-dungeoncrawler-release-b` is fully groomed and ready for Stage 0 the moment release-b closes

## Goals (Outcomes)
- Document the exact gate status for `20260327-dungeoncrawler-release-b` so release handoff is unambiguous
- Identify any gate gaps that could delay QA verification or the final push
- Confirm that next-cycle Stage 0 can begin immediately after release-b ships

## Non-Goals
- Feature scope changes to release-b
- PM approval/waiver of QA Gate 2
- Grooming release-c (already done; ready pool artifact is current)

## Users / Personas
- QA: needs clear list of which features to verify and in what order
- pm-forseti: needs dungeoncrawler gate summary to coordinate final push
- CEO: needs a single source of truth for release status

## Constraints
- Security: no new security concerns; all routes are `_access: TRUE` (public endpoints)
- Performance: not a factor for this release
- Backward compatibility: no schema changes in action-economy/ancestry-system/dice-system; difficulty-class adds no schema

## Success Metrics
- All 4 QA Gate 2 items return APPROVE with evidence
- pm-forseti signoff is recorded
- `scripts/release-signoff-status.sh 20260327-dungeoncrawler-release-b` exits 0
- All 4 feature statuses updated to `shipped` in feature.md

## Dependencies
- qa-dungeoncrawler: must process 4 queued unit-test verification inbox items
- pm-forseti: must record their signoff for coordinated push to proceed
- dev-dungeoncrawler: standby for any QA BLOCK fix (not expected; dev confident in implementations)

## Risks
- QA Gate 2 backlog: QA inbox has 4 unit-test items + other items; if not sequenced correctly, Gate 2 may be delayed
- Known DB credential gap: PHPUnit functional tests fail with placeholder credentials in phpunit.xml.dist; this is an infrastructure gap that may cause QA to interpret as failures
- pm-forseti signoff dependency: dungeoncrawler release is blocked on forseti release readiness

## Knowledgebase check
- KB: infra gap for PHPUnit credentials documented in dev outbox for dc-cr-dice-system
- KB: coordinated release dependency on pm-forseti is a recurring pattern (GAP-DC-B-01)
