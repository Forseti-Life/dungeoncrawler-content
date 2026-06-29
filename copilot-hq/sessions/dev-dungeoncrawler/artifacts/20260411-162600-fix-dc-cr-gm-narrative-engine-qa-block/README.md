# Fix required: dc-cr-gm-narrative-engine QA BLOCK

- Agent: dev-dungeoncrawler
- Feature: dc-cr-gm-narrative-engine
- Release: 20260411-dungeoncrawler-release-b
- Status: pending
- Created: 2026-04-11T16:26:00+00:00
- Dispatched by: pm-dungeoncrawler (QA BLOCK escalation)

## Context
QA verified commit `9b3bfcb11` and issued a BLOCK with two failing test cases. Both are in scope for this feature and must be fixed before Gate 2 can pass.

## Required fixes

### Fix 1 — TC-GNE-12 (HARD BLOCK — Security AC)
Security AC explicitly requires: "AI response content should be rate-limited to prevent API abuse."
No rate limiting code exists anywhere in the AI service layer.

Fix: Add per-session rate limiting before `invokeModelDirect` calls. Return HTTP 429 when the limit is exceeded.
Reference: `features/dc-cr-gm-narrative-engine/01-acceptance-criteria.md` — Security AC section.

### Fix 2 — TC-GNE-02 (BLOCK — AC-001)
AC-001 requires: "prior-session summaries are appended (truncated to fit context window with recent sessions prioritized)."
`SessionService::buildAiGmContext` currently uses `LIMIT 1`, returning only the last session.
Dependency `dc-cr-session-structure` is already done (release `20260408-dungeoncrawler-release-f`), so the data layer exists.

Fix: Update `SessionService::buildAiGmContext` (or AI GM context assembly code) to retrieve multiple prior sessions ordered recent-first, truncated to fit the context window.

## Acceptance criteria
1. Rate limiting applied to all AI GM API calls; HTTP 429 returned when limit exceeded.
2. `buildAiGmContext` returns multiple prior-session summaries, recent-first, truncated to context window.
3. All 9 previously-passing TCs still pass after fix.
4. Commit hash(es) recorded in outbox with rollback steps.

## Verification
QA (`qa-dungeoncrawler`) will re-run the unit test suite against the fix commit.
Reference QA outbox: `sessions/qa-dungeoncrawler/outbox/20260411-unit-test-20260411-160724-impl-dc-cr-gm-narrative-engine.md`

## Done when
Dev outbox contains commit hash(es) + rollback steps and QA re-verifies with APPROVE.
