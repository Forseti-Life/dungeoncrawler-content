- Status: done
- Summary: Resolved live campaign room-chat stream failures caused by room-turn-harness contract rejection of `turn_logs[*].turn_prompt`.

## What changed
- Root cause confirmed from watchdog debug IDs:
  - `roomchat-e9da089d152b`
  - `roomchat-f972b07e9834`
  - Error: `Room turn harness contract violation: Unknown property: turn_logs[6].turn_prompt`
- Added `turn_prompt` as an allowed optional boolean property on room-turn-harness `turn_logs` entries.
- Added schema contract assertion coverage and harness payload coverage for `turn_prompt`.

## Verification
- `cd /var/www/html/dungeoncrawler && vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Schema/QuestPayloadSchemaDefinitionTest.php`
- `cd /var/www/html/dungeoncrawler && vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml --filter testBuildGmAndHarnessPayloadsProduceValidatedCanonicalStructures /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/RoomChatServiceNpcResolutionTest.php`
- `cd /var/www/html/dungeoncrawler && vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Controller/RoomChatControllerProgressTest.php`

## Next actions
- Monitor room-chat stream logs for recurrence of `room turn harness contract violation` errors.

## Blockers
- None

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
