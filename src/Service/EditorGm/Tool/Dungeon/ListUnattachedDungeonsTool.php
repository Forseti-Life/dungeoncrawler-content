<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Context tool: the standing check for dungeons no storyline uses.
 */
final class ListUnattachedDungeonsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'list_unattached_dungeons',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'List every published dungeon that no storyline names, with how many campaigns use each. Use it when the author asks which dungeons are unattached, orphaned or unused.',
      FALSE,
      'DungeonEditorService::unattachedDungeons()',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $unattached = DungeonEditorGmToolContext::of($context)->dungeonEditor->unattachedDungeons();
    return ['total' => count($unattached), 'dungeons' => $unattached];
  }

}
