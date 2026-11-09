<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/**
 * Returns the draft's brief synopsis and story-shape qualification.
 */
final class GetStorylineSynopsisTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'get_storyline_synopsis',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Answer what the story is, report its chapter/scene/conflict/branch structure, and state when it lacks enough content to qualify as a complete story.',
      FALSE,
      'StorylineAuthoringDraftService::summarizeDraftSynopsis()',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    return $context->drafts->summarizeDraftSynopsis($context->draftId);
  }

}
