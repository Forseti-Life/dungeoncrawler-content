- Status: done
- Summary: Resolved the live Dungeoncrawler room-chat `GameMasterSubsystemService::__construct()` failure by rebuilding the stale Drupal service container; the runtime now instantiates the GM subsystem with the correct 2-argument signature.

## Completed outcome
- Reproduced the live failure directly in the Drupal runtime with:
  - `cd /var/www/html/dungeoncrawler && vendor/bin/drush php:eval 'try { $service = \Drupal::service("dungeoncrawler_content.game_master_subsystem"); echo get_class($service) . PHP_EOL; } catch (\Throwable $e) { echo get_class($e) . ": " . $e->getMessage() . PHP_EOL; }'`
- Confirmed the live module path is the symlinked repo source:
  - `/var/www/html/dungeoncrawler/web/modules/custom/dungeoncrawler_content -> /home/ubuntu/forseti.life/dungeoncrawler-content`
- Confirmed the source/service wiring already expects the 2-argument constructor, so the failure was a stale runtime container rather than a code mismatch.
- Rebuilt the Drupal caches with:
  - `cd /var/www/html/dungeoncrawler && vendor/bin/drush cr`
- Re-verified the runtime after rebuild:
  - `Drupal\dungeoncrawler_content\Service\GameMasterSubsystemService`

## Validation references
- `vendor/bin/drush status --fields=drupal-version,db-status,bootstrap --format=json`
- `vendor/bin/drush php:eval 'try { $service = \Drupal::service("dungeoncrawler_content.game_master_subsystem"); echo get_class($service) . PHP_EOL; } catch (\Throwable $e) { echo get_class($e) . ": " . $e->getMessage() . PHP_EOL; }'`
- `vendor/bin/drush cr`
- `curl -I 'https://dungeoncrawler.forseti.life/hexmap?campaign_id=266&character_id=984&dungeon_level_id=7f2c64b3-6a91-441b-abba-8c558e919ad9&map_id=23a74f50-58f8-4c34-aeb0-f885844244b7'`

## Notes
- No source code change was required in `dungeoncrawler-content`; the runtime was still using a stale service container definition.
- The `dungeoncrawler-content` working tree still contains existing local edits in:
  - `js/v2/panels/ChatPanel.js`
  - `src/Service/NarrationEngine.php`
  - `tests/room_chat_json_post_diagnostics_contract_test.js`
