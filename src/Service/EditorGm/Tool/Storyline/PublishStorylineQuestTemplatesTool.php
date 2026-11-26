<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/**
 * Publishes only the requested quest templates from saved storyline tasks.
 */
final class PublishStorylineQuestTemplatesTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'publish_storyline_quest_templates',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Create only the authorized missing canonical quest templates from existing tasks, without editing or publishing the storyline. Never use asset remediation for this.',
      TRUE,
      'StorylineAuthoringDraftService::publishDraftQuestTemplates()',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Current saved draft revision.'),
        EditorGmToolDefinition::argument('task_ids', 'array', TRUE, 'Exact saved task IDs of the authorized templates, in the current draft.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $records = $context->drafts->publishDraftQuestTemplates(
      $context->draftId,
      EditorGmToolContext::requireInt($arguments, 'expected_revision'),
      EditorGmToolContext::requireArray($arguments, 'task_ids'),
    );
    $context->invalidate();
    return ['quest_records' => $records];
  }

}
