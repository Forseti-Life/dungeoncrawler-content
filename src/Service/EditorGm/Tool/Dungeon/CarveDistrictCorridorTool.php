<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Geometry\HexShapes;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomBuildService;

/**
 * Lays a 1 to 10 hex wide corridor of terrain between two points of the ground.
 */
final class CarveDistrictCorridorTool implements EditorGmToolInterface {

  public function __construct(private readonly CanonicalRoomBuildService $rooms) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'carve_district_corridor',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Lay a corridor 1 to 10 hexes wide on the district ground between two rooms or two hexes, for example "a 3 wide dirt road from the clearing to the longhouse". Each end is a placement (its way in, the default entry port) or a level hex. The corridor is a straight run painted in the given terrain; hexes under a building are skipped. Publishes a new street version and returns a command plan that repins the street placement. Apply the plan with apply_dungeon_commands, then validate.',
      TRUE,
      'CanonicalRoomBuildService::paintRoomTerrain()',
      [
        EditorGmToolDefinition::argument('street_placement_id', 'string', TRUE, 'Placement of the street room.'),
        EditorGmToolDefinition::argument('width', 'integer', TRUE, 'Corridor width in hexes, 1 to 10.'),
        EditorGmToolDefinition::argument('terrain_type', 'string', TRUE, 'Terrain to lay, e.g. dirt, smooth_stone, sand.'),
        EditorGmToolDefinition::argument('from_placement_id', 'string', FALSE, 'Start at this placement\'s way in.'),
        EditorGmToolDefinition::argument('from_hex', 'object', FALSE, 'Start at this level hex {q, r}, instead.'),
        EditorGmToolDefinition::argument('to_placement_id', 'string', FALSE, 'End at this placement\'s way in.'),
        EditorGmToolDefinition::argument('to_hex', 'object', FALSE, 'End at this level hex {q, r}, instead.'),
        EditorGmToolDefinition::argument('reason', 'string', FALSE, 'Publication note.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $model = $context->model();
    $street = DungeonPlanSteps::placement($model, EditorGmToolContext::requireString($arguments, 'street_placement_id'));
    $width = EditorGmToolContext::requireInt($arguments, 'width');
    $terrain = EditorGmToolContext::requireString($arguments, 'terrain_type');
    $from = $this->end($arguments, 'from', $context);
    $to = $this->end($arguments, 'to', $context);
    $split = DistrictGround::split($context, $street, HexShapes::strip($from, $to, $width));
    $note = isset($arguments['reason']) ? trim((string) $arguments['reason']) : '';
    $built = $this->rooms->paintRoomTerrain((string) $street['room_id'], $split['paint'], $terrain, $note !== '' ? $note : 'District corridor laid through the Dungeon Editor GM.');
    return DistrictGround::result($context, $street, $built, [
      'from' => $from,
      'to' => $to,
      'width' => $width,
      'skipped_under_rooms' => $split['under_rooms'],
      'skipped_off_ground' => $split['off_ground'],
    ], sprintf('%d wide corridor of %s', $width, $terrain));
  }

  /**
   * @return array{q: int, r: int}
   */
  private function end(array $arguments, string $side, DungeonEditorGmToolContext $context): array {
    $placement = (string) ($arguments[$side . '_placement_id'] ?? '');
    $hex = $arguments[$side . '_hex'] ?? NULL;
    if (($placement === '') === !is_array($hex)) {
      throw new \InvalidArgumentException(sprintf('corridor_%s_needs_exactly_one_of_placement_or_hex', $side));
    }
    if ($placement !== '') {
      return DistrictGround::doorHex($context, DungeonPlanSteps::placement($context->model(), $placement));
    }
    return ['q' => (int) ($hex['q'] ?? PHP_INT_MAX), 'r' => (int) ($hex['r'] ?? PHP_INT_MAX)];
  }

}
