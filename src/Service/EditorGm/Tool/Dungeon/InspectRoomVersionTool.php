<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Returns the frozen room payload behind one published version id.
 */
final class InspectRoomVersionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'inspect_room_version',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Load the frozen canonical room payload for one published version_id, as pinned by a placement.',
      FALSE,
      'DungeonEditorService::roomVersion()',
      [
        EditorGmToolDefinition::argument('version_id', 'string', TRUE, 'Published room version id (from list_published_rooms or a placement).'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $version_id = EditorGmToolContext::requireString($arguments, 'version_id');
    return [
      'version_id' => $version_id,
      'room' => $context->roomVersion($version_id),
    ];
  }

}
