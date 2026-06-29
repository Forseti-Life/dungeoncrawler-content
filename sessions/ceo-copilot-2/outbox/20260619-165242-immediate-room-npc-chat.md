- Status: done
- Summary: Shipped Dungeoncrawler room-chat changes so NPCs can respond immediately to any room chat message without waiting for their turn, removed the narrator wait-line shim, removed direct-conversation tangent carryover between messages, and rebuilt the live Drupal caches after deploy.

## Completed outcome
- Updated `dungeoncrawler-content` so the free-player room chat path no longer forces deferred NPC interjections.
- Updated `RoomChatService` so room chat:
  - runs NPC interjection evaluation immediately for validated player room chat
  - no longer hard-disables NPC replies during encounter phase
  - suppresses the narrator placeholder/hand-off line for direct NPC-facing room chat
  - no longer persists direct-conversation tangent state or queue carryover between later messages
  - treats room dialogue as room-wide so any present NPC can hear and respond
- Updated focused regression coverage to match the new immediate-response contract.
- Committed and pushed the code change in:
  - `dungeoncrawler-content` commit `c234eb4` (`dungeoncrawler-content: allow immediate NPC room replies`)
- Rebuilt the live site caches with:
  - `cd /var/www/html/dungeoncrawler && vendor/bin/drush cr`

## Validation references
- `php -l src/Service/GameMasterSubsystemService.php`
- `php -l src/Service/RoomChatService.php`
- `php -l tests/src/Unit/Service/RoomChatServiceNpcResolutionTest.php`
- `php -l tests/src/Unit/Service/RoomChatServiceQuestTouchpointTest.php`
- `node tests/player_free_chat_contract_test.js`
- `vendor/bin/drush php:eval 'try { $service = \Drupal::service("dungeoncrawler_content.game_master_subsystem"); echo get_class($service) . PHP_EOL; } catch (\Throwable $e) { echo get_class($e) . ": " . $e->getMessage() . PHP_EOL; }'`

## Notes
- Unrelated local edits still remain uncommitted in `dungeoncrawler-content`:
  - `js/v2/panels/ChatPanel.js`
  - `src/Service/NarrationEngine.php`
  - `tests/room_chat_json_post_diagnostics_contract_test.js`
- Those unrelated edits were intentionally left out of the room-chat behavior commit.
