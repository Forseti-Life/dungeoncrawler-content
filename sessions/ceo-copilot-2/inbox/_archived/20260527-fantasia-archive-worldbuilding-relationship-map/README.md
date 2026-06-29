# Incorporate Fantasia Archive-style worldbuilding and relationship-map mechanics

- Agent: ceo-copilot-2
- Requested-by: Board
- Requested-at: 2026-05-27T20:21:59+00:00
- Source: Board command
- External reference: https://github.com/vishiri/fantasia-archive
- Status: archived
- Closed-at: 2026-05-29T12:33:14+00:00
- Closeout reason: Board directed that this avenue is not being pursued because it was not a good fit.

## Issue

Evaluate how to incorporate the **worldbuilding database** and **relationship-map/search mechanics** exemplified by **Fantasia Archive** into the Forseti portfolio, and convert that into concrete org work.

Relevant mechanics identified from the reference project:

- **Projects and documents** as the primary worldbuilding model, not loose ad hoc notes.
- **Relationship fields** between documents, including single and multi-relationship flows.
- **Advanced relationship search** that can filter by type, tag, hierarchy, switches, and full-field search.
- **Hierarchical tree + tags** as parallel organization and discovery mechanisms.
- **Inline document linking** from long-form text via `@` references.

This is a **mechanics and product-pattern intake**, not a request to copy Fantasia Archive code or UI directly. Fantasia Archive is GPL-3.0, so the org must treat it as inspiration for product design, data modeling, and UX contracts only unless a deliberate GPL-compatible strategy is approved.

## Required outcome

The CEO should turn this into an actionable workstream by deciding:

1. Which Forseti product or project should own this capability first.
2. What the canonical domain model should be for world entities, relationships, hierarchy, tags, and inline references.
3. Whether the right first slice is:
   - a Dungeoncrawler lore/world-state system,
   - a Forseti/forseti.life narrative knowledgebase feature,
   - or a new explicitly scoped product/project.
4. What follow-on inbox items need to be dispatched to PM / architect / dev once the target scope is chosen.

## Acceptance criteria

- CEO reviews the Fantasia Archive reference and writes a scope decision in outbox.
- The scope decision names the owning project/product and the first delivery slice.
- The resulting plan defines a canonical contract for:
  - worldbuilding records/documents,
  - typed relationships between records,
  - hierarchy/tag navigation,
  - inline cross-reference/link behavior,
  - search/filter expectations.
- Any follow-on delegation is issued as concrete inbox items with ROI and verification, not left as a vague note.
- Any licensing boundary is made explicit: pattern/mechanics may inspire the design, but no unreviewed code copying from GPL material.

## Verification

- CEO outbox entry filed with `Status: done` or `Status: in_progress` and a concrete recommendation.
- If delegated onward, new inbox items exist for the owning PM/architect/dev seats with measurable contracts.

## Notes

- Reference repo product description: Fantasia Archive is a **worldbuilding database manager** organized around **projects** and **documents**.
- Reference repo docs explicitly describe **relationship searches** across document fields and support for **hierarchy**, **tags**, and **full-search** filters.

## Current CEO-owned execution state

This workstream has now been pulled back under the **CEO inbox** and should be run from this seat instead of from delegated PM/architect/dev/QA inbox items.

Current scope decision already made:

- first landing target: **Dungeoncrawler**
- canonical foundational feature: `dc-cr-world-codex-graph`
- dependent social layer: `dc-cr-social-relationship-loyalty`

Current planning artifact set:

- `01-dungeoncrawler-worldbuilding-master-plan.md`
- `02-dungeoncrawler-service-boundary-validation.md`
- `features/dc-cr-world-codex-graph/feature.md`
- `features/dc-cr-world-codex-graph/01-acceptance-criteria.md`
- `features/dc-cr-world-codex-graph/02-implementation-notes.md`
- `features/dc-cr-world-codex-graph/03-schema-contract.md`
- `features/dc-cr-world-codex-graph/04-readiness-matrix.md`
- `features/dc-cr-world-codex-graph/05-endpoint-contracts.md`
- `features/dc-cr-world-codex-graph/06-subject-id-contract.md`

Archived delegated planning inputs for reference:

- `sessions/pm-dungeoncrawler/inbox/_archived/20260528-world-codex-social-relationship-grooming/`
- `sessions/architect-copilot/inbox/_archived/20260528-world-codex-social-contract-review/`
- `sessions/dev-dungeoncrawler/inbox/_archived/20260528-world-codex-social-discovery/`
- `sessions/qa-dungeoncrawler/inbox/_archived/20260528-world-codex-social-readiness-matrix/`

## CEO next actions

1. Continue running codex planning and execution directly from the CEO queue.
2. Use the archived delegated inbox items as reference material, not as active execution owners.
3. Keep the world-codex contract authoritative over downstream social-state work.
