<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalRoomBuildService;

/**
 * Moves a room port to a side through a new published room version.
 *
 * Ports belong to the room, so the change is a Room Editor version; the
 * returned plan repins this dungeon's placements to it.
 */
final class MoveRoomPortTool implements EditorGmToolInterface {

  public function __construct(private readonly CanonicalRoomBuildService $rooms) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'move_room_port',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Move a room entry or exit port to the north, south, east or west side of the room, for example "make the district entrance the southern tip": publishes a new version of the room with the port on the boundary hex furthest toward that side and returns a command plan that repins every placement of that room in this dungeon to it. The level entrance is a room: its arrival point is that room\'s default entry port. Ports belong to the room, not the dungeon. Apply the returned plan with apply_dungeon_commands, then validate.',
      TRUE,
      'CanonicalRoomBuildService::moveRoomPort()',
      [
        EditorGmToolDefinition::argument('room_id', 'string', TRUE, 'Library room whose port moves (from list_published_rooms).'),
        EditorGmToolDefinition::argument('port_id', 'string', TRUE, 'Port to move (from inspect_room_version).'),
        EditorGmToolDefinition::argument('side', 'string', TRUE, 'north|south|east|west. North is the top of the map.'),
        EditorGmToolDefinition::argument('reason', 'string', FALSE, 'Publication note.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $room_id = EditorGmToolContext::requireString($arguments, 'room_id');
    $note = isset($arguments['reason']) ? trim((string) $arguments['reason']) : '';
    $built = $this->rooms->moveRoomPort($room_id, EditorGmToolContext::requireString($arguments, 'port_id'), EditorGmToolContext::requireString($arguments, 'side'), $note !== '' ? $note : 'Port moved through the Dungeon Editor GM.');

    $context->invalidate();
    $steps = [];
    foreach ((array) ($context->model()['placements'] ?? []) as $placement) {
      if ($placement['room_id'] === $room_id) {
        $steps[] = DungeonPlanSteps::step(count($steps) + 1, 'retarget_room_placement', [
          'placement_id' => $placement['placement_id'],
          'version_id' => $built['version_id'],
        ], sprintf('Repin "%s" to version %s (port %s now at (%d, %d)).', $placement['label'], $built['version'], $built['port_id'], $built['hex']['q'], $built['hex']['r']));
      }
    }

    return $built + [
      'command_plan' => $steps === [] ? NULL : DungeonPlanSteps::plan($context, $steps),
      'next_step' => $steps === [] ? 'No placement of this room is in this dungeon; nothing to repin.' : 'Apply the command plan to repin the placements, then validate.',
    ];
  }

}
