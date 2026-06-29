# Outbox — Quest journal refresh ReferenceError fix

Date: 2026-06-07  
Seat: ceo-copilot-2  
Repo: `dungeoncrawler-content`

## Trigger
User reported quest tasks not checking off in UI and no visible completion progress updates.

Live console trace showed:
- `refreshQuestJournalFromApi failed`
- `ReferenceError: QUEST_SUMMARY_SCHEMA_VERSION is not defined`

## Root cause
`js/v2/utils/quest-utils.js` used `QUEST_SUMMARY_SCHEMA_VERSION` inside `normalizeQuestSummaryPayload()` but the constant was never defined/exported in that v2 module.

## Fix shipped
- Added:
  - `export const QUEST_SUMMARY_SCHEMA_VERSION = 'quest-summary-v2';`

This unblocks `refreshQuestJournalFromApi()` and allows quest completion state to reflect in the quest UI again.

## Code shipped
- Commit: `99e67eb`
- Branch: `main`
- Push: completed to `origin/main`

## Validation
- JS syntax check passes for quest utils.
- Frontend contract tests pass:
  - `tests/chat_panel_progress_contract_test.js`
  - `tests/action_rail_tabs_contract_test.js`
