- Status: done
- Summary: Shipped player free-speech encounter room chat so player messages no longer spend actions or require turn ownership, while NPC dialogue remains turn-locked.

## Completed outcome
- Added CEO tracking item: `20260619-player-free-speech-chat`
- Repository shipped: `dungeoncrawler-content`
- Ordinary player room chat now routes through a GM-subsystem free-chat path instead of canonical encounter `talk`.
- Deterministic turn-control chat still routes to canonical actions.
- NPC room dialogue remains deferred / turn-locked.

## Validation references
- `php -l src/Service/GameCoordinatorService.php`
- `php -l src/Service/GameMasterSubsystemService.php`
- `php -l src/Controller/RoomChatController.php`
- `php -l src/Service/RoomChatService.php`
- `php -l tests/src/Unit/Service/GameMasterSubsystemServiceTest.php`
- `php -l tests/src/Unit/Controller/RoomChatControllerProgressTest.php`
- `node tests/player_free_chat_contract_test.js`
- `node tests/chat_panel_progress_contract_test.js`
- `node tests/action_rail_chat_pending_contract_test.js`

## Notes
- Composer-based PHPUnit execution was not available in this environment because the module requires the private `drupal/ai_conversation` package and no installable lockfile/vendor tree was present.
- Follow-up hardening review fixed two concrete regressions before closeout:
  - free-chat resync now uses actor-scoped `available_actions` / `action_contract`
  - non-stream JSON room chat now completes deferred NPC interjections before returning, matching streamed behavior
- Second hardening review fixed one adjacent transcript contract mismatch:
  - quest narrator notes now use the same narrator/narrative classification in both legacy room chat and normalized session chat
- Deep contract audit fixed three more concrete boundary issues:
  - legacy scalar chat entries can no longer crash room history channel filtering
  - character narrative / GM-private transcript endpoints now enforce character ownership instead of only campaign membership
  - normalized session writes are limited to writable session types, and the active V2 chat panel now queues follow-up room messages instead of hard-failing mid-response
- Final transcript hardening added UTF-8 substitution to room-chat JSON / NDJSON emission so malformed persisted transcript bytes cannot take down the chat surface during encoding
- Added room-history diagnostics on both server and browser paths so any remaining production 500 now yields concrete request/exception context and the backend response body on the next repro
- Fixed the room-chat JSON hardening order itself: `JsonResponse` now gets UTF-8 substitution flags before transcript data is assigned, so invalid transcript bytes cannot fail during response construction.
- `RoomChatController` now resolves the GM subsystem lazily, reducing the room-history GET dependency surface to the read path instead of the full room-chat action stack.
- `RoomChatController` now also resolves `GameCoordinatorService` lazily, so room-history GETs avoid constructing the encounter runtime unless a progress/action path explicitly needs it.
- Encounter progress snapshot/prefix lookup now fails soft with logging instead of aborting the streamed room-chat turn when coordinator state lookup throws.
- The main room chat UI now uses the non-stream JSON transport instead of NDJSON while the stream-only room-chat backend fault remains under investigation; this restores the primary room chat path without changing the free-speech / NPC turn-lock gameplay contract.
- The non-stream room chat path no longer synchronously completes deferred NPC interjections, which keeps NPC dialogue turn-locked and removes an extra failure surface from the main room request.
- Non-stream room chat POST failures now return a correlatable `roomchat-...` debug token and log the same token server-side with full request context, closing the last blind spot in the main room request path.
- JSON POST failures now also expose the exception class/message inside the debug payload, and the browser logs that payload automatically for direct repro-driven diagnosis.
- Final room-history hardening also skips malformed legacy dungeon snapshot payloads during room lookup, covering one remaining likely fatal path before transcript normalization even begins
- That skip path now logs a clear warning with campaign, requested room, dungeon id, payload size, and decoded type whenever it is used.
- Visible room narration now keeps `Game Master` as the public label while using `Narrator` for the encounter turn-role/prefix, matching the turn model where narration stays on the narrator-owned opening slot.

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
