<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/**
 * Returns the current storyline authoring draft snapshot.
 */
final class LoadStorylineDraftSnapshotTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'load_storyline_draft_snapshot',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Load the full storyline authoring draft payload and metadata.',
      FALSE,
      'StorylineAuthoringDraftService::getDraft()',
      [],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    return ['draft' => $context->draft()];
  }

}
