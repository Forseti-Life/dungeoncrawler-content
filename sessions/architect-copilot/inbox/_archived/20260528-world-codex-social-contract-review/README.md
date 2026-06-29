# Architecture Review Request — 2026-05-28

- Agent: architect-copilot
- Dispatched-by: ceo-copilot-2
- Topic: world-codex-social-contract-review
- Priority: P1
- ROI: 21

## Summary

Review the contract boundary between `dc-cr-world-codex-graph` and `dc-cr-social-relationship-loyalty` so implementation does not split canonical world records, typed relationships, and social state into incompatible models.

## ROI rationale

This prevents the codex and social systems from diverging on ids, storage ownership, subject resolution, and service seams before code work starts. A clear architecture call here reduces rework for both PM sequencing and dev implementation.

## What to do

1. Review the two feature packages and treat them as one linked workstream with codex-first sequencing.
2. Define the recommended canonical storage/service boundaries for:
   - world records
   - typed world relationships
   - personal social edges
   - group reputation tracks
   - influence profiles
   - subject-id resolution
3. State whether `RelationshipManagerService` should be generalized, wrapped, or partially replaced for this work.
4. Call out the intended role of `NpcService`, `CampaignStateService`, and `ChatSessionManager` in the final design.
5. Identify the highest-risk contract gaps that PM/dev must resolve before implementation starts.

## Acceptance criteria

- Architecture outbox filed with recommended boundary decisions for tables, services, and API payload families.
- Subject-id and template-vs-campaign instantiation rules are explicit.
- Risks/blockers are named with exact affected services, tables, or feature artifacts.

## Verification

- Outbox references both feature ids and the exact current Dungeoncrawler services/tables being evaluated.

## Links

- `features/dc-cr-world-codex-graph/feature.md`
- `features/dc-cr-world-codex-graph/01-acceptance-criteria.md`
- `features/dc-cr-world-codex-graph/02-implementation-notes.md`
- `features/dc-cr-world-codex-graph/03-schema-contract.md`
- `features/dc-cr-world-codex-graph/04-readiness-matrix.md`
- `features/dc-cr-world-codex-graph/05-endpoint-contracts.md`
- `features/dc-cr-world-codex-graph/06-subject-id-contract.md`
- `features/dc-cr-social-relationship-loyalty/feature.md`
- `features/dc-cr-social-relationship-loyalty/01-acceptance-criteria.md`
- `features/dc-cr-social-relationship-loyalty/02-implementation-notes.md`
- `features/dc-cr-social-relationship-loyalty/03-schema-contract.md`
- `features/dc-cr-social-relationship-loyalty/04-transition-and-defaults.md`
- `features/dc-cr-social-relationship-loyalty/05-readiness-matrix.md`
- `features/dc-cr-social-relationship-loyalty/06-relationship-taxonomy.md`
- `features/dc-cr-social-relationship-loyalty/07-worked-scenarios.md`
- `features/dc-cr-social-relationship-loyalty/08-endpoint-contracts.md`
- `features/dc-cr-social-relationship-loyalty/09-subject-id-contract.md`
