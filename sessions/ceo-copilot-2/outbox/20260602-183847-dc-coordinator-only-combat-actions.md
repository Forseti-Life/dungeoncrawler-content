# Dungeoncrawler: coordinator-only encounter actions (v2) + room-chat authority hardening

## Why
We still had a dual-master path during encounters:
- v2 UI routed encounterActive actions through `/api/combat/action` (legacy combat API), bypassing GameCoordinator/EncounterPhaseHandler.
- That split authority is consistent with the observed symptom: actor output not appearing on the correct turn, then “spilling out” after the turn harness finishes.

## What changed
### Frontend (v2)
- **GameShell.performCombatAction()** now posts to **`/api/game/{campaignId}/action`** (GameCoordinator) instead of `/api/combat/action`.
- Request fields are normalized to the coordinator contract (snake_case `params.*`, including `action_cost`, `spell_id`, `skill_name`, etc.).
- On error, we emit a `chat:system-message` with the coordinator error and avoid silent failures.

### Frontend consumers
- Removed reliance on legacy `response.action_result.summary` in:
  - `js/v2/systems/EncounterSystem.js`
  - `js/v2/systems/PlayerAutomation.js`
  These now use deterministic local summaries (server remains authoritative for state).

### Backend authority hardening
- **RoomChatController** now explicitly enforces room-channel authority:
  - `channel=room` **requires `type=player`** and is routed through the coordinator.
  - Non-player room-channel posts are rejected (prevents bypassing encounter/turn ordering).
- Updated NPC/LLM prompt “available tools” lists to remove `/api/combat/*` and other bypass routes, standardizing on the coordinator route only.

## Validation
- `php -l` on modified PHP files: OK
- `drush cr` + `drush php:script tests/chat_session_test.php`: **59 passed / 0 failed**
- `node --check` on modified JS files: OK

## Remaining ambiguity / follow-ups
- Legacy `/api/combat/*` controllers still exist, but their routes are now admin-only. Next step is deciding whether to fully retire/remove them after confirming no remaining demo/legacy client needs them.

### Backend routing hardening
- Updated `dungeoncrawler_content.routing.yml` so legacy `/api/combat/*` routes now require `administer dungeoncrawler content` (prevents player bypass of coordinator authority).
