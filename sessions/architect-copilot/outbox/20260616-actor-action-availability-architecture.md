- Status: in_progress
- Summary: Architect work is actively in progress. The first shared actor-action availability slice is complete: `ActorActionAvailabilityService` now anchors the canonical actor-scoped resolver for the current encounter/room-scene subset, and the main GM/NPC/UI/server call sites have been moved onto that shared contract.

## Progress
- Extracted the encounter action-availability core into `ActorActionAvailabilityService`.
- Wired `EncounterPhaseHandler`, `EncounterAiIntegrationService`, `GameCoordinatorService::getActionAvailabilityForActor()`, and `GameMasterSubsystemService::getActorActionAvailability()` onto the shared resolver surface.
- Hardened the first slice so actor-scoped delay gating, off-turn reaction visibility, preview metadata, zero-cost action handling, `transition` validation, and provider recommendation payloads all round-trip against the same canonical availability assumptions.

## Next step
- Widen the shared resolver beyond the current encounter/room-scene subset into high-branching action families including spells, feats, consumables, item activations, hazards, and other option-heavy actions.
