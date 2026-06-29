- Status: in_progress
- Summary: Mapped the seven open Dungeoncrawler chat CEO inbox items to the room-chat stabilization/refactor work now landed in `dungeoncrawler-content`; code coverage aligns with all seven items, but live campaign-28 verification and inbox closeout/archive still remain.

# Dungeoncrawler chat inbox ↔ refactor mapping

## Assessment
The current room-chat refactor stack now covers the full defect cluster represented by the seven open CEO inbox items.

## Inbox item mapping
- `2026-05-15-chat-gm-role-boundary-failure.md`
  - Covered by GM prompt/system guardrails, deterministic role-boundary correction handling, deterministic-response boundary validation, and the existing retry/fallback role-boundary checks.
- `2026-05-15-chat-gm-meta-question-misrouting.md`
  - Covered by the strengthened adjudication classifier and explicit precedence for GM-directed prompts over active NPC-thread continuity.
- `2026-05-15-chat-direct-conversation-scope-instability.md`
  - Covered by active direct-NPC thread recovery, follow-up continuity handling, and regressions for adjacent scoped follow-up turns.
- `2026-05-15-chat-room-context-mismatch.md`
  - Covered by hexmap chat-context changes that now prefer the pinned `room_id` from URL/launch context over `active_room_id` drift.
- `2026-05-15-chat-context-leakage-and-truncation.md`
  - Covered by player-visible narrative sanitization, truncated-fragment cleanup, and follow-up hardening against internal prompt scaffolding leakage.
- `2026-05-15-chat-storyline-grounding-gap.md`
  - Covered by brokered storyline lead plumbing already present in `RoomChatService` plus deterministic lead-handoff tests for tavern NPC behavior.
- `2026-05-15-chat-user-correction-not-absorbed.md`
  - Covered by explicit GM role-correction intent handling and deterministic acknowledgement that overrides the broken behavior on the immediate next turn.

## Code surfaces now carrying the fix cluster
- `src/Service/RoomChatService.php`
  - direct-NPC continuity
  - GM adjudication routing
  - GM/player role boundary enforcement
  - correction absorption
  - storyline lead grounding
  - player-visible narrative sanitization
- `js/hexmap.js`
  - pinned-room chat context resolution
  - shared pinned-room target fallback helper
- Regression coverage
  - `tests/src/Unit/Service/RoomChatServiceNpcResolutionTest.php`
  - `tests/src/Unit/Controller/RoomChatControllerProgressTest.php`
  - `tests/hexmap_chat_context_test.js`
  - `tests/chat_session_api_test.js`

## Verification status
- Targeted unit and JS regression coverage is green.
- Remaining gap: live campaign-28 verification against the original tavern transcript conditions has not yet been executed in this CEO thread.

## CEO conclusion
The inbox is no longer best interpreted as seven separate engineering defects. It is now one operational closeout batch: verify the live behavior in campaign 28, then resolve/archive the seven inbox items with a single coordinated closeout note.
