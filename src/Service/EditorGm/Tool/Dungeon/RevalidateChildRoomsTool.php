<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
/**
 * Revalidates rooms owned by lower-tier Room Editor authorities.
 */
final class RevalidateChildRoomsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'revalidate_child_rooms',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Rerun Room Editor validation for one placed room or every room in this dungeon.',
      FALSE,
      'Room Editor GM::revalidate',
      [
        EditorGmToolDefinition::argument('room_id', 'string', FALSE, 'One placed room ID; omit to validate all placed rooms.'),
        EditorGmToolDefinition::argument('profile', 'string', FALSE, 'editing, preview, or publication. Defaults to publication.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $profile = isset($arguments['profile']) ? (string) $arguments['profile'] : 'publication';
    $placements = (array) ($context->dungeon()['room_placements'] ?? []);
    $ids = array_values(array_unique(array_map(
      static fn(array $placement): string => (string) ($placement['room_id'] ?? ''),
      $placements,
    )));
    if (isset($arguments['room_id'])) {
      $requested = EditorGmToolContext::requireString($arguments, 'room_id');
      if (!in_array($requested, $ids, TRUE)) {
        throw new \InvalidArgumentException('room_not_in_dungeon');
      }
      $ids = [$requested];
    }
    return [
      'profile' => $profile,
      'room_ids' => array_values(array_filter($ids)),
    ];
  }

}
