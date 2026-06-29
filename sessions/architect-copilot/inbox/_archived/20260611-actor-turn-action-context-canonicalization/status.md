# Status

- status: completed
- created_at: 2026-06-11T13:58:00+00:00
- current_phase: complete

## Notes

### 2026-06-11 — Kickoff
- Inbox item created for deterministic actor turn action-context canonicalization.
- Started architecture review of current action-availability surfaces:
  - `PhaseHandlerInterface::getAvailableActions(...)`
  - `EncounterPhaseHandler::getAvailableActions(...)`
  - `EncounterPhaseHandler::getClientActionContract(...)`
  - `EncounterAiIntegrationService::buildEncounterContext(...)`
  - `CombatEncounterApiController::buildActorTurnAiContext(...)`

### 2026-06-11 — Refactor/hardening pass complete
- Removed static actor-turn action list usage from context builders where
  actor-scoped deterministic derivation is available.
- `EncounterPhaseHandler::buildNpcContext(...)` now emits:
  - `allowed_actions` from actor-scoped `getAvailableActions(...)`
  - `action_contract` from `getClientActionContract(...)`
  - `actions_available_to_me_this_turn` envelope with deterministic shape.
- `EncounterAiIntegrationService::buildEncounterContext(...)` now emits:
  - deterministic `allowed_actions` based on actor turn resources
  - structured `action_contract`
  - canonical `actions_available_to_me_this_turn` envelope including
    `action_contract`.
- Added/updated focused regressions:
  - `EncounterAiIntegrationServiceTest::testBuildEncounterContextReturnsExpectedShape`
  - `EncounterPhaseHandlerTest::testBuildNpcContextIncludesCurrentActorProfile`
- Focused suites pass for modified surfaces:
  - `EncounterAiIntegrationServiceTest`
  - targeted `EncounterPhaseHandlerTest` subset
  - `AiConversationEncounterAiProviderTest`

### 2026-06-11 — Delivery
- `dungeoncrawler-content` changes committed and pushed to `main`:
  - commit: `759eb05`
- Canonical actor-turn action context is now deterministic and server-derived
  across actor-turn context builders covered by this item.

## Next Action
1. Monitor downstream consumers for reliance on removed static action assumptions.
