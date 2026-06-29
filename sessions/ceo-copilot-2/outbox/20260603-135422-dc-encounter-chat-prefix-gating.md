# Outbox — Dungeoncrawler encounter chat prefix + bypass gating

Date: 2026-06-03
Owner seat: ceo-copilot-2

## Outcome
Closed the remaining bypass paths so the **EncounterPhaseHandler remains the master engine** for encounter-phase room chat, and ensured **every room chat line during encounter is server-prefixed** with the canonical transcript header:

`Turn <X>: Round <Y>: Actor <Name>: <content>`

## What changed
### Server-authoritative prefixing
- Encounter engine now stamps/propagates a canonical `_encounter_prefix` into RoomChatService so **player + GM + NPC + system turn-log lines** are consistently prefixed.
- GM continuation (`continueQueuedRoomConversation`) now computes the current encounter prefix and prefixes the GM output as well.

### Bypass prevention (action economy enforcement)
- `RoomChatService::postMessage()` now rejects **player room chat during encounter** unless it is invoked via the encounter talk action (i.e. has an encounter prefix), preventing “free talk” outside action economy.
- `ChatSessionController::postSessionMessage()` now blocks direct session writes to room sessions during encounter (409), preventing a second bypass surface.

## Code + commit
- Repo: `Forseti-Life/dungeoncrawler-content`
- Commit: `08b5e2d` (pushed to `main`)

## Verification
Ran:
- `php -l` on modified PHP files
- `node tests/encounter_system_logging_contract_test.js`
- `node tests/chat_panel_line_contract_test.js`
- `node tests/chat_panel_progress_contract_test.js`
- Drush scripts:
  - `tests/multi_round_combat_cycle_test.php`
  - `tests/full_combat_cycle_test.php`

All passed.

## Notes / next steps
- Remaining work is largely **manual QA in browser** to confirm the live room transcript shows the prefix on *every* visible line and that off-turn or no-actions talk fails deterministically.
