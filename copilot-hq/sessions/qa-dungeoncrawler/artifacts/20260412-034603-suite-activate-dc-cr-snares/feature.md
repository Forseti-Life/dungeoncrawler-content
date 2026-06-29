# Feature Brief: Snares (Core + APG)

- Website: dungeoncrawler
- Type: new
- Module: dungeoncrawler_content
- Priority: P2
- Status: ready
- Release: 
- Dependencies: dc-cr-equipment-system, dc-cr-skill-system, dc-cr-class-ranger

## Goal

Implement the snare crafting and placement system — snare types (Alarm/Hampering/Marking/Wounding), ranger and feat-based access, exploration-mode square placement, and encounter-mode trigger resolution when enemies enter snared squares.

## Source reference

> "Snares are small, difficult-to-notice traps that are quick to set up; a ranger or character with the Snare Crafting feat can set a snare in a square as part of an exploration activity."

## Implementation hint

`SnareEntity` stores: snare_type enum, placed_square (grid coordinate), creator_id, trigger_DC (Perception to detect), and effect payload. `PlaceSnareAction` is an exploration-phase activity consuming 1 `SnareItem` from inventory and placing a `SnareEntity` on the map. During encounter movement resolution, `SnareDetectionService` checks if a moving creature enters a snared square; if not detected, trigger the snare effect (alarm/slow/mark/damage) via the appropriate condition/damage handler. Snare Crafting feat grants access to the place-snare action; Rangers get it for free at level 1.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; snare placement server-authoritative; GM can see all placed snares regardless of detection.
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Snare type must be a valid snare enum; placed square must be within map bounds; snare item must be in character inventory before placement.
- PII/logging constraints: no PII logged; log character_id, snare_type, placed_square, trigger_target_id; no PII logged.

## Roadmap section
- See `runbooks/roadmap-audit.md` for audit process.
- Requirements tracked in `dc_requirements` table.
