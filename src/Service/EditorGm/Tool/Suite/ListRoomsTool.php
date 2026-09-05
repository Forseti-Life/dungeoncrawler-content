<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorSuiteGmToolContext;

/**
 * Lists every canonical room with its publication status.
 */
final class ListRoomsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'list_rooms',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'List every canonical room with publication status and published version id, published or not.',
      FALSE,
      'RoomEditorService::listRooms()',
      [
        EditorGmToolDefinition::argument('published_only', 'boolean', FALSE, 'Only rooms with a published version.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = EditorSuiteGmToolContext::of($context);
    $rooms = $context->suite->rooms();
    if (!empty($arguments['published_only'])) {
      $rooms = array_values(array_filter($rooms, static fn(array $r): bool => $r['published_version_id'] !== NULL));
    }
    return ['rooms' => $rooms, 'count' => count($rooms)];
  }

}
