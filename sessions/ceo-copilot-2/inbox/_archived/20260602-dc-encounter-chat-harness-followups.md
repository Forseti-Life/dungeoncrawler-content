# Dungeoncrawler — Encounter harness master, chat harness subordinate: follow-ups

Date: 2026-06-02
Owner seat: ceo-copilot-2

## Context (what we already fixed)
From recent sessions we hardened the single-master contract:
- Encounter framework is authoritative (GameCoordinator + EncounterPhaseHandler).
- Room chat POST returns canonical `room-chat-response-v1` envelope even when routed via coordinator (`/api/game/{campaign}/action type=talk`).
- Verbose round/turn logging + deterministic NPC per-turn output were added so the chat window shows what the encounter harness is doing without requiring refresh.

Key references:
- Outbox: `sessions/ceo-copilot-2/outbox/20260602-172213-dc-room-chat-envelope-fix.md`
- Outbox: `sessions/ceo-copilot-2/outbox/20260602-182930-dc-npc-turn-output-ordering.md`
- Outbox: `sessions/ceo-copilot-2/outbox/20260602-200500-dc-turn-event-rendering-and-start-combat.md`
- Tests: `dungeoncrawler-content/tests/encounter_system_logging_contract_test.js`, `tests/chat_panel_line_contract_test.js`

## What still needs done (gap closure)

### Update (2026-06-07, phase 2)
Live campaign 211 desync investigation confirmed client-side encounter state drift on action rail posts:
- Action rail Search (and other coordinator actions) was posting stale `client_state_version` and repeatedly receiving 422 mismatch responses.
- This produced user-visible turn/round confusion because client and server state diverged until hard refresh.

Shipped fix:
- `GameCoordinatorApi` now surfaces structured error payloads (`status` + parsed JSON body) on non-2xx responses.
- `EncounterSystem` now routes coordinator action posts through a state-resync helper that:
  1) detects 422 state-version mismatch,
  2) applies authoritative server state,
  3) retries once with updated state version.

Code: `dungeoncrawler-content` commit `8837100` (pushed to `main`)

### Update (2026-06-07, phase 3)
Quest task UI progress refresh failure traced to a v2 client regression:
- `refreshQuestJournalFromApi()` called `normalizeQuestSummaryPayload()`.
- `normalizeQuestSummaryPayload()` referenced `QUEST_SUMMARY_SCHEMA_VERSION`, but that constant was missing in `js/v2/utils/quest-utils.js`.
- Result: `ReferenceError: QUEST_SUMMARY_SCHEMA_VERSION is not defined`, preventing quest journal refresh after completion events.

Shipped fix:
- Added the missing constant export in `js/v2/utils/quest-utils.js` so quest summary normalization no longer crashes.

Code: `dungeoncrawler-content` commit `99e67eb` (pushed to `main`)

### Update (2026-06-07, phase 4)
Addressed runtime 404 for campaign/roster list background image:
- CSS referenced non-existent files:
  - `/themes/custom/dungeoncrawler/build/assets/images/site/campaigns/page-background.png`
  - `/themes/custom/dungeoncrawler/build/assets/images/site/characters/page-background.png`
- Repointed both selectors to existing theme asset:
  - `/themes/custom/dungeoncrawler/build/assets/images/dungeon-crawler-hero.png`

Code: `dungeoncrawler-content` commit `8c41ba4` (pushed to `main`)

### Update (2026-06-07, phase 5)
Follow-up on recurring quest journal refresh `ReferenceError` in live logs:
- Active production bundle line (`v2-action-dispatch-refactor-1`) was still loading stale `quest-utils` module cache in some browsers.
- Added cache-bust query suffixes to quest-utils imports used by:
  - `js/v2/GameShell.js`
  - `js/v2/panels/QuestPanel.js`
- Bumped `hexmap-v2` library + entry import version to force module graph reload.

Code: `dungeoncrawler-content` commit `1150ac8` (pushed to `main`)

### Update (2026-06-07, phase 6)
Addressed chat UX gap where users had no strong in-panel signal that server processing was in progress:
- Added explicit chat-log pending banner text: `Waiting for server response…` whenever there is a visible pending chat request.
- Added pending-line visual pulse (`chat-line--pending`) so in-flight player/progress lines are visibly marked while waiting.
- Synced pending indicator state on request lifecycle and chat context switches (channel/session-view changes) so the signal is accurate to what the player is viewing.
- Bumped v2 asset cache-bust versions for immediate rollout of JS/CSS updates.

Code: `dungeoncrawler-content` commit `32f2a48` (pushed to `main`)

### Update (2026-06-07, phase 7)
Quest-tab contract review for "missing detail" report:
- Verified server quest-summary contract is still conformant and includes rich objective detail (`next_step`, `completion_criteria`, labels, progress) for active/lead quests in live campaign data.
- Root-cause path for reduced detail in UI: character-sheet quest tab could remain on stale/partial quest state until a later chat-triggered refresh, and QuestPanel module path was not explicitly cache-busted.
- Shipped fix:
  - Force canonical quest-journal refresh when top-level Character tab opens.
  - Force canonical quest-journal refresh when sidebar Quests tab is activated (manual or programmatic activation).
  - Added explicit QuestPanel cache-bust import version and rolled `hexmap-v2` entry/library version.

Code: `dungeoncrawler-content` commit `4cc20a1` (pushed to `main`)

### Update (2026-06-07, phase 8)
Follow-up on campaign 217 objective checkmark behavior (`speak_to_eldric`):
- Verified live quest state was correctly updated server-side (`objective_states.speak_to_eldric.completed = true`).
- Found UI regression in active-quest rendering: completed objectives were being filtered out before rendering, so completed talk steps were not retained with a ✅ in the active quest objective list.
- Shipped fix:
  - Keep completed objectives in active quest rendering (`flattenQuestObjectives(..., { includeCompleted: true })`) in both v2 panel and legacy renderer.
  - Rolled v2 cache-bust versions to force fresh client module load.

Code: `dungeoncrawler-content` commit `056104a` (pushed to `main`)

### Update (2026-06-07, phase 9)
Encounter harness strict-ordering/action-economy follow-up (campaign 218):
- Root cause 1: room-chat harness could inject NPC interjections during a player `talk` action while encounter phase was active, creating out-of-turn transcript noise.
- Root cause 2: room-scene `talk` action forced `actions_remaining = 0`, effectively auto-ending turn instead of consuming exactly one action.
- Root cause 3: some narrator/system mechanical lines did not bind `speaker_ref` to the acting entity, allowing prefix ownership drift.

Shipped fix:
- `EncounterPhaseHandler::processTalk()` now enforces encounter-master ownership by deferring room-chat NPC interjections while in encounter phase.
- `talk` now consumes exactly 1 AP and does not auto-end the room-scene turn.
- Narrator/system log entries for per-actor mechanical output now stamp `speaker_ref` with the actor entity id for stable prefix ownership.
- Updated encounter unit coverage for talk contract to assert explicit turn advancement and single-action AP spend.

Code: `dungeoncrawler-content` commit `31c32fb` (pushed to `main`)

### Update (2026-06-07)
Quest collectible and completion-notification follow-up is now closed:
- Restored automatic turn-start Search collectible pickup (removed explicit-only collection gate).
- Added Narrator chat notifications for objective completion and quest completion via `QuestTrackerService`, routed to room chat when available with system-log fallback.
- Hardened notifier wiring to support environments without constructor DI for `chat_session_manager`.

Code: `dungeoncrawler-content` commit `9612c43` (pushed to `main`)

### Update (2026-06-03)
We closed the remaining server-side gaps for “every chat line is an authoritative encounter transcript line”:
- Encounter-phase room chat is now gated so players cannot bypass action economy via direct room-chat or session endpoints.
- Room chat controller now rejects any client attempts to POST non-player room transcript lines (prevents Narrator/system/NPC injection).
- GM continuation now prefixes its output during encounter.

Outbox: `sessions/ceo-copilot-2/outbox/20260603-135422-dc-encounter-chat-prefix-gating.md`
Code: `dungeoncrawler-content` commit `1ef35aa` (pushed to `main`)

We also corrected the remaining round/turn prompt + prefix standardization issues seen in live transcripts:
- Canonical prefix is now `Round N: Turn T: Actor X: …` (round displayed 0-based).
- Room turn harness now uses stable per-speaker turn indices (not the structured-log sequence counter).
- Player “Current turn” system line is now non-internal + marked `turn_prompt` so the UI can prompt reliably.
- GM narrative no longer inherits the player’s actor prefix (correctly labeled as `Actor Game Master`).

Code: `dungeoncrawler-content` commit `1175689` (pushed to `main`)
- Follow-up refactor: centralized prefix formatting helper (reduces drift risk): commit `877ded9` (pushed to `main`)
- UI hardening: ChatPanel now preserves `turn_prompt` metadata, ensures turn prompts are not transient, and fixes client-side prefix detection/formatting to match `Round N: Turn T: Actor X:` (prevents accidental re-prefixing into the old Turn/Round order): commit `23cfecd` (pushed to `main`)
- UI prompt: ChatPanel now propagates server `turn_prompt` metadata on `turn_logs` / streamed `system_message` events and emits a deterministic `System: It's your turn, <name>.` prompt on authoritative `turn_start` encounter events: commit `853b099` (pushed to `main`)


### 0) Fix: GM reply bridge regression (integration)
Update (2026-06-03): Resolved failing regression in `tests/chat_integration_test.php` (Test 6: GM reply bridge).
- Root cause: `bridgeGmReplyToSessionSystem()` required a `dc_campaign_dungeons` row via `loadLatestDungeonSnapshot()` even when invoked directly.
- Fix: fall back to `ChatSessionManager::ensureRoomSession()` when no dungeon snapshot exists; also ensure system log exists.

Code: `dungeoncrawler-content` commit `d412fb4` (pushed to `main`)

### 0b) Fix: Room entry scene intro persists into room chat (regression)
Update (2026-06-03): Room descriptions now persist into instantiated room chat when entering a room, so the v2 chat view no longer depends on client-side `room_entered` event handling.
- Injects deterministic Narrator scene intro text sourced from the instantiated room record (`dungeon_data.rooms[].description`) into `dungeon_data.rooms[].chat` when the room has no player-visible chat yet.
- Injection point is server-authoritative: `EncounterPhaseHandler::enterRoomFramework()`.

Code: `dungeoncrawler-content` commit `badff06` (pushed to `main`)

### 1) Manual QA: validate hierarchy + ordering end-to-end (browser)
Goal: prove “chat harness is subordinate to encounter harness” in a real run.

Acceptance checklist:
- Live room entry starts an encounter harness round/turn sequence.
- Chat window shows (in-order): `round_start` → `turn_start` → per-actor action/speech → explicit `choose_not_to_act` where applicable.
- NPC speech appears during that NPC’s turn (no “silent turn” then spill-over at end of harness).
- Refresh does NOT change the ordering (refresh should only re-hydrate the same canonical order).
- Player off-turn talk is rejected; on-turn talk consumes 1 action and appears immediately.

Notes:
- v2 EncounterSystem round/turn observer methods should remain console-only (per `encounter_system_logging_contract_test.js`) — the chat window must be driven by **authoritative events**, not fabricated client lines.

Update (2026-06-11, phase 10): Completed.
- Re-ran the full verification command set for this inbox item and confirmed all listed suites pass with strict failure handling.
- Fixed the remaining contract gap in `tests/encounter_system_logging_contract_test.js` so `endCurrentTurn()` is exercised through the coordinator resync helper (`_sendCoordinatorActionWithResync`) rather than failing with a missing-method runtime error.
- This restores reliable regression coverage for the turn-end authoritative-event contract and closes the last open follow-up in this item.

Code: `dungeoncrawler-content` commit `dd5f891` (pushed to `main`)

### 2) Confirm the server always emits turn/round events in the authoritative event stream
Why: The v2 client explicitly warns when authoritative turn events are missing; missing events will look like chat gaps.

Action:
- Audit `EncounterPhaseHandler` event emission to ensure `round_start` and `turn_start` are reliably persisted into the campaign event stream used by `GET /api/game/{campaign_id}/events`.
- If any paths still mutate state without emitting these events, fix them.

Update (2026-06-03): Completed.
- Verified all encounter entry/advance paths build and return `round_start` + `turn_start` (room-scene framework + hostile combat + end-turn advancement), and `GameCoordinatorService` persists those returned events via `GameEventLogger::logEvents()` into the authoritative `event_log` consumed by `GET /api/game/{campaign_id}/events`.
- Strengthened server-side regression coverage to explicitly assert `turn_start` is present in the logged event stream in:
  - `tests/multi_round_combat_cycle_test.php`
  - `tests/full_combat_cycle_test.php`

Code: `dungeoncrawler-content` commit `1a57732` (pushed to `main`)

### 3) Prevent double-rendering (legacy transcript vs event stream)
Risk: The room view can display both legacy `dungeon_data.rooms[*].chat[]` and authoritative coordinator events; if both are rendered simultaneously, we can get duplicates or apparent ordering bugs.

Action:
- Review ChatPanel room view rendering path and ensure there is a single canonical source of truth for “encounter narration/turn transcript” lines.
- If legacy transcript is still needed for compatibility, ensure deterministic de-dupe keys (e.g., `eventId`/`lineId` + `turn_cursor`) prevent duplicates.

Update (2026-06-03): Completed.
- ChatPanel now detects when room history already contains encounter-prefixed transcript lines and suppresses rendering the persisted encounter-event transcript to avoid double-rendering.
- We still recreate the player-facing "It's your turn" prompt from `turn_start` events.
- We also skip rendering persisted encounter events when viewing a non-room channel (encounter events always target the room channel).

Code: `dungeoncrawler-content` commit `42ed355` (pushed to `main`)

### 4) Strengthen ordering tie-breakers in the UI
Why: timestamps can collide; ordering must be stable when mixing:
- room history (`room-history`)
- streamed room responses (`room-stream`)
- encounter events (`encounter-event`)

Action:
- Ensure ordering uses stable tie-breakers (e.g., `(created, eventId, lineId)` or `(round, turn_index, eventId)` when present).
- Add/extend a regression test that simulates mixed-source lines with identical timestamps and asserts stable order.

Update (2026-06-03): Completed.
- `renderChatLineRecords()` now deterministically sorts normalized chat lines before rendering/remembering using `(created, eventId, messageId, lineId)` with stable handling for lines missing timestamps.
- Added regression coverage in `tests/chat_panel_line_contract_test.js` for ordering stability when `created` timestamps collide.

Code: `dungeoncrawler-content` commit `afb18ee` (pushed to `main`)

### 5) Docs sanity: remove remaining misleading `/api/combat/*` gameplay guidance
We already restricted `/api/combat/*` to admin-only; ensure docs don’t suggest players rely on it.

Action:
- Re-scan `README.md` and any API reference docs for `/api/combat/*` references that describe it as player-facing.
- Update language to: legacy/admin-only; canonical gameplay mutation surface is `POST /api/game/{campaign_id}/action`.

Update (2026-06-03): Completed.
- Clarified `/api/combat/*` as legacy/admin-only (debug/testing/support) and reinforced coordinator-first guidance (`POST /api/game/{campaign_id}/action`) in:
  - `GM_TOOLKIT_API_REFERENCE.md`
  - `HEXMAP_ARCHITECTURE.md`

Code: `dungeoncrawler-content` commit `37543d0` (pushed to `main`)

## Verification commands
- `cd /var/www/html/dungeoncrawler && vendor/bin/drush cr -q`
- `vendor/bin/drush -q php:script /home/ubuntu/forseti.life/dungeoncrawler-content/tests/chat_session_test.php`
- `vendor/bin/drush -q php:script /home/ubuntu/forseti.life/dungeoncrawler-content/tests/chat_integration_test.php`
- `cd /home/ubuntu/forseti.life/dungeoncrawler-content && node tests/encounter_system_logging_contract_test.js`
- `cd /home/ubuntu/forseti.life/dungeoncrawler-content && node tests/chat_panel_line_contract_test.js`
- `cd /home/ubuntu/forseti.life/dungeoncrawler-content && node tests/chat_panel_progress_contract_test.js`

## Recent related commits (for context)
- `812b46c` — Resolve strike weapon by `weapon_id` (removes placeholder strike defaults; supports coordinator-only strike)
