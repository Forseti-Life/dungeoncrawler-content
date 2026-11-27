<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Geometry\HexShapes;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomBuildService;

/**
 * Carves the district entry route and blocks the remaining ground in one edit.
 */
final class PrepareDistrictNavigationTool implements EditorGmToolInterface {

  public function __construct(private readonly CanonicalRoomBuildService $rooms) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'prepare_district_navigation',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Prepare a district for play: carve a passable corridor from the district ground external entry port to the first room door, then make every other uncovered ground hex wall or void. Hexes beneath current room placements stay untouched, so rooms can be moved later and the tool can be rerun to rebuild the route. Publishes one street version and returns a repin plan.',
      TRUE,
      'CanonicalRoomBuildService::paintRoomTerrainMap()',
      [
        EditorGmToolDefinition::argument('street_placement_id', 'string', TRUE, 'Placement of the district ground.'),
        EditorGmToolDefinition::argument('first_room_placement_id', 'string', TRUE, 'First room reached from the district external entry.'),
        EditorGmToolDefinition::argument('width', 'integer', FALSE, 'Path width in hexes, 1 to 10; default 3.'),
        EditorGmToolDefinition::argument('path_terrain', 'string', FALSE, 'Passable path terrain; default dirt.'),
        EditorGmToolDefinition::argument('blocked_terrain', 'string', FALSE, 'Impassable terrain for the rest; wall or void, default wall.'),
        EditorGmToolDefinition::argument('reason', 'string', FALSE, 'Publication note.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $model = $context->model();
    $street = DungeonPlanSteps::placement(
      $model,
      EditorGmToolContext::requireString($arguments, 'street_placement_id'),
    );
    $first_room = DungeonPlanSteps::placement(
      $model,
      EditorGmToolContext::requireString($arguments, 'first_room_placement_id'),
    );
    $width = isset($arguments['width']) ? EditorGmToolContext::requireInt($arguments, 'width') : 3;
    if ($width < 1 || $width > 10) {
      throw new \InvalidArgumentException('district_navigation_width_invalid');
    }
    $path_terrain = trim((string) ($arguments['path_terrain'] ?? 'dirt'));
    $blocked_terrain = trim((string) ($arguments['blocked_terrain'] ?? 'wall'));
    if ($path_terrain === '' || !in_array($blocked_terrain, ['wall', 'void'], TRUE)) {
      throw new \InvalidArgumentException('district_navigation_terrain_invalid');
    }

    $from = DistrictGround::doorHex($context, $street);
    $to = DistrictGround::doorHex($context, $first_room);
    $path_level = HexShapes::strip($from, $to, $width);
    $path_split = DistrictGround::split($context, $street, $path_level);
    if ($path_split['paint'] === []) {
      throw new \DomainException('district_navigation_path_unavailable');
    }

    $room = $context->roomVersion((string) $street['version_id']);
    $ground_level = array_map(
      static fn(array $hex): array => RoomPlacementTransformer::toLevel($hex, $street),
      (array) $room['hexes'],
    );
    $ground_split = DistrictGround::split($context, $street, $ground_level);
    $path = [];
    foreach ($path_split['paint'] as $hex) {
      $path[HexShapes::key($hex)] = $hex;
    }
    $blocked = [];
    foreach ($ground_split['paint'] as $hex) {
      if (!isset($path[HexShapes::key($hex)])) {
        $blocked[] = $hex;
      }
    }
    if ($blocked === []) {
      throw new \DomainException('district_navigation_blocked_ground_empty');
    }

    $note = isset($arguments['reason']) ? trim((string) $arguments['reason']) : '';
    $built = $this->rooms->paintRoomTerrainMap(
      (string) $street['room_id'],
      [
        ['hexes' => $blocked, 'terrain_type' => $blocked_terrain],
        ['hexes' => array_values($path), 'terrain_type' => $path_terrain],
      ],
      $note !== '' ? $note : 'District entry path carved and remaining ground blocked.',
    );
    $result = DistrictGround::result($context, $street, $built, [
      'external_entry' => $from,
      'first_room_entry' => $to,
      'width' => $width,
      'path_hexes' => count($path),
      'blocked_hexes' => count($blocked),
      'skipped_under_rooms' => $ground_split['under_rooms'],
      'skipped_off_ground' => $ground_split['off_ground'],
      'path_skipped_under_rooms' => $path_split['under_rooms'],
    ], sprintf('%d-hex wide %s route with remaining ground set to %s', $width, $path_terrain, $blocked_terrain));
    $result['room_movement'] = 'Current room footprints are excluded from terrain edits. Rooms may move over the underlay without terrain validation errors; rerun prepare_district_navigation after the move.';
    $result['next_step'] = 'Apply the repin plan, then validate. After any later room move or reflow, rerun prepare_district_navigation to redraw the entry route.';
    return $result;
  }

}
