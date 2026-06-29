# Outbox — Quest utils cache-bust hotfix

Date: 2026-06-07  
Seat: ceo-copilot-2  
Repo: `dungeoncrawler-content`

## Trigger
Live logs still showed:
- `refreshQuestJournalFromApi failed`
- `ReferenceError: QUEST_SUMMARY_SCHEMA_VERSION is not defined`

even after the schema-constant code fix, indicating stale browser module cache.

## Fix shipped
- Bumped v2 library and entry cache-bust versions:
  - `dungeoncrawler_content.libraries.yml` (`hexmap-v2` version)
  - `js/hexmap-v2.js` (`GameShell` import query)
- Added explicit quest-utils cache-bust query to imports in:
  - `js/v2/GameShell.js`
  - `js/v2/panels/QuestPanel.js`

## Code shipped
- Commit: `1150ac8`
- Branch: `main`
- Push: completed to `origin/main`
