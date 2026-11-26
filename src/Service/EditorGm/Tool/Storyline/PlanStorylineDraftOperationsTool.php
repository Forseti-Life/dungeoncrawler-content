<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;

/** Plans exact structured draft changes without persisting them. */
final class PlanStorylineDraftOperationsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_storyline_draft_operations',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Plan structured storyline edits, including quest content and objective criteria, against one draft revision. Use ID-matched patches for edits to existing rows. For a fixed clue triggered by explicit Search, patch the explore objective with its room location and search_narration.',
      FALSE,
      'StorylineAuthoringDraftService::planDraftOperations()',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Expected current draft revision.'),
        EditorGmToolDefinition::argument('preview_only', 'boolean', FALSE, 'True when offering a concrete edit for approval rather than executing an authorized edit.'),
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
    $expected_revision = EditorGmToolContext::requireInt($arguments, 'expected_revision');
    $operations = EditorGmToolContext::requireArray($arguments, 'operations');
    $plan = $context->drafts->planDraftOperations(
      $context->draftId,
      $expected_revision,
      $operations,
    );
    $plan['proposed_execution'] = $plan['has_changes'] ? [
      'tool_name' => 'apply_storyline_draft_operations',
      'authority' => 'StorylineAuthoringDraftService::applyDraftOperations()',
      'arguments' => [
        'expected_revision' => $expected_revision,
        'operation_id' => 'storyline-op-' . bin2hex(random_bytes(12)),
        'operations' => $operations,
      ],
    ] : NULL;
    if (($arguments['preview_only'] ?? FALSE) === TRUE && $plan['has_changes']) {
      $plan['requires_approval'] = TRUE;
    }
    return $plan;
  }

}
