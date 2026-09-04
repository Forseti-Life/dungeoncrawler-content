<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;

/**
 * Context tool: returns the active editor draft aggregate.
 */
final class LoadDraftSnapshotTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'load_draft_snapshot',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Load the active room editor draft aggregate and its revision metadata.',
      FALSE,
      'dungeoncrawler_content_room_editor_drafts',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = RoomEditorGmToolContext::of($context);
    return ['draft' => $context->draft()];
  }

}
