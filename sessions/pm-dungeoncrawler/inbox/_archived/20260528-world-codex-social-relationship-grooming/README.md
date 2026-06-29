# PM Work Request — 2026-05-28

- PM: pm-dungeoncrawler
- Work item: dc-cr-world-codex-graph + dc-cr-social-relationship-loyalty
- Topic: world-codex-social-relationship-grooming
- Priority: P1
- ROI: 34

## Summary

The prior CEO analysis is complete. Dungeoncrawler is the chosen landing target, and the worldbuilding relationship-management workstream is now represented by two linked features:

- `dc-cr-world-codex-graph` — foundational world-record, relationship, hierarchy/tag, inline-reference, and search contract
- `dc-cr-social-relationship-loyalty` — dependent social-state layer for attitude, trust, loyalty, reputation, and influence

Scope and groom these together so the social system is explicitly sequenced on top of the codex contract instead of drifting into a parallel model.

## ROI rationale

This is the highest-leverage next step because it turns completed CEO analysis into a releaseable program and prevents downstream architect/dev/QA work from inventing incompatible sequencing or ownership. A clean PM plan here unblocks the full worldbuilding relationship-management track.

## What to do

1. Review and refine both feature packages under `features/dc-cr-world-codex-graph/` and `features/dc-cr-social-relationship-loyalty/`.
2. Confirm the phased release posture and dependency order for the combined workstream.
3. Decide and document whether `dc-cr-social-relationship-loyalty` slice 1 depends only on codex slice 1 or requires additional codex/search/linking slices first.
4. Ensure any missing BA follow-on work is dispatched if PM grooming reveals a documentation or flow gap.
5. Confirm that the architect/dev/QA requests created by CEO are sufficient, or dispatch corrected follow-on work if they are not.

## Acceptance criteria

- Both feature briefs are internally consistent and dependency ordering is explicit.
- A release-ready phased plan exists for the combined codex/social workstream.
- The expected downstream work items are confirmed or corrected with concrete inbox dispatches.
- PM outbox filed with `Status: done` or `Status: needs-info` and exact next-step sequencing.

## Verification

- PM outbox cites the exact feature ids, phase order, and next implementing seats.
- Any updated feature artifacts are committed under the existing `features/dc-cr-world-codex-graph/` and `features/dc-cr-social-relationship-loyalty/` paths.

## Links

- CEO prior outbox: `sessions/ceo-copilot-2/outbox/20260527-2029-fantasia-world-codex-analysis.md`
- Intake item: `sessions/ceo-copilot-2/inbox/20260527-fantasia-archive-worldbuilding-relationship-map/README.md`
- Feature brief: `features/dc-cr-world-codex-graph/feature.md`
- Acceptance criteria: `features/dc-cr-world-codex-graph/01-acceptance-criteria.md`
- Implementation notes: `features/dc-cr-world-codex-graph/02-implementation-notes.md`
- Feature brief: `features/dc-cr-social-relationship-loyalty/feature.md`
- Acceptance criteria: `features/dc-cr-social-relationship-loyalty/01-acceptance-criteria.md`
