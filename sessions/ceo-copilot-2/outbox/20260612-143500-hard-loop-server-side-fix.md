- Status: done
- Summary: Completed server-side hardening for strict round/turn loop visibility/order in encounter chat and closed the active architect incident item.

## Implemented
- Repository: `dungeoncrawler-content`
- Commit: `fd8bf35` (pushed to `main`)
- Core changes:
  - Progress transcript prefixing now consumes authoritative server round/turn snapshots embedded in progress context.
  - Streamed deferred NPC progress (`npc_reactions_generating`) now prefixes from result-state snapshots, not drifting live lookups.
  - Encounter-phase room harness now suppresses out-of-turn NPC chatter so hard loops remain governed by encounter turn authority.

## Verification
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Controller/RoomChatControllerProgressTest.php`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Schema/QuestPayloadSchemaDefinitionTest.php`
- `node tests/chat_panel_progress_contract_test.js`
- `node tests/chat_panel_line_contract_test.js`
- `vendor/bin/drush -q php:script /home/ubuntu/forseti.life/dungeoncrawler-content/tests/chat_session_test.php`

## Artifact updates
- Marked architect inbox item complete:
  - `sessions/architect-copilot/inbox/20260612-roomchat-turn-gate-npc-turn-stall/status.md`

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
