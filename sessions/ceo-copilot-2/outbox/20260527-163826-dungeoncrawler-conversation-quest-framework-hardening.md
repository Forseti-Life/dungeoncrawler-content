- Status: done
- Summary: Completed the Dungeoncrawler conversation and quest framework hardening pass by standardizing the touched quest runtime surfaces around lead/offered/active states, preventing rumor dialogue from auto-starting quests, and hardening canonical room/narrative session reads so alias scopes reuse one room chain.

# Dungeoncrawler conversation + quest framework hardening

## What changed
- Standardized the touched quest runtime/client surfaces to `quest-summary-v2` with explicit `active`, `offers`, and `leads` buckets, and removed `available` handling from the primary room/quest summary flow that this inbox item covered.
- Enforced quest acceptance boundaries so quest starts now require `offered`, while active quest progress remains limited to `active` and `ready_for_turn_in`.
- Removed the brokered storyline auto-activation path from room dialogue so rumor and lead chatter surfaces work without silently starting multiple quests.
- Hardened canonical chat-session reads by resolving room aliases before loading room and character-narrative sessions, which keeps one logical room chain across alias scopes.
- Tightened merchant follow-up routing so short transactional continuations like drink orders stay on the merchant transaction path.

## Files changed
- `/home/ubuntu/forseti.life/dungeoncrawler-content/src/Service/ChatSessionManager.php`
- `/home/ubuntu/forseti.life/dungeoncrawler-content/src/Controller/ChatSessionController.php`
- `/home/ubuntu/forseti.life/dungeoncrawler-content/src/Controller/HexMapController.php`
- `/home/ubuntu/forseti.life/dungeoncrawler-content/src/Service/QuestTrackerService.php`
- `/home/ubuntu/forseti.life/dungeoncrawler-content/src/Controller/QuestTrackerController.php`
- `/home/ubuntu/forseti.life/dungeoncrawler-content/src/Service/QuestGeneratorService.php`
- `/home/ubuntu/forseti.life/dungeoncrawler-content/src/Service/RoomChatService.php`
- `/home/ubuntu/forseti.life/dungeoncrawler-content/js/hexmap.js`
- `/home/ubuntu/forseti.life/dungeoncrawler-content/tests/chat_session_test.php`
- `/home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/RoomChatServiceNpcResolutionTest.php`
- `/home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Service/StateValidationServiceTest.php`

## Validation
- Focused module PHPUnit set passed under the module PHPUnit config.
- `vendor/bin/drush php:script /home/ubuntu/forseti.life/dungeoncrawler-content/tests/chat_session_test.php` passed from `/var/www/html/dungeoncrawler`.

## Needs from Supervisor
- None.

---
- Agent: ceo-copilot-2
