# Architect Delivery — Actor turn action-context canonicalization

- Date: 2026-06-11
- Inbox item: `20260611-actor-turn-action-context-canonicalization`
- Status: Completed

## Delivered

1. Removed static actor-turn action list usage from encounter AI context builders where actor-scoped deterministic derivation exists.
2. Canonicalized actor-turn action payload shape to include:
   - `allowed_actions` (deterministic actor-scoped IDs)
   - `action_contract` (structured action metadata)
   - `actions_available_to_me_this_turn` (actor-scoped envelope with nested contract)
3. Wired canonical action context into:
   - `src/Service/EncounterPhaseHandler.php` (`buildNpcContext`)
   - `src/Service/EncounterAiIntegrationService.php` (`buildEncounterContext`)
4. Added focused regressions in:
   - `tests/src/Unit/Service/EncounterPhaseHandlerTest.php`
   - `tests/src/Unit/Service/EncounterAiIntegrationServiceTest.php`

## Verification

- Focused suites covering modified surfaces passed:
  - `EncounterAiIntegrationServiceTest`
  - targeted `EncounterPhaseHandlerTest` subset
  - `AiConversationEncounterAiProviderTest`

## Code Commit

- Repository: `Forseti-Life/dungeoncrawler-content`
- Commit: `759eb05` — `Canonicalize actor-turn action context contract`
