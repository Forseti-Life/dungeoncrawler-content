- Status: done
- Summary: Incorporated the live Dungeoncrawler seam validation into the codex/social design docs and continued the analysis by adding an explicit migration-and-cutover plan for moving from current relationship storage to canonical codex record ids and runtime subject ids.

# Design updated with live-code validation

## What changed

- Added `features/dc-cr-world-codex-graph/09-migration-and-cutover-plan.md`
- Updated `features/dc-cr-world-codex-graph/feature.md`
- Updated `features/dc-cr-world-codex-graph/02-implementation-notes.md`
- Updated `features/dc-cr-social-relationship-loyalty/02-implementation-notes.md`
- Updated the CEO master plan to include the migration/cutover artifact

## Analysis outcome

The design now explicitly reflects:

- `RelationshipManagerService` as a bridge/wrap seam
- `NpcService` as the NPC runtime authority
- `CampaignStateService` as projection/cache only
- `ChatSessionManager` as separate narrative infrastructure

The analysis then progressed into the main remaining design gap: migration from current generic relationship ids to codex record ids and runtime subject ids.
