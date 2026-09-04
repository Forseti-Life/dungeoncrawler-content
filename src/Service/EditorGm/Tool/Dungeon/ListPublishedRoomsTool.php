<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Lists the published room versions a placement may pin.
 */
final class ListPublishedRoomsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'list_published_rooms',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'List published rooms available for placement, with room_id, version_id, footprint size and port counts. Only published versions can be placed.',
      FALSE,
      'DungeonEditorService::roomLibrary()',
      [
        EditorGmToolDefinition::argument('room_type', 'string', FALSE, 'Restrict to one room_type.'),
        EditorGmToolDefinition::argument('include_geometry', 'boolean', FALSE, 'Include room-local footprint hexes and ports (default false).'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $room_type = isset($arguments['room_type']) ? trim((string) $arguments['room_type']) : '';
    $include_geometry = !empty($arguments['include_geometry']);

    $rooms = [];
    foreach ($context->roomLibrary() as $entry) {
      if ($room_type !== '' && ($entry['room_type'] ?? NULL) !== $room_type) {
        continue;
      }
      if (!$include_geometry) {
        unset($entry['footprint'], $entry['ports']);
      }
      $rooms[] = $entry;
    }

    return [
      'room_count' => count($rooms),
      'rooms' => $rooms,
    ];
  }

}
