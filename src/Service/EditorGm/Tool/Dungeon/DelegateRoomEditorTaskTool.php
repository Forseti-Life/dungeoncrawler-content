<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Defines a Dungeon GM request delegated to the Room Editor GM.
 */
final class DelegateRoomEditorTaskTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'delegate_room_editor_task',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Delegate one authorized room creation, edit and publication to the Room Editor GM. The Dungeon GM receives the published room result; it does not edit room data.',
      TRUE,
      'EditorGmHarnessService cross-surface delegation to RoomEditorGmService',
      [
        EditorGmToolDefinition::argument('room_id', 'string', FALSE, 'Existing room ID or authoritative missing room ID. Omit for a newly named room; the Room Editor GM assigns its canonical identity.'),
        EditorGmToolDefinition::argument('name', 'string', TRUE, 'Human-readable room name used only if the room does not exist.'),
        EditorGmToolDefinition::argument('task', 'string', TRUE, 'Complete authorized creation or edit request for the Room Editor GM.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    DungeonEditorGmToolContext::of($context);
    throw new \LogicException('delegate_room_editor_task_must_run_through_editor_gm_harness');
  }

}
