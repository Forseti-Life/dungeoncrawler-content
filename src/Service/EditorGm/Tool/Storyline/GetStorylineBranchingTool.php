<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/** Returns the current choice triggers, effects, and destinations. */
final class GetStorylineBranchingTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'get_storyline_branching',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Report authored decisions, choices, typed triggers, actor/environment effects, called-out storyline implications, destinations, terminal outcomes, and follow-up requirements.',
      FALSE,
      'StorylineAuthoringDraftService::summarizeDraftBranching()',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    return $context->drafts->summarizeDraftBranching($context->draftId);
  }

}
