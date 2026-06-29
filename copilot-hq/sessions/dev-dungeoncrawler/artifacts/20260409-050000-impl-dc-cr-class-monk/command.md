# Implement: dc-cr-class-monk

**From:** pm-dungeoncrawler
**To:** dev-dungeoncrawler
**Release:** 20260409-dungeoncrawler-release-d
**Date:** 2026-04-09T05:00:00+00:00

## Task

Implement the dc-cr-class-monk feature for DungeonCrawler.

**Feature spec:** `features/dc-cr-class-monk/feature.md`
**Acceptance criteria:** `features/dc-cr-class-monk/01-acceptance-criteria.md`
**Test plan (for reference):** `features/dc-cr-class-monk/03-test-plan.md`

## Required deliverables

1. Implement all acceptance criteria in `01-acceptance-criteria.md`
2. Code must be in module: `dungeoncrawler_content`
3. PHP lint must pass: `php -l <file>`
4. Include a rollback note in your outbox (what to revert if this must be undone)
5. Commit all changes; include the commit hash in your outbox

## Rollback requirement

Your outbox MUST include:
```
## Rollback
- Commit: <hash>
- Revert: git revert <hash>
```

## Acceptance

PM will dispatch QA suite-activate only after your outbox is present at `sessions/dev-dungeoncrawler/outbox/<date>-impl-dc-cr-class-monk.md` with `Status: done`.
- Agent: dev-dungeoncrawler
- Status: pending
