# Dungeoncrawler — Authoritative event emission audit (round_start / turn_start)

Date: 2026-06-03
Seat: ceo-copilot-2
Scope: dungeoncrawler-content (server-side regressions)

## Goal
Confirm the server always persists `round_start` + `turn_start` into the authoritative campaign event stream (`GET /api/game/{campaign_id}/events`), because the v2 client relies on those events to hydrate deterministic transcripts and to warn on missing turn/round markers.

## Findings (code-level)
- Encounter entry/advance logic (`EncounterPhaseHandler`) consistently builds `round_start` + `turn_start` for:
  - Room-scene encounter framework start/resume (`startRoomSceneEncounter()`)
  - Hostile combat entry (`onEnter()`)
  - Turn advancement and round wrap (`processEndTurn()`)
  - Room entry bootstrap path (`enterRoomFramework()`)
- `GameCoordinatorService::processAction()` persists handler-returned events via `GameEventLogger::logEvents()` into `dungeon_data['event_log']` (the authoritative stream consumed by `GET /api/game/{campaign_id}/events`).

## What changed
Strengthened existing drush regression scripts to explicitly assert `turn_start` is present in the logged event stream:
- `tests/multi_round_combat_cycle_test.php`
- `tests/full_combat_cycle_test.php`

## Verification
Executed (passing):
- `cd /var/www/html/dungeoncrawler && vendor/bin/drush -q php:script /home/ubuntu/forseti.life/dungeoncrawler-content/tests/multi_round_combat_cycle_test.php`
- `cd /var/www/html/dungeoncrawler && vendor/bin/drush -q php:script /home/ubuntu/forseti.life/dungeoncrawler-content/tests/full_combat_cycle_test.php`

## Code reference
- `dungeoncrawler-content` commit `1a57732` — test: assert turn_start in event stream
