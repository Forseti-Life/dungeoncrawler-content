# Feature Brief: Survival — Sense Direction, Track, Cover Tracks

- Work item id: dc-cr-skills-survival-track-direction
- Website: dungeoncrawler
- Module: dungeoncrawler_content
- Status: done
- Priority: P2
- Release: 
20260412-dungeoncrawler-release-r
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: PF2E Core Rulebook (Fourth Printing), core/ch04
- Category: skill-action
- Created: 2026-04-07
- DB sections: core/ch04/Survival (Wis)
- Depends on: dc-cr-skill-system, dc-cr-exploration-mode

## Goal

Implement Survival (Wis) skill action handlers — Cover Tracks, Sense Direction, Subsist (downtime), and Track — as exploration-mode activities covering wilderness navigation, self-sufficiency, and pursuit mechanics.

## Source reference

> "Track: You follow the trail of a creature or group of creatures. You attempt a Survival check against a DC determined by the terrain and how recently the tracks were made; on a success you follow the trail to its end or until the trail goes cold."

## Implementation hint

`TrackAction` is an exploration-mode activity; the DC is computed from a terrain + time-since-passage matrix (stored as a lookup table). On success, reveal the next waypoint of the tracked creature's path. `CoverTracksAction` sets a `tracks_covered` flag on the character's movement record for the current exploration turn; opposed by the pursuer's Track check. `SenseDirectionAction` resolves as a flat Survival check against DC 15 + modifiers for overcast/underground; returns cardinal direction and distance estimate. `SubsistAction` is a downtime activity; resolve vs DC 15 (urban) or DC 12 (rural) and set a `subsistence_status` flag clearing food/water need for that day.

## Latest updates

- 2026-04-19: Surfaced the already-implemented Survival actions through the phase APIs — `ExplorationPhaseHandler::getAvailableActions()` now exposes `sense_direction`, `cover_tracks`, and `track`, and `DowntimePhaseHandler::getAvailableActions()` now includes `subsist`. Added focused unit coverage for both exploration and downtime availability.

## Mission alignment

- [x] Aligns with democratized community game experience
- [x] Does not add surveillance or restrict community access

## Security acceptance criteria

- Authentication/permission surface: Character-scoped write; tracking results broadcast to GM view only (hidden from players until revealed).
- CSRF expectations: all POST/PATCH routes require `_csrf_request_header_mode: TRUE`
- Input validation: Terrain type must be a valid terrain enum; Track DC modifiers validated against server-side terrain/age tables; Subsist environment must be [urban, rural, wilderness].
- PII/logging constraints: no PII logged; log character_id, action_type, terrain_type, dc_attempted, outcome; no PII logged.

## Roadmap section
- Book: core, Chapter: ch04
- REQs: 1572, 1573, 1595, 1596, 1597, 1598, 1739, 1740, 1741, 1742, 1743, 1744, 1745, 1746
- See `runbooks/roadmap-audit.md` for audit process.
