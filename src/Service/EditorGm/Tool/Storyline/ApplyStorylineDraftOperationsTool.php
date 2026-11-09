<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;

/** Applies an approved, idempotent structured operation batch. */
final class ApplyStorylineDraftOperationsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'apply_storyline_draft_operations',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Apply one approved structured operation batch exactly once.',
      TRUE,
      'StorylineAuthoringDraftService::applyDraftOperations()',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Expected current draft revision.'),
        EditorGmToolDefinition::argument('operation_id', 'string', TRUE, 'Stable idempotency key for this exact batch.'),
        EditorGmToolDefinition::argument(
          'operations',
          'array',
          TRUE,
          StorylineAuthoringDraftService::operationContractDescription()
            . ' Branch changes must follow: '
            . json_encode(StorylineAuthoringDraftService::branchAuthoringContract(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $result = $context->drafts->applyDraftOperations(
      $context->draftId,
      EditorGmToolContext::requireInt($arguments, 'expected_revision'),
      EditorGmToolContext::requireString($arguments, 'operation_id'),
      EditorGmToolContext::requireArray($arguments, 'operations'),
    );
    $context->invalidate();
    return $result;
  }

}
