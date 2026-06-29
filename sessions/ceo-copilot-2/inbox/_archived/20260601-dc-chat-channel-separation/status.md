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
