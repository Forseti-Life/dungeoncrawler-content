- Status: done
- Summary: Completed 5-Whys RCA and shipped root-cause fix for non-canonical/out-of-order room chat by enforcing single encounter transcript authority.

## RCA (5 Whys)
1. Out-of-order chat persisted because room chat mixed encounter transcript lines with room-turn-harness synthetic turn logs.
2. Turn harness produced its own turn order/current-turn sequence independent of canonical encounter turn ownership.
3. Those harness lines were persisted and streamed as transcript-visible room chat.
4. Client ordering could not fully reconcile mixed-source lines that represented different authority models.
5. We repeatedly fixed symptoms (prefix/sort/contract) while keeping dual transcript writers active.

## What changed
- `RoomChatService::getChatHistory()` now excludes room `internal_log` and `turn_prompt` harness artifacts from player-visible room history, while preserving deterministic `sequence_index` for visible lines.
- `RoomChatController::emitStreamedTurnResult()` now filters harness diagnostics (`internal_log` / `turn_prompt`) from streamed `system_message` events and from returned `turn_logs`.
- `ChatPanel` now suppresses rendering of room turn-log artifacts (`internal_log` / `turn_prompt`) even if present in payloads.
- `ChatPanel` pending player-line prefix was corrected to canonical order (`Round ?: Turn ?: Actor ...`) and local/non-transcript message classes are no longer auto-prefixed by client encounter context.

## Verification
- `node tests/chat_panel_line_contract_test.js`
- `node tests/chat_panel_progress_contract_test.js`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Controller/RoomChatControllerProgressTest.php`
- `vendor/bin/phpunit -c /home/ubuntu/forseti.life/dungeoncrawler-content/phpunit.xml /home/ubuntu/forseti.life/dungeoncrawler-content/tests/src/Unit/Schema/QuestPayloadSchemaDefinitionTest.php`

## Pushed code
- `dungeoncrawler-content` commit `44601b2` on `main`

## Next actions
- Watch campaign 230+ live transcripts for recurrence of harness-style `Current turn:` / `Turn order:` room lines in the player chat panel.

## Blockers
- None

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
