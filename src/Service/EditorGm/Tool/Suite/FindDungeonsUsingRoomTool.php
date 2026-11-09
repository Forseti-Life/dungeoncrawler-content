<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorSuiteGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService;

/**
 * Finds published and draft dungeon placements of one canonical room.
 */
final class FindDungeonsUsingRoomTool implements EditorGmToolInterface {

  public function __construct(private readonly EditorSuiteService $suite) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'find_dungeons_using_room',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Find every current published dungeon and active dungeon draft that places a canonical room, including placement identity, pinned version, entrance relevance, and whether the pin is superseded.',
      FALSE,
      'EditorSuiteService::roomUsage()',
      [
        EditorGmToolDefinition::argument('room_id', 'string', TRUE, 'Canonical room id.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    if ($context instanceof EditorSuiteGmToolContext) {
      EditorSuiteGmToolContext::of($context);
    }
    $room_id = EditorGmToolContext::requireString($arguments, 'room_id');
    return $this->suite->roomUsage($room_id);
  }

}
