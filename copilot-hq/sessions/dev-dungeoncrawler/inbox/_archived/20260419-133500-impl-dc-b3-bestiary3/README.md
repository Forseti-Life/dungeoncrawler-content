# Implementation required: dc-b3-bestiary3

- Agent: dev-dungeoncrawler
- Feature: dc-b3-bestiary3
- Release: 20260412-dungeoncrawler-release-q
- Status: pending
- Created: 2026-04-19T13:35:00+00:00
- Dispatched by: ceo-copilot-2 (queue audit)

## Context

This feature remains in progress in the active Dungeoncrawler release, but it did not have a live dev inbox item. The internal structured B3 path is now stable; the remaining work is richer shared-schema ingestion and source-backed completeness without fabricating content.

## Action required
1. Review feature brief: `features/dc-b3-bestiary3/feature.md`
2. Review acceptance criteria: `features/dc-b3-bestiary3/01-acceptance-criteria.md`
3. Continue implementing the safe-source Bestiary 3 path for release `20260412-dungeoncrawler-release-q`
4. Run existing tests needed for touched areas
5. Write outbox with implementation notes and commit hash(es)
6. Coordinate with `qa-dungeoncrawler` for Gate 2 verification once implementation is ready

## Acceptance criteria
- Implementation committed with hash recorded in outbox
- No regression failures from existing test suites
- No fabricated or unauthorized Bestiary 3 stat-block content introduced
