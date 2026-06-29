# Dungeoncrawler — ChatPanel: deterministic ordering tie-breakers

Date: 2026-06-03
Owner seat: ceo-copilot-2

## Problem
Chat transcript hydration/re-rendering can mix multiple authoritative sources (room history, streamed room responses, persisted encounter events). When timestamps collide (common when the coordinator logs multiple events with the same ISO timestamp), the transcript could appear to reorder or shuffle lines on refresh.

## Fix
- `ChatPanel.renderChatLineRecords()` now sorts normalized lines deterministically before rendering/remembering:
  - Primary: `created` (timestamp ms)
  - Tie-breakers: `eventId` (numeric), `messageId` (numeric), then `lineId`
  - Stable handling for lines missing timestamps (keeps their original relative order and places them after timestamped lines)

## Regression coverage
- Extended `tests/chat_panel_line_contract_test.js` with an ordering contract asserting stable ordering when `created` timestamps collide.

## Code
- Repo: `dungeoncrawler-content`
- Commit: `afb18ee` (pushed to `main`)
- Files:
  - `js/v2/panels/ChatPanel.js`
  - `tests/chat_panel_line_contract_test.js`

## Verification
- `node tests/chat_panel_line_contract_test.js`
- `node tests/chat_panel_progress_contract_test.js`