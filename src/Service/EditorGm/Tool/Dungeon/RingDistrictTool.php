<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Geometry\HexShapes;
use Drupal\dungeoncrawler_content\Geometry\PlacementRules;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomBuildService;

/**
 * Rings the edge of a district's ground with an obstacle, leaving the ways in.
 */
final class RingDistrictTool implements EditorGmToolInterface {

  public function __construct(private readonly CanonicalRoomBuildService $rooms) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'ring_district',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Ring the outer edge of the district ground with an obstacle (a palisade, tree line or wall) for example "wall the town in except the road". Hexes left clear: the street\'s own ports and the hex outside each, the way in of every building placement, any terrain named in keep_clear_terrain (corridors), any hex in keep_clear_hexes, hexes under a building, and hexes already holding an object or blocking terrain. Skipped hexes are counted by reason. Publishes a new street version and returns a command plan that repins the street placement. Apply the plan with apply_dungeon_commands, then validate.',
      TRUE,
      'CanonicalRoomBuildService::placeRoomObjects()',
      [
        EditorGmToolDefinition::argument('street_placement_id', 'string', TRUE, 'Placement of the street room.'),
        EditorGmToolDefinition::argument('definition_id', 'string', TRUE, 'Obstacle definition to place, e.g. stone_pillar.'),
        EditorGmToolDefinition::argument('version', 'string', TRUE, 'Definition version.'),
        EditorGmToolDefinition::argument('keep_clear_terrain', 'array', FALSE, 'Terrain types kept clear, e.g. the corridor terrain.'),
        EditorGmToolDefinition::argument('keep_clear_hexes', 'array', FALSE, 'Level hexes {q, r} kept clear.'),
        EditorGmToolDefinition::argument('every', 'integer', FALSE, 'Place on every nth edge hex (default 1).'),
        EditorGmToolDefinition::argument('reason', 'string', FALSE, 'Publication note.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $model = $context->model();
    $street = DungeonPlanSteps::placement($model, EditorGmToolContext::requireString($arguments, 'street_placement_id'));
    if (!$street['resolved']) {
      throw new \DomainException(sprintf('placement_unresolved:%s', $street['placement_id']));
    }
    $room = $context->roomVersion((string) $street['version_id']);
    $ref = [
      'family' => 'obstacle',
      'definition_id' => EditorGmToolContext::requireString($arguments, 'definition_id'),
      'version' => EditorGmToolContext::requireString($arguments, 'version'),
    ];

    $clear = PlacementRules::keptClear($room, ['keep_ports_clear' => TRUE]);
    foreach ((array) ($model['placements'] ?? []) as $other) {
      if ($other['placement_id'] === $street['placement_id'] || !$other['resolved']) {
        continue;
      }
      $local = RoomPlacementTransformer::toRoomLocal(DistrictGround::doorHex($context, $other), $street);
      $clear[HexShapes::key($local)] = 'building_door';
    }
    foreach ((array) ($arguments['keep_clear_hexes'] ?? []) as $level) {
      $clear[HexShapes::key(RoomPlacementTransformer::toRoomLocal($level, $street))] = 'caller';
    }
    $terrain = PlacementRules::terrainByHex($room);
    $keep_terrain = (array) ($arguments['keep_clear_terrain'] ?? []);
    foreach ($terrain as $key => $type) {
      if (in_array($type, $keep_terrain, TRUE)) {
        $clear[$key] = 'terrain';
      }
    }

    $occupancy = (array) ($model['occupancy'] ?? []);
    $occupied = PlacementRules::solidOccupancy($room);
    $every = max(1, (int) ($arguments['every'] ?? 1));
    $edge = HexShapes::every(HexShapes::boundary($room['hexes']), $every);
    $items = [];
    $skipped = [];
    foreach ($edge as $local) {
      $key = HexShapes::key($local);
      $level = RoomPlacementTransformer::toLevel($local, $street);
      $under = array_diff((array) ($occupancy[HexShapes::key($level)] ?? []), [$street['placement_id']]) !== [];
      $refusal = $under ? ['reason' => 'hex_under_building'] : PlacementRules::refusal($terrain, $clear, $occupied, 'obstacle', $key);
      if ($refusal !== NULL) {
        $skipped[$refusal['reason']] = ($skipped[$refusal['reason']] ?? 0) + 1;
        continue;
      }
      $items[] = ['definition_ref' => $ref, 'anchor_hex' => ['q' => $local['q'], 'r' => $local['r']], 'facing' => 0];
    }
    $note = isset($arguments['reason']) ? trim((string) $arguments['reason']) : '';
    $built = $this->rooms->placeRoomObjects((string) $street['room_id'], $items, 'ring_district', $note !== '' ? $note : 'District ringed through the Dungeon Editor GM.');
    return DistrictGround::result($context, $street, $built, ['skipped' => $skipped], sprintf('ringed with %d %s', count($items), $ref['definition_id']));
  }

}
