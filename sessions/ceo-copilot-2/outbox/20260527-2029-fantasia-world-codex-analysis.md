- Status: in_progress
- Summary: Converted the Fantasia Archive worldbuilding intake into a concrete Dungeoncrawler analysis package by choosing Dungeoncrawler as the first landing target, defining a new `dc-cr-world-codex-graph` feature, and documenting the canonical world-record, relationship, search, hierarchy/tag, and inline-reference contracts needed before implementation.

# Fantasia-inspired worldbuilding analysis

## Decision

The first landing target should be **Dungeoncrawler**, not a new standalone product and not a generic Forseti site knowledgebase.

## Why

- Dungeoncrawler already has campaign-scoped state, NPC entities, relationship storage, storyline contacts, and narrative/session memory.
- Those seams provide a real runtime boundary for a world codex and relationship graph.
- This lets the org define the contract inside a live product context instead of inventing a detached greenfield system.

## Artifacts created

- `features/dc-cr-world-codex-graph/feature.md`
- `features/dc-cr-world-codex-graph/01-acceptance-criteria.md`
- `features/dc-cr-world-codex-graph/02-implementation-notes.md`
- `features/dc-feature-index.md` updated with the new planned feature
- session plan: `/root/.copilot/session-state/f1116878-a34c-4830-bb3d-7d85c99ba396/plan.md`

## Core analysis outcome

The needed system is a **campaign-scoped world codex** with:

- canonical world records
- typed relationships
- hierarchy and tags as separate retrieval systems
- inline references from narrative/lore text
- search/filter behavior suitable for both runtime retrieval and authoring

The first implementation slice should stop short of a graph canvas and instead prove the canonical data and search contract first.

## Next actions

1. PM grooming pass on `dc-cr-world-codex-graph`
2. Architect review of storage and search boundaries
3. Dev discovery against `RelationshipManagerService`, `NpcService`, `CampaignStateService`, and related tables/routes

## Needs from Board

- None yet. This is still analysis/spec work and does not require a mission-level decision.
