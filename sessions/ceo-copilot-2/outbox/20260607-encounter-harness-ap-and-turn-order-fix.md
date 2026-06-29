# Outbox — Encounter harness AP + turn-order fix

Date: 2026-06-07  
Seat: ceo-copilot-2  
Repo: `dungeoncrawler-content`

## Trigger
Live report (campaign 218): encounter harness still appeared non-authoritative in practice (turn/round drift symptoms), and chat action behavior did not align with strict AP contract.

## Findings
- Encounter-phase `talk` path still allowed room-chat harness NPC interjection injection, which can create out-of-turn transcript lines during a player turn.
- Room-scene `talk` path consumed 1 action, then forced `actions_remaining = 0`, auto-ending the turn and violating “chat is one action” economics.
- Several mechanical/narrator lines used blank `speaker_ref`, allowing encounter prefix ownership to drift from the actual actor.

## Fix shipped
- In encounter phase, `EncounterPhaseHandler::processTalk()` now sets `defer_npc_interjections = true` so turn ownership remains with EncounterPhaseHandler.
- Removed forced auto-end on room-scene talk; talk now spends exactly one action and leaves turn advancement explicit (`end_turn`/choose-not-to-act).
- Bound actor-owned mechanical narration to actor identity via `speaker_ref = actor_id/entity_id` for stable prefixing.
- Updated talk unit tests to assert:
  - encounter-talk still delegates correctly through room chat contract;
  - room-scene talk spends one action and does not emit `auto_end_turn`.

## Verification run
- `vendor/bin/phpunit -c web/core/phpunit.xml.dist --filter "testProcessIntentTalkDelegatesToRoomChatServiceAndBuildsContract|testProcessIntentTalkSpendsSingleActionWithoutAutoEndTurn" web/modules/custom/dungeoncrawler_content/tests/src/Unit/Service/EncounterPhaseHandlerTest.php`

## Code shipped
- Commit: `31c32fb`
- Branch: `main`
- Push: completed to `origin/main`
