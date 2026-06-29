# Implement: Areas of Effect (Burst, Cone, Emanation, Line)

- Release: ch09-playing-the-game
- Feature: dc-cr-areas-of-effect
- Status: pending
- Priority: high
- Agent: dev-dungeoncrawler

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Areas of Effect (p.456–457)

## Requirements

| ID   | Req                                                                                          |
|------|----------------------------------------------------------------------------------------------|
| 2125 | Burst: corner origin, radius, partial occupancy counts                                       |
| 2126 | Cone: quarter-circle from caster, no caster overlap                                         |
| 2127 | Emanation: all sides of caster, optional caster inclusion                                   |
| 2128 | Line: straight path 5 ft wide, all overlapped creatures                                     |
| 2129 | Difficult terrain does not reduce area shape                                                |

## What Exists

- `HexUtilityService.php` exists — hex distance calculations used in combat
- No `AreaResolverService` or AoE methods exist anywhere in the service layer
- The combat system uses hex grid (q/r coordinates); positions are hex-based

## Required Implementation

### Create `AreaResolverService.php`

New service: `dungeoncrawler_content.area_resolver`

Methods needed:

```php
/**
 * Resolve all participants within a burst area.
 *
 * Burst originates at a hex corner (between up to 3 adjacent hexes).
 * All hexes within $radius hexes of origin are included.
 * Difficult terrain does NOT affect inclusion.
 *
 * @param int $origin_q, $origin_r  Hex coordinate of origin point
 * @param int $radius               Radius in hex units
 * @param array $participants       All encounter participants with position_q/position_r
 * @return array                    Participant IDs in the burst area
 */
public function resolveBurst(int $origin_q, int $origin_r, int $radius, array $participants): array;

/**
 * Resolve all participants within a cone area.
 *
 * Cone covers a quarter-circle (90°) from caster position in $direction.
 * Caster's own hex is never included.
 *
 * @param array $caster_pos   ['q' => int, 'r' => int]
 * @param string $direction   One of: 'N','NE','SE','S','SW','NW'
 * @param int $length         Cone length in hexes
 * @param array $participants
 * @return array              Participant IDs in cone
 */
public function resolveCone(array $caster_pos, string $direction, int $length, array $participants): array;

/**
 * Resolve all participants within an emanation area.
 *
 * Emanation extends from ALL sides of caster's hex.
 *
 * @param array $caster_pos
 * @param int $radius
 * @param array $participants
 * @param bool $include_origin  Whether caster's own hex is included (default FALSE)
 * @return array
 */
public function resolveEmanation(array $caster_pos, int $radius, array $participants, bool $include_origin = FALSE): array;

/**
 * Resolve all participants along a line area.
 *
 * Line is 1 hex wide (5 ft), straight from caster to $length hexes in $direction.
 *
 * @param array $start_pos
 * @param string $direction
 * @param int $length
 * @param array $participants
 * @return array
 */
public function resolveLine(array $start_pos, string $direction, int $length, array $participants): array;
```

**Important:** All resolver methods must ignore terrain type when determining which hexes are in the area (req 2129).

### Wire into `ActionProcessor`

When a spell has `'area_type'` in its definition (burst/cone/emanation/line), resolve targets via `AreaResolverService` instead of using the `resolved_targets` array passed by the caller.

## Acceptance Criteria

1. `resolveBurst(0, 0, 2, $participants)` returns all participants within 2-hex radius
2. `resolveCone($caster, 'NE', 3, $participants)` does not include caster
3. `resolveEmanation($caster, 2, $participants, FALSE)` excludes caster; `TRUE` includes caster
4. `resolveLine($start, 'N', 4, $participants)` returns creatures on the straight path
5. Terrain array passed to resolver does NOT change which hexes are included

## Definition of Done
- [ ] `AreaResolverService.php` created with all four AoE methods
- [ ] Service registered in `dungeoncrawler_content.services.yml`
- [ ] ActionProcessor routes area spells through AreaResolverService
- [ ] QA evidence updated for reqs 2125–2129
- [ ] `dc_requirements` reqs 2125–2129 updated to `implemented` via MySQL
