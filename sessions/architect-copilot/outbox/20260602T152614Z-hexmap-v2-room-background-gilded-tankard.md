# HexMap V2 — Room background (Gilded Tankard) snapshot

Timestamp: 2026-06-02T15:26:14Z

## What was done

1) Implemented/verified the room background anchoring contract on the client (`room.map_background`) and ensured backend can inject a background when a linked image exists.

2) Extended canonical visual-state output so `room.map_background` is preserved in:
`map_visual_state.topology.rooms[room_id].map_background`.

3) Generated a top-down battlemap background for The Gilded Tankard and persisted it as a linked generated image:
- Campaign: 131
- Room: tavern_entrance
- image_id: 448
- URL: https://dungeoncrawler.forseti.life/sites/default/files/generated-images/2026/06/19f66b9e-9233-48f4-be01-001d4d96a090.png

4) Fixed “missing background” on campaign 133 by adding a generated-image link:
- Root cause: no link row for campaign 133 + tavern_entrance + slot=background.
- Fix: linked image_id 448 to campaign 133 for dc_campaign_rooms:tavern_entrance slot=background.

## Verification

- Verified image URL returns HTTP 200.
- Verified via Drush calling `HexMapController::demo()` that drupalSettings contains map_background URL in BOTH:
  - `hexmapDungeonData.rooms[tavern_entrance].map_background.image_url`
  - `map_visual_state.topology.rooms[tavern_entrance].map_background.image_url`

## Notes

- Curling /hexmap anonymously will not show campaign-scoped state due to campaign ownership enforcement in controller (`assertCampaignAccess`).
- The “missing background” symptom across campaigns is data/linking, not renderer math: each campaign needs a slot=background link (or we add a deliberate fallback policy).
