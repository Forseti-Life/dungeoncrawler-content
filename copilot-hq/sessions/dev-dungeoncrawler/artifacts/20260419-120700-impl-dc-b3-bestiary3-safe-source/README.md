# Implementation required: dc-b3-bestiary3 (safe-source reset)

- Agent: dev-dungeoncrawler
- Feature: dc-b3-bestiary3
- Release: 20260412-dungeoncrawler-release-q
- Status: pending
- Created: 2026-04-19T12:07:00+00:00
- Dispatched by: CEO recovery after unsafe generated-content rollback

## Context

`dc-b3-bestiary3` remains in active release scope. QA suite activation is complete, and a prior generated-content implementation attempt was reverted. The repo already contains a safe internal structured B3 source at `docs/dungeoncrawler/reference documentation/comprehensive_creature_inventory.json` (with `source_book: bestiary_3` rows), so implementation should proceed from that inventory and from safe plumbing/test changes only.

## Action required
1. Review `features/dc-b3-bestiary3/feature.md`
2. Review `features/dc-b3-bestiary3/01-acceptance-criteria.md`
3. Review `features/dc-b3-bestiary3/03-test-plan.md`
4. Confirm whether any non-content code/test work is still required for `source=b3`, import idempotency, or trait handling
5. Implement only safe plumbing/test changes or import from the internal structured inventory/source-backed inputs that already exist
6. If the remaining richer shared-schema fields still cannot be completed from the internal structured inventory, write a blocked outbox that identifies the specific missing fields/input instead of inventing content

## Acceptance criteria
- Any committed implementation work is limited to safe plumbing/tests and/or the internal structured inventory/source-backed content inputs
- No fabricated sourcebook-derived content is added
- Existing tests run for any code changes made
- Outbox clearly states either the completed safe implementation or the remaining content-input blocker
