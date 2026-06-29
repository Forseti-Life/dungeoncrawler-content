# Status

- status: in_progress
- created_at: 2026-06-16T01:09:36+00:00
- current_phase: slice 1 complete - shared resolver extracted

## Notes

Created from Board direction after review of the current actor psychology/action prompt path. Current encounter prompts already embed a canonical-looking action envelope, but that envelope is still a curated subset and not a complete authoritative resolver for all engine action families.

### 2026-06-19 - slice 1 complete
- Board clarified the direction: one shared `getAvailableActions`-style resolver must serve **all actors including the GM**.
- Extracted the current encounter action-availability logic into `ActorActionAvailabilityService` as the first shared actor-scoped resolver slice.
- Wired `EncounterPhaseHandler`, `EncounterAiIntegrationService`, `GameCoordinatorService::getActionAvailabilityForActor()`, and `GameMasterSubsystemService::getActorActionAvailability()` onto that shared resolver family so GM/NPC/UI/server access now converges on one availability core.
- Focused unit coverage now locks the shared resolver wiring across encounter handler, NPC AI context, and GM subsystem access.
- Review-driven hardening fixed three first-pass issues in the slice: actor-scoped delay gating now checks the speaking actor's availability, off-turn actor queries no longer inherit active-turn reaction availability, and encounter preview context now carries actor-specific heritage metadata so actor-only actions remain aligned with the shared resolver.
- A second review/refactor pass tightened the shared contract further: `transition` is now actor-turn-scoped in both availability and validation, encounter AI validation now honors canonical zero-cost actions and action-cost matching, and coordinator actor-availability reads now normalize through the same `getFullState()` snapshot flow used by the main runtime.
- A deeper contract/assumption pass then removed the remaining drift inside the current slice: `EncounterPhaseHandler::validateIntent()` now defers to the shared availability surface instead of a stale custom action-economy list, and provider-side AI recommendation payloads now preserve canonical zero-cost costs and canonical actor identifiers so the contract can round-trip cleanly.
