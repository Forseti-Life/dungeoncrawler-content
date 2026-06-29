# Implement: Line of Effect and Line of Sight

- Release: ch09-playing-the-game
- Feature: dc-cr-line-of-effect-sight
- Status: pending
- Priority: high
- Agent: dev-dungeoncrawler

## Rulebook References
- `/home/ubuntu/forseti.life/docs/dungeoncrawler/PF2requirements/references/core-ch09-playing-the-game.md` — Line of Effect (p.457), Line of Sight (p.457)

## Requirements: 2130–2134 (all pending)

## What Exists
- `HexUtilityService.php`: hex distance and grid math exist
- `DungeonStateService.php`, `RoomStateService.php`: terrain/room data exists with obstacle tracking
- `RulesEngine.validateAttack()`: range check exists but no LoE/LoS check

## Required Implementation

### Create `LineOfEffectService.php`

New service: `dungeoncrawler_content.los_service`

```php
/**
 * Returns TRUE if an unblocked physical path exists between two hex positions.
 * Semi-solid obstacles (portcullises, grates) do NOT block LoE.
 */
public function hasLineOfEffect(array $from, array $to, array $terrain_obstacles): bool;

/**
 * Returns TRUE if attacker can visually perceive the target.
 * Solid obstacles block LoS. Darkness blocks unless attacker has darkvision.
 * Semi-solid obstacles do NOT block LoS.
 */
public function hasLineOfSight(array $attacker, array $target, string $lighting, array $terrain_obstacles): bool;
```

Use Bresenham's line algorithm adapted to hex grid (or hex raycasting) to check all hexes between origin and target.

### Terrain obstacle flags required in data
Each terrain/room object needs:
- `is_solid: bool` — blocks LoE and LoS
- `is_semi_solid: bool` — does NOT block either

### Wire into `RulesEngine.validateAttack()`
After range check, add LoE check:
```php
$loe = $this->losService->hasLineOfEffect($attacker_pos, $target_pos, $terrain_obstacles);
if (!$loe) {
  return ['is_valid' => FALSE, 'reason' => 'No line of effect to target.'];
}
```

### Wire into Area Resolver (req 2132)
`AreaResolverService` methods should filter targets by LoE from origin point.

## Acceptance Criteria
1. Two adjacent hexes with no obstacles: LoE=TRUE
2. Solid wall between hexes: LoE=FALSE
3. Portcullis (semi-solid) between hexes: LoE=TRUE
4. Attacker without darkvision in darkness: LoS=FALSE
5. Attacker with darkvision in darkness: LoS=TRUE
6. Burst AoE excludes target behind solid wall
7. RulesEngine blocks attack when LoE=FALSE

## Definition of Done
- [ ] `LineOfEffectService.php` created
- [ ] Service wired in `services.yml`
- [ ] `RulesEngine.validateAttack()` uses LoE check
- [ ] `AreaResolverService` filters by LoE
- [ ] QA evidence updated for reqs 2130–2134
- [ ] `dc_requirements` 2130–2134 updated to `implemented` via MySQL
