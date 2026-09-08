<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Describes deterministic dungeon publication readiness.
 */
final class DescribePublicationReadinessTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'describe_publication_readiness',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Run publication-profile validation and active-campaign guard checks for the active dungeon draft.',
      FALSE,
      'DungeonEditorService::publicationReadiness()',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    return $context->dungeonEditor->publicationReadiness($context->draftId);
  }

}
