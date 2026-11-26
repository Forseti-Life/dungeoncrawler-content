<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;

/**
 * Selects hexes of a district's ground in level space.
 *
 * The ground is the street room. Painting and corridor tools pick level
 * hexes here; hexes under a building are left alone because the building
 * overlays them, and the rest are mapped back into the street room.
 */
final class DistrictGround {

  /**
   * Splits level hexes into ground to paint and the rest.
   *
   * @param list<array{q: int, r: int}> $level_hexes
   * @param array<string, mixed> $street
   *   The street placement from the dungeon model.
   *
   * @return array{paint: list<array{q: int, r: int}>, under_rooms: int, off_ground: int}
   */
  public static function split(DungeonEditorGmToolContext $context, array $street, array $level_hexes): array {
    if (!$street['resolved']) {
      throw new \DomainException(sprintf('placement_unresolved:%s', $street['placement_id']));
    }
    $room = $context->roomVersion((string) $street['version_id']);
    $ground = [];
    foreach ($room['hexes'] as $hex) {
      $ground[RoomPlacementTransformer::hexKey($hex)] = TRUE;
    }
    $occupancy = (array) ($context->model()['occupancy'] ?? []);
    $paint = [];
    $under = 0;
    $off = 0;
    $seen = [];
    foreach ($level_hexes as $level) {
      $key = RoomPlacementTransformer::hexKey($level);
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;
      if (!in_array($street['placement_id'], $occupancy[$key] ?? [], TRUE)) {
        $off++;
        continue;
      }
      if (array_diff((array) $occupancy[$key], [$street['placement_id']]) !== []) {
        $under++;
        continue;
      }
      $local = RoomPlacementTransformer::toRoomLocal($level, $street);
      if (isset($ground[RoomPlacementTransformer::hexKey($local)])) {
        $paint[] = $local;
      }
      else {
        $off++;
      }
    }
    return ['paint' => $paint, 'under_rooms' => $under, 'off_ground' => $off];
  }

  /**
   * The street hex a building's way in stands on.
   *
   * @return array{q: int, r: int}
   */
  public static function doorHex(DungeonEditorGmToolContext $context, array $placement, string $port_id = ''): array {
    $room = $context->roomVersion((string) $placement['version_id']);
    $ports = array_values(array_filter((array) ($room['entry_ports'] ?? []), static fn(array $p): bool => $port_id === '' || $p['port_id'] === $port_id));
    if ($ports === []) {
      throw new \DomainException(sprintf('placement_has_no_entry_port:%s%s', $placement['placement_id'], $port_id !== '' ? ':' . $port_id : ''));
    }
    $default = $ports[0];
    foreach ($ports as $port) {
      if (!empty($port['is_default'])) {
        $default = $port;
      }
    }
    return RoomPlacementTransformer::toLevel($default['hex'], $placement);
  }

  /**
   * Result and repin plan for a street version just published.
   *
   * @param array<string, mixed> $built
   *   Result of CanonicalRoomBuildService::paintRoomTerrain().
   * @param array<string, int> $counts
   */
  public static function result(DungeonEditorGmToolContext $context, array $street, array $built, array $counts, string $what): array {
    $context->invalidate();
    $step = DungeonPlanSteps::step(1, 'retarget_room_placement', [
      'placement_id' => $street['placement_id'],
      'version_id' => $built['version_id'],
    ], sprintf('Repin "%s" to version %s (%s).', $street['label'], $built['version'], $what));
    return $built + $counts + [
      'command_plan' => DungeonPlanSteps::plan($context, [$step]),
      'next_step' => 'Apply the command plan to repin the street, then validate.',
    ];
  }

}
