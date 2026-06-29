# Status

- status: completed
- created_at: 2026-06-01T00:00:00+00:00
- current_phase: line item completed

## Notes

This line item is complete. The V2 chat panel no longer treats the active tab as the implicit destination for every incoming line, which was causing Room / My Story / Party / GM Secret / Dice Log to converge on the same transcript over time.

## Implementation slice completed

- `ChatPanel` now routes `chat:message-received` and targeted `chat:system-message` events through explicit session-view/channel targets instead of appending them into whichever tab is active.
- Untargeted local UI notices remain visible to the player but are no longer remembered into cached transcript state.
- Encounter narration from `dungeoncrawler:game-events` is now appended only to the Room transcript.
- Authoritative room-scene emitters in `EncounterSystem`, `NavigationSystem`, and `PlayerAutomation` now declare `view: room` / `channel: room` so their transcript lines cannot leak into narrative, party, GM, or dice feeds.
- Quest toasts now use the local notice path instead of being misclassified as transcript chat lines.
- Focused validation passed for the touched V2 JS files and the existing Drupal chat session hierarchy regression (`vendor/bin/drush php:script /home/ubuntu/forseti.life/dungeoncrawler-content/tests/chat_session_test.php`).

## Follow-up cleanup completed

- Normalized chat target/channel ownership so non-room session views no longer inherit the active Room subchannel as part of their cache identity.
- `Room` keeps channel-scoped transcript state, while `My Story`, `Party`, `GM Secret`, and `Dice Log` now each resolve to one stable view-owned transcript key regardless of which Room subtab was active when the panel rendered.

## Channel surface simplification completed

- Removed the exposed `My Story` session tab from the V2/runtime chat surface and deleted the `ChatPanel` narrative-view branches that only existed to support that tab.
- Renamed the `Dice Log` tab to `System` while keeping `system-log` as the underlying system/mechanical data source.
- Updated chat copy so the System tab is described as the home for system messages, dice rolls, checks, and mechanical output.
- Updated the GM channel copy to reflect the intended contract: messages there go directly to the GM, the GM should answer there, and the GM should use its tools to resolve issues raised in that channel.

## Chat turn-status removal completed

- Removed the `chat-turn-status` block from the V2 and demo chat templates.
- Deleted the `ChatPanel` turn-status DOM bindings, turn-status event subscription, remembered room turn-sequence cache, pending turn-status helper methods, and the related sync call sites.
- This leaves the chat panel responsible only for transcript/channel presentation while round-turn ownership stays with the dedicated encounter/combat UI.

## Party and GM transcript purity tightened

- Untargeted `chat:system-message` traffic no longer renders into whichever tab happens to be active.
- `ChatPanel` now routes that fallback notice stream into `System`, which keeps Party limited to party-member / party-NPC chat and keeps GM limited to the PC ↔ GM conversation.
- This aligns the non-room channels with the stricter user contract that Party and GM should not display unrelated system or room traffic.
## 2026-06-01 - Phase 7 - Quest tab auto-refresh repaired

Root cause: the V2 room/chat flow still relied on legacy hexmap quest hooks, while `GameShell` handled `quest_updates` with a brittle local bucket merge. That merge could not reliably move a quest between `offers`, `leads`, and `active` when a grant changed its state, so the Quest tab would stay stale until a manual refresh.

Implemented:
- added authoritative `GameShell.refreshQuestJournalFromApi()` backed by the server quest-journal endpoint
- added `GameShell.applyQuestUpdates()` and exposed both methods through the V2 hexmap shim
- replaced the local `quest_updates` merge in `GameShell` chat handling with the authoritative refresh path
- continued to emit `quest:progress-updated` from the refreshed server-owned `questSummary` so `QuestPanel` rerenders immediately

Validation:
- `node --check js/v2/GameShell.js`
- `node --check js/v2/panels/ChatPanel.js`
- `cd /var/www/html/dungeoncrawler && vendor/bin/drush php:script /home/ubuntu/forseti.life/dungeoncrawler-content/tests/chat_session_test.php`

## 2026-06-01 - Phase 8 - strict session-view contract hardening

Root cause: V2 still allowed loose async session-view rendering. Non-room view payloads did not carry a hard ownership contract, and the client could accept late/stale Party / GM / System responses after the active target had changed.

Implemented:
- `ChatSessionController` now returns explicit Party / GM / System ownership metadata with a formal `contract` block plus mirrored top-level fields (`contract_version`, `view`, `channel_key`, `session_type`, `campaign_id`, `character_id`, `session_id`)
- `ChatSessionApi` now enforces that contract and rejects missing / mismatched session-view payloads instead of tolerating legacy flat responses
- `ChatPanel` now captures the request target/context up front, validates payload ownership before rendering, and ignores stale async responses that no longer own the active tab
- removed the permissive `user:session-view-requested` → `session:view-data` bus repaint path from `GameShell` / `ChatPanel`
- added focused regression coverage for the controller session-view contract helper and updated JS harnesses to validate the stricter ownership contract

Validation:
- `cd /home/ubuntu/forseti.life/dungeoncrawler-content && node --check js/ChatSessionApi.js && node --check js/v2/GameShell.js && node --check js/v2/panels/ChatPanel.js`
- `cd /home/ubuntu/forseti.life/dungeoncrawler-content && php -l src/Controller/ChatSessionController.php && php -l tests/chat_integration_test.php`
- `cd /home/ubuntu/forseti.life/dungeoncrawler-content && node tests/chat_session_api_test.js`
- `cd /home/ubuntu/forseti.life/dungeoncrawler-content && node tests/chat_panel_line_contract_test.js`
- `cd /home/ubuntu/forseti.life/dungeoncrawler-content && node tests/action_rail_search_binding_test.js`
