<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Geometry\HexShapes;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomBuildService;

/**
 * Paints terrain onto the district ground through a new street version.
 */
final class PaintDistrictTerrainTool implements EditorGmToolInterface {

  public function __construct(private readonly CanonicalRoomBuildService $rooms) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'paint_district_terrain',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Change the terrain type of part of the district ground (the street room), for example "make the north end forest floor" or "ring the village with void": give a disk (centre_q, centre_r, radius) or an explicit hex list, in level coordinates. Hexes under a building are left alone; hexes off the ground are counted and ignored. Publishes a new street version and returns a command plan that repins the street placement. Terrain wall and void block movement. Apply the plan with apply_dungeon_commands, then validate.',
      TRUE,
      'CanonicalRoomBuildService::paintRoomTerrain()',
      [
        EditorGmToolDefinition::argument('street_placement_id', 'string', TRUE, 'Placement of the street room.'),
        EditorGmToolDefinition::argument('terrain_type', 'string', TRUE, 'A Room Editor terrain type, e.g. dirt, mud, sand, water_shallow, fungal_growth, rubble, wall, void.'),
        EditorGmToolDefinition::argument('centre_q', 'integer', FALSE, 'Disk centre q (level coordinates).'),
        EditorGmToolDefinition::argument('centre_r', 'integer', FALSE, 'Disk centre r (level coordinates).'),
        EditorGmToolDefinition::argument('radius', 'integer', FALSE, 'Disk radius in hexes.'),
        EditorGmToolDefinition::argument('hexes', 'array', FALSE, 'Explicit level hexes [{q, r}], instead of a disk.'),
        EditorGmToolDefinition::argument('reason', 'string', FALSE, 'Publication note.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $street = DungeonPlanSteps::placement($context->model(), EditorGmToolContext::requireString($arguments, 'street_placement_id'));
    $terrain = EditorGmToolContext::requireString($arguments, 'terrain_type');
    if (isset($arguments['hexes'])) {
      $level = array_map(static fn(array $h): array => ['q' => (int) $h['q'], 'r' => (int) $h['r']], (array) $arguments['hexes']);
    }
    else {
      $level = HexShapes::disk(
        ['q' => EditorGmToolContext::requireInt($arguments, 'centre_q'), 'r' => EditorGmToolContext::requireInt($arguments, 'centre_r')],
        EditorGmToolContext::requireInt($arguments, 'radius'),
      );
    }
    $split = DistrictGround::split($context, $street, $level);
    $note = isset($arguments['reason']) ? trim((string) $arguments['reason']) : '';
    $built = $this->rooms->paintRoomTerrain((string) $street['room_id'], $split['paint'], $terrain, $note !== '' ? $note : 'District terrain painted through the Dungeon Editor GM.');
    return DistrictGround::result($context, $street, $built, ['skipped_under_rooms' => $split['under_rooms'], 'skipped_off_ground' => $split['off_ground']], sprintf('%d hexes now %s', $built['painted'], $terrain));
  }

}
