# HexMap V2 — Template room background fallback (2026-06-02)

## Problem
Room battlemap backgrounds were linked per-campaign (`dc_campaign_rooms` + `campaign_id`). This caused drift: if a campaign had the room but no link row, the background silently disappeared (observed on campaign 133; likely to recur on other campaigns).

## Change
Implemented a strict fallback chain in `HexMapController::attachRoomBackgroundImages()`:
1) Campaign-specific background: `dc_campaign_rooms` + `campaign_id` + `room_id` + `slot=background`.
2) If missing and `room.source_room_id` is set, load a canonical template background from `dungeoncrawler_content_room_templates` keyed by `source_room_id`.

## Data
Inserted a canonical template background link:
- table_name: `dungeoncrawler_content_room_templates`
- object_id: `tavern_entrance`
- slot: `background`
- image_id: `448`
- scope_type: `template`

## Verification
- Confirmed campaign `130` has no campaign-scoped background link but template-scoped link exists:
  - SQL: `campaign=0`, `template=1` link counts.
  - Repository lookup resolves template URL:
    `/sites/default/files/generated-images/2026/06/19f66b9e-9233-48f4-be01-001d4d96a090.png`

## Notes
- Campaign-specific links still win; fallback only applies when the campaign link is absent.
- This removes the need to manually link the same battlemap background into every campaign that uses a shared room template.
