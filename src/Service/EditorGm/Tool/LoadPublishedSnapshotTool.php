<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Context tool: returns the currently published canonical room aggregate.
 */
final class LoadPublishedSnapshotTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'load_published_snapshot',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Load the canonical room version currently published for this room.',
      FALSE,
      'dungeoncrawler_content_room_versions',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $published = $context->publishedRoom();
    return [
      'room_id' => $context->roomId(),
      'published' => $published !== NULL,
      'room' => $published,
    ];
  }

}
