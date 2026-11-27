<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomBuildService;

/**
 * Publishes a resized version of a room and plans repinning its placements.
 *
 * A room is not a definition: its size is its hex footprint, changed through
 * the Room Editor as a new published version. Nothing here edits a
 * definition.
 */
final class ResizeRoomTool implements EditorGmToolInterface {

  public function __construct(private readonly CanonicalRoomBuildService $rooms) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'resize_room',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Make a room bigger or smaller, for example "twice the size" or "half the size": publishes a new version with about area_percent of its hexes (200 = double, 50 = half). For a district-level ground room, use this before adding rooms so the container grows to hold future placements, then apply the returned repin plan and reflow buildings with plan_district_buildings. Growing adds hexes outward; shrinking protects ports and placed objects. Rooms are not definitions: use this, never the definition tools, to change room size.',
      TRUE,
      'CanonicalRoomBuildService::resizeRoom()',
      [
        EditorGmToolDefinition::argument('room_id', 'string', TRUE, 'Library room to resize (from list_published_rooms).'),
        EditorGmToolDefinition::argument('area_percent', 'integer', TRUE, 'New size as a percentage of the current hex count: 200 doubles it, 50 halves it. Between 25 and 1000, and not within 10 of 100.'),
        EditorGmToolDefinition::argument('reason', 'string', FALSE, 'Publication note.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $room_id = EditorGmToolContext::requireString($arguments, 'room_id');
    $note = isset($arguments['reason']) ? trim((string) $arguments['reason']) : '';
    $built = $this->rooms->resizeRoom($room_id, EditorGmToolContext::requireInt($arguments, 'area_percent'), $note !== '' ? $note : 'Resized through the Dungeon Editor GM.');

    $context->invalidate();
    $steps = [];
    foreach ((array) ($context->model()['placements'] ?? []) as $placement) {
      if ($placement['room_id'] === $room_id) {
        $steps[] = DungeonPlanSteps::step(count($steps) + 1, 'retarget_room_placement', [
          'placement_id' => $placement['placement_id'],
          'version_id' => $built['version_id'],
        ], sprintf('Repin "%s" to version %s (%d hexes, was %d).', $placement['label'], $built['version'], $built['hexes_after'], $built['hexes_before']));
      }
    }

    return $built + [
      'command_plan' => $steps === [] ? NULL : DungeonPlanSteps::plan($context, $steps),
      'next_step' => $steps === [] ? 'No placement of this room is in this dungeon; nothing to repin.' : 'Apply the command plan to repin the placements, then validate.',
    ];
  }

}
