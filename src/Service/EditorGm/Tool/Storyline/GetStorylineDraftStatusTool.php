<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/**
 * Returns the canonical Storyline Builder completion and validity projection.
 */
final class GetStorylineDraftStatusTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'get_storyline_draft_status',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Summarize completion percentage, object and branch counts, missing minimum content, validation, and next steps.',
      FALSE,
      'StorylineAuthoringDraftService::summarizeDraftState()',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    return StorylineBuilderGmToolContext::of($context)->status();
  }

}
