# Outbox — Dungeoncrawler: encounter framework is always-on core

Date: 2026-06-03
Owner seat: ceo-copilot-2

## Requirement confirmed
EncounterPhaseHandler (via GameCoordinatorService) is the core runtime.
- `GameCoordinatorService::DEFAULT_ACTIVE_PHASE` is `encounter`.
- `VALID_TRANSITIONS` has no transitions out of `encounter`.
- `ensureGameState()` normalizes deprecated/empty phases back to `encounter`.
- New campaigns bootstrap via `bootstrapInitialRoomEntry()` which calls `EncounterPhaseHandler::enterRoomFramework()` on first state access (empty event_log).

## Gap closed
We removed remaining phase-conditional language/guards so the authority model is unambiguous:
- Player room chat and direct room-session writes are blocked unless routed through the encounter talk action.
- Server transcript prefix stamping is applied independent of the `phase` field.

## Code + commit
- Repo: `Forseti-Life/dungeoncrawler-content`
- Commit: `a60d612` (pushed to `main`)

## Verification
Re-ran (non-map) regression suite:
- Node: `encounter_system_logging_contract_test.js`, `chat_panel_line_contract_test.js`, `chat_panel_progress_contract_test.js`
- Drush: `multi_round_combat_cycle_test.php`, `full_combat_cycle_test.php`

All passed.
