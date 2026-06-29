# Status

- status: done
- created_at: 2026-06-19T13:35:00+00:00
- current_phase: complete - player free speech shipped

## Notes

Created from Board direction after analysis of encounter chat coupling. The current Dungeoncrawler stack routes ordinary player room chat through canonical encounter `talk`, which spends action economy and enforces turn ownership. This work item tracks decoupling player speech from action economy while preserving deterministic turn-control and NPC dialogue turn-locking.

### 2026-06-19 - phase 1 complete
- Added a CEO work item and implemented a free-player-room-chat route in the GM subsystem instead of routing ordinary player room chat through canonical encounter `talk`.
- Preserved deterministic turn-control routing (`delay` / `wait`) as canonical encounter actions.
- Kept NPC dialogue turn-locked by forcing deferred NPC interjections for free player room chat.

### 2026-06-19 - phase 2 complete
- Added focused regression coverage for the new free player chat contract and updated unit expectations around the GM subsystem route metadata.
- Validated changed PHP files with `php -l`.
- Ran focused node regressions:
  - `node tests/player_free_chat_contract_test.js`
  - `node tests/chat_panel_progress_contract_test.js`
  - `node tests/action_rail_chat_pending_contract_test.js`

### 2026-06-19 - phase 3 review hardening complete
- Hard-review pass found and fixed an actor-scoped action availability resync bug in the free-chat response path.
- Hard-review pass also found and fixed the non-stream JSON deferred-NPC completion gap so plain room chat and streamed room chat now stay behaviorally aligned.

### 2026-06-19 - phase 4 review hardening complete
- Second hard-review pass found and fixed a narrator contract mismatch in quest narrator notes so legacy room chat and normalized session chat now both classify those lines as narrator/narrative rather than splitting between narrator and GM/system.

### 2026-06-19 - phase 5 deep contract audit complete
- Hardened room chat history channel filtering so malformed legacy scalar entries cannot crash the `/room/{room_id}/chat` GET path before normalization.
- Locked normalized session writes down to explicitly writable session types and added character-ownership enforcement for character narrative / GM-private transcript endpoints.
- Brought the active V2 chat panel back into parity with legacy queued-room-chat behavior by queuing follow-up room messages instead of hard-failing while a response is still in flight.

### 2026-06-19 - phase 6 transcript encoding hardening complete
- Hardened `RoomChatController` JSON and NDJSON transcript emission with `JSON_INVALID_UTF8_SUBSTITUTE` so dirty persisted transcript bytes cannot crash room history or streamed room chat responses during encoding.

### 2026-06-19 - phase 7 room history diagnostics complete
- Added explicit server-side logging for room chat history request rejections/failures with campaign, room, channel, character, and exception context.
- Added browser-side `GameShell` logging of non-OK room history responses so the next production repro exposes the backend response body instead of only a generic 500.
- Corrected the UTF-8-safe `JsonResponse` hardening order in `RoomChatController` so encoding options are applied before transcript payload data is assigned; this closes a bug where invalid UTF-8 could still throw during response construction.
- Refactored `RoomChatController` to resolve the GM subsystem lazily so room-history GET requests no longer instantiate the full player-chat action graph during controller construction.
- Refactored `RoomChatController` to resolve `GameCoordinatorService` lazily as well, so room-history GET requests no longer instantiate the encounter runtime stack during controller construction.
- Made encounter progress snapshot/prefix lookup fail-soft in `RoomChatController` so progress-line decoration can no longer throw and abort streamed room chat turns.
- Switched the main room chat surface in `js/v2/panels/ChatPanel.js` to the non-stream JSON transport, keeping other channels eligible for NDJSON while avoiding the still-unresolved stream-only backend fault on room chat.
- Removed synchronous deferred-NPC completion from the non-stream room chat path so room chat no longer drains NPC turn-locked dialogue during the main player request.
- Added debug-id diagnostics to the non-stream room chat POST path so JSON transport failures now log with request context server-side and surface a correlatable `roomchat-...` token back to the browser.
- Expanded the JSON POST debug payload to include exception class/message and added browser-side console logging of that payload so the next room-chat failure exposes the concrete backend exception directly in the console.

### 2026-06-19 - phase 8 room snapshot scan hardening complete
- Hardened `loadLatestDungeonSnapshot()` room matching so malformed legacy `dungeon_data` rows that decode to non-array scalars are skipped during room lookup instead of causing a fatal offset read while scanning candidate dungeon snapshots.
- Added explicit warning logging when malformed snapshot payloads are skipped so production logs clearly show when this safety path is exercised.
- Corrected the visible room-GM transcript contract so the **visible label remains `Game Master`** while the **encounter turn-role/prefix remains `Narrator`**. This preserves the public naming the Board wants while keeping narration on the narrator-owned turn slot.

### 2026-06-19 - phase 9 room turn harness normalization complete
- Fixed the non-stream room-chat 500 caused by persisted transcript-only fields leaking into the strict `room_turn_harness` contract payload.
- Normalized harness `messages` entries server-side before validation so fields like `sequence_index` and `message_class` are stripped while canonical NPC interjection content remains intact.
- Added focused unit coverage to lock the regression and validated the changed PHP files plus the targeted `RoomChatServiceNpcResolutionTest` harness payload cases.

### 2026-06-19 - phase 10 quest completion narrator routing fix complete
- Fixed the quest objective/quest completion narrator-note path so it resolves the latest dungeon snapshot that actually contains the quest room instead of blindly choosing the newest dungeon row.
- Added a fallback room-matching snapshot re-resolution step before writing legacy room chat, preventing narrator-note persistence from aborting quest progression when onboarding and live map snapshots coexist.
- Added focused unit coverage for room-matched narration context resolution, rebuilt Drupal caches, and repaired the stuck live quest in campaign `266` so it now appears in the completed journal section.

### 2026-06-19 - phase 11 quest completion announcement handoff complete
- Fixed the Search collectible quest path so it goes back through the canonical `QuestTrackerService` objective-progress flow instead of silently bypassing quest-completion side effects.
- Propagated server-authored narrator quest notes through the canonical quest progress result and into Search narration, so the active transcript now immediately includes a narrator quest-completed announcement when the final quest task resolves.
- Added focused unit coverage for both the canonical quest-progress narrator-note return path and the Search narration handoff that appends the quest-completion announcement.

### 2026-06-19 - phase 12 search reward refresh handoff complete
- Fixed the encounter Search action-rail client path so it waits for the authoritative character refresh and quest journal refresh after a successful Search instead of fire-and-forget refreshing only the character sheet request.
- Mirrored the same wait/refresh behavior in the legacy `hexmap.js` search path so both active clients surface newly granted quest rewards and completed quest state immediately after Search resolves.
- Rebuilt Drupal caches after the JS handoff update; the broader search binding contract file still has unrelated pre-existing failures, so this phase used direct syntax validation on the touched JS files.

### 2026-06-19 - phase 13 v2 search cache bust complete
- Verified on live campaign `267` / character `988` that the quest reward state already persisted server-side (`XP`, currency, and the extra `healing_potion_minor` item row were all present), confirming the remaining bug was stale browser code rather than missing reward writes.
- Bumped the `hexmap-v2.js` → `GameShell.js` import version and the `GameShell.js` → `EncounterSystem.js` import version so browsers fetch the updated encounter Search refresh path instead of reusing the pre-fix cached modules.
- Rebuilt Drupal caches after the import-version bump so the refreshed search bundle is immediately available.

### 2026-06-19 - phase 14 character sheet reward rerender complete
- Fixed the V2 `CharacterPanel` so it re-renders the launch character summary on `character:updated` events instead of only during initial `game:init`.
- This closes the remaining character-sheet-tab gap where XP, gold, and other reward-backed fields could change in authoritative state without the visible sheet updating after Search completion.
- Added a focused CharacterPanel contract test and rebuilt Drupal caches after bumping the `CharacterPanel.js` import chain so browsers fetch the refreshed panel module immediately.

### 2026-06-19 - phase 15 character sheet loop fix complete
- Removed the self-triggering `character:updated` emit from `CharacterPanel.showLaunchCharacter()`, which was recursively re-entering the panel render path and spamming the console.
- Kept the one-way refresh contract intact: `GameShell.loadCharacterFromApi()` still emits `character:updated`, and `CharacterPanel` still listens to that event to rerender authoritative reward-backed state.
- Added a focused contract assertion preventing `CharacterPanel` from re-emitting `character:updated`, then rebuilt Drupal caches after bumping the CharacterPanel import chain again so browsers load the loop fix immediately.

### 2026-06-19 - phase 16 character update payload handoff complete
- Hardened the `character:updated` event so `GameShell.loadCharacterFromApi()` now emits the freshly hydrated `launchCharacter` payload directly instead of forcing `CharacterPanel` to resolve that state indirectly from the shim.
- This closes the last XP display gap where the panel could rerender from stale launch-character data even after the authoritative API refresh had already returned the updated reward state (`basicInfo.experiencePoints`).
- Added a focused contract assertion for the payload-carrying `character:updated` event and rebuilt Drupal caches after bumping the `GameShell.js` import chain again so browsers fetch the updated module immediately.

### 2026-06-19 - phase 17 runtime character state id fix complete
- Corrected `GameShell.resolveLaunchCharacterStateId()` so it no longer falls back to the source `character_id` field (`324`) when resolving the authoritative state row for the launched campaign character (`988`).
- This prevents V2 from fetching and rerendering the wrong character-state record during refresh, which could leave XP stuck at `0` even while the live campaign-runtime row already held the awarded XP in `basicInfo.experiencePoints`.
- Added a focused GameShell contract test for the runtime state-id resolution order and rebuilt Drupal caches after bumping the `GameShell.js` import chain again so browsers fetch the corrected state-id logic immediately.

### 2026-06-19 - phase 18 hexmap entrypoint library version bump complete
- Identified that Drupal was still serving the old `hexmap-v2` library version (`20260616-v2-gm-nonturn-prefix-2`), which meant the browser never requested the newer `GameShell.js` / `CharacterPanel.js` fixes at all.
- Bumped the `hexmap_v2` library version in `dungeoncrawler_content.libraries.yml` so the page now emits a fresh entrypoint URL and forces browsers to fetch the updated runtime-refresh stack.
- Rebuilt Drupal caches immediately after the library version bump so the new `hexmap-v2` asset URL is live.

### 2026-06-19 - phase 19 merchant sell-price fallback complete
- Fixed merchant sell-price calculation so sellable inventory no longer collapses to `0 cp` when runtime inventory rows omit `price_gp`.
- The merchant transaction backend now falls back through `price_cp`, inventory metadata price fields, and catalog/base item price fields before applying the half-value sell rule (or full-price subtype exception), restoring the intended half-base-value offers in the Merchant tab.
- Added focused unit coverage for the catalog-price fallback case and validated the touched merchant transaction service/test pair.

### 2026-06-19 - phase 20 search no longer auto-starts lead quests
- Fixed the Search collectible quest targeting path so it only scans `active` quests; lead/offered quests can no longer be silently auto-started and progressed just because the player pressed Search in the room.
- Repaired live campaign `268` by removing the unintended progress row for `collect_spellbooks_268_6a3592630d80b` and returning that quest to the `lead` bucket while keeping `tavern_storyline_leads_268_6a3592630c9a8` in `completed`.
- Added a focused regression contract that locks Search collectible quest targeting to active quests only.

### 2026-06-19 - phase 21 search narrowed to quest items only
- Removed explicit Search action behavior that revealed hidden entities, hazards, and sensory room details; Search now only checks for quest-item collectibles in the current room and otherwise returns the standard no-find narration.
- Updated focused exploration Search tests to assert the new no-sensory/no-hazard contract while preserving quest-item discovery and quest-completion narrator announcement coverage.
- Rebuilt Drupal caches after the server-side Search scope change; the broader JS search binding contract still has unrelated pre-existing failures outside this narrowed Search scope update.

### 2026-06-19 - phase 22 repeated empty search refresh guard complete
- Fixed the repeated Search delay by stopping the encounter Search client paths from forcing a full character-sheet and quest-journal refresh when Search returns no discoveries.
- Both the V2 encounter system and legacy `hexmap.js` now only run those heavier refreshes when Search actually returns quest-item discoveries, which is the only remaining useful Search outcome after narrowing Search to quest items only.
- Added a focused refresh-guard contract test and rebuilt Drupal caches after the client-side Search refresh change.

### 2026-06-19 - phase 23 search system-log refresh restored
- Restored Search dice/check visibility in the chat system-log channel by invalidating and prefetching the `system-log` session view after encounter Search actions complete.
- Confirmed the “many searches and nothing” behavior in campaign `269` is not a stale refresh issue: `collect_spellbooks_269_6a35945f6e59b` is currently still a `lead`, not an `active` quest, so Search correctly does not discover its room items until that lead is explicitly advanced into an active quest state.
- Rebuilt Drupal caches after the client-side Search system-log refresh fix.

### 2026-06-19 - phase 24 global encounter dice-log refresh complete
- Generalized the system-log refresh through the shared encounter action helpers so successful rolled encounter actions refresh the `system-log` chat view even when they are not Search actions.
- In V2, the shared coordinator action helper now invalidates/prefetches `system-log` on successful encounter actions; in legacy `hexmap.js`, the shared `performCombatAction()` helper now does the same for successful encounter action API responses.
- Added focused contract coverage for the shared helper behavior and rebuilt Drupal caches after the broader dice-log refresh change.

### 2026-06-19 - phase 25 Marta lead activation and journal quest normalization complete
- Direct questgiver conversations now activate that questgiver's surfaced lead quest: when the giver of a `lead` quest talks to the player, the quest is promoted to `offered` and then started immediately for that character.
- Normalized the tavern scholar quest from “collect lost spellbooks” into a single-item recovery quest for **Marta's Journal** by fixing the template target count to `1`, updating the tavern room template quest title, and leaving only one associated collectible item on the tavern content template.
- Repaired live campaign `269` so `collect_spellbooks_269_6a35945f6e59b` is now active for character `996`, renamed to **Recover Marta's Journal**, and backed by a single associated room item.

### 2026-06-19 - phase 26 canonical DB library sync complete
- Synced the canonical quest-template DB row for `collect_spellbooks` to the source file so new campaigns now generate **Recover Marta's Journal** with deterministic rewards (`73 XP`, `6 gp`, `healing_potion_minor`) instead of inheriting stale randomized template data from the database.
- Synced the canonical starter tavern room row in `dungeoncrawler_content_rooms` so new campaigns pull the updated Marta quest title and single associated quest item directly from the canonical room library, not just from the source JSON on disk.
- Repaired the already-generated campaign `269` quest row again so its generated objectives and rewards now match the canonical library contract, then rebuilt Drupal caches after the canonical DB sync.

### 2026-06-19 - phase 27 encounter narrator chatter suppressed
- Suppressed `round_start`, `turn_start`, and `choose_not_to_act`/`npc_choose_not_to_act` encounter narrator chatter from the room chat transcript so those bookkeeping lines no longer print into the visible chat log.
- Preserved the explicit **System** player-turn prompt (`It's your turn, ...`) by making the prompt logic independent of whether the underlying turn-start narrator line is rendered.
- Bumped the V2 `ChatPanel.js` import chain and rebuilt Drupal caches so browsers fetch the updated transcript suppression behavior immediately.

### 2026-06-19 - phase 28 questgiver surfacing activation fix complete
- Fixed the main room-dialogue quest surfacing path so when a surfaced quest is spoken by its own quest giver, the handoff now promotes that quest and starts it immediately instead of leaving it stranded in `lead`.
- This closes the remaining gap that was still leaving new campaigns like `271` with **Recover Marta's Journal** stuck in `lead` even after speaking to Marta, despite the direct questgiver activation rule already existing elsewhere.
- Repaired live campaign `271` by promoting `collect_spellbooks_271_6a35a1f8bf111` to `offered`, starting it for character `1004`, and verifying it now appears under `active` rather than `lead`.

### 2026-06-19 - phase 29 Marta quest moved from preseed to on-talk creation complete
- Removed the Marta journal quest from starter campaign pre-seeding, so new campaigns no longer auto-populate that quest in the log before the player has talked to her.
- Added room-questgiver materialization in `RoomChatService` so speaking with Marta can create the authored tavern quest row on demand from the canonical room/quest template library, after which the existing giver activation logic promotes it into `active`.
- Rebuilt Drupal caches after the new on-talk creation path so subsequent campaigns use the deferred Marta quest flow instead of the old auto-seeded lead row.
