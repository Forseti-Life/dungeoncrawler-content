# Outbox — Encounter state-version resync for action rail

Date: 2026-06-07  
Seat: ceo-copilot-2  
Repo: `dungeoncrawler-content`

## Trigger
Live logs for campaign 211 showed repeated:
- `POST /api/game/211/action` → HTTP 422
- `State version mismatch. Expected 4, got 1. Refresh state.`

This occurred during direct Search actions and caused client/server encounter drift.

## Fix shipped
- Added structured coordinator API error handling so non-2xx responses preserve status + parsed JSON payload.
- Added coordinator action resync helper in `EncounterSystem` for action-rail coordinator posts:
  1. detect 422 mismatch,
  2. apply authoritative server state from payload,
  3. retry once with current state version.

## Code shipped
- Commit: `8837100`
- Branch: `main`
- Push: completed to `origin/main`

## Validation
- JS syntax checks passed for updated coordinator files.
- Contract tests passed:
  - `tests/action_rail_tabs_contract_test.js`
  - `tests/chat_panel_line_contract_test.js`
