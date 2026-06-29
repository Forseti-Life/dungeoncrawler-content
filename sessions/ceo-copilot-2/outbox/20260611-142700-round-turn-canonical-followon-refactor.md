- Status: done
- Summary: Continued review/refactor by removing persistence of synthetic room-turn-harness logs from room chat storage to further enforce single canonical transcript authority.

## What changed
- `RoomChatService::runRoomTurnHarness()` now builds turn-log diagnostics without writing them into `dungeon_data.rooms[*].chat`.
- Added optional persistence control to internal room system-log builders and used non-persisting mode for harness turn-order/current-turn diagnostics.
- NPC interjection prefixing in harness flow now derives from canonical encounter state (`buildEncounterPrefixForSpeaker`) instead of synthetic harness turn indices.

## Why
- Even after transcript rendering filters, persisted synthetic harness logs remained a second writer path in storage.
- Removing this write path reduces long-tail drift and prevents future history hydration from reintroducing non-canonical turn artifacts.

## Verification
- `cd /var/www/html/dungeoncrawler && vendor/bin/drush cr -q`
- `vendor/bin/drush -q php:script /home/ubuntu/forseti.life/dungeoncrawler-content/tests/chat_integration_test.php`
- `cd /home/ubuntu/forseti.life/dungeoncrawler-content && node tests/chat_panel_line_contract_test.js`
- `cd /home/ubuntu/forseti.life/dungeoncrawler-content && node tests/chat_panel_progress_contract_test.js`
- `cd /var/www/html/dungeoncrawler && vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Controller/RoomChatControllerProgressTest.php`

## Pushed code
- `dungeoncrawler-content` commit `ae44c7b` on `main`

## Next actions
- Observe new campaign transcripts to confirm no fresh synthetic `Current turn:` / `Turn order:` room lines are persisted.

## Blockers
- None

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
