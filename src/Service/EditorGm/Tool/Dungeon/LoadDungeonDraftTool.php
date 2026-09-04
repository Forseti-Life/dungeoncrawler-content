<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Returns the resolved Dungeon Editor read model for the active draft.
 */
final class LoadDungeonDraftTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'load_dungeon_draft',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Load the active dungeon draft read model: placements with resolved room geometry, level-space occupancy, port links, regions and validation.',
      FALSE,
      'DungeonEditorService::describe()',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    return ['model' => $context->model()];
  }

}
