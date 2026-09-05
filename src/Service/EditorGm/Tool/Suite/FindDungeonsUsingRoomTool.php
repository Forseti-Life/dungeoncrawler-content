<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorSuiteGmToolContext;

/**
 * Finds dungeon drafts placing one room and whether each pin is superseded.
 */
final class FindDungeonsUsingRoomTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'find_dungeons_using_room',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Find every active dungeon draft that places a given room, the version each placement pins, and whether that pin is behind the published version.',
      FALSE,
      'EditorSuiteService::roomUsage()',
      [
        EditorGmToolDefinition::argument('room_id', 'string', TRUE, 'Canonical room id.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = EditorSuiteGmToolContext::of($context);
    $room_id = EditorGmToolContext::requireString($arguments, 'room_id');
    return $context->suite->roomUsage($room_id);
  }

}
