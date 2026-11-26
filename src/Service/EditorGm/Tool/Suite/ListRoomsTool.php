<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorSuite\EditorSuiteService;

/**
 * Lists or searches canonical rooms with their publication status.
 */
final class ListRoomsTool implements EditorGmToolInterface {

  public function __construct(private readonly EditorSuiteService $suite) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'list_rooms',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'List or search canonical rooms with publication status and published version id.',
      FALSE,
      'RoomEditorService::listRooms()',
      [
        EditorGmToolDefinition::argument('search', 'string', FALSE, 'Distinctive words from the room name or ID. All words must match; omit to list all rooms.'),
        EditorGmToolDefinition::argument('published_only', 'boolean', FALSE, 'Only rooms with a published version.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    if (array_key_exists('search', $arguments) && !is_string($arguments['search'])) {
      throw new \InvalidArgumentException('argument_invalid:search');
    }
    $search = mb_strtolower(trim($arguments['search'] ?? ''));
    $terms = $search === '' ? [] : preg_split('/[^\p{L}\p{N}]+/u', $search, -1, PREG_SPLIT_NO_EMPTY);
    if ($terms === FALSE || ($search !== '' && $terms === [])) {
      throw new \InvalidArgumentException('argument_invalid:search');
    }
    $rooms = $this->suite->rooms();
    if (!empty($arguments['published_only'])) {
      $rooms = array_values(array_filter($rooms, static fn(array $r): bool => $r['published_version_id'] !== NULL));
    }
    if ($terms !== []) {
      $rooms = array_values(array_filter($rooms, static function (array $room) use ($terms): bool {
        $haystack = mb_strtolower($room['name'] . ' ' . $room['room_id']);
        foreach ($terms as $term) {
          if (!str_contains($haystack, $term)) {
            return FALSE;
          }
        }
        return TRUE;
      }));
    }
    return ['rooms' => $rooms, 'count' => count($rooms)];
  }

}
