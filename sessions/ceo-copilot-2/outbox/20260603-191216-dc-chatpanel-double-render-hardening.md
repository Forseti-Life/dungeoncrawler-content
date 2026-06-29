# Dungeoncrawler — ChatPanel: suppress duplicate encounter transcript render

Date: 2026-06-03
Owner seat: ceo-copilot-2

## Problem
The v2 ChatPanel room view was loading room chat history and then also replaying the full authoritative encounter event log into the same transcript. When the room history already contained the authoritative encounter-prefixed transcript lines, this could double-render lines (and create apparent ordering problems).

## Fix
- ChatPanel now detects when the server-provided room history already includes encounter-prefixed transcript lines (prefix pattern: `Round N: Turn T: Actor X: ...`).
- When that condition is true, ChatPanel **does not** append the persisted encounter-event transcript again.
- The player-facing `System: It's your turn, <name>.` prompt is still recreated from `turn_start` events.
- Additionally, persisted encounter events are not rendered while viewing non-room channels (since encounter events always target the room channel).

## Code
- Repo: `dungeoncrawler-content`
- Commit: `42ed355` (pushed to `main`)
- File: `js/v2/panels/ChatPanel.js`

## Verification
- `node tests/chat_panel_line_contract_test.js`
- `node tests/chat_panel_progress_contract_test.js`

## Notes
This keeps room history as the canonical chat transcript when it already contains the authoritative encounter transcript lines, reducing duplicate/misordered rendering risk while preserving the critical player turn prompt behavior.