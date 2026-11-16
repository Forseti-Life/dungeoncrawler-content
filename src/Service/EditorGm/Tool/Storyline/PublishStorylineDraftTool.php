<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/**
 * Publishes one approved storyline draft to canonical template storage.
 */
final class PublishStorylineDraftTool implements EditorGmToolInterface {

  public function __construct(
    private readonly PlanStorylineRemediationTool $remediationPlanner,
    private readonly ApplyStorylineRemediationTool $remediationExecutor,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'publish_storyline_draft',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Complete review and create/publish the approved storyline revision, persisting missing canonical assets and recording its native publication receipt as final signoff.',
      TRUE,
      'StorylineAuthoringDraftService::publishDraft()',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Expected current draft revision.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $expected_revision = EditorGmToolContext::requireInt($arguments, 'expected_revision');
    $draft = $context->draft();
    if ((int) $draft['revision'] !== $expected_revision) {
      throw new \DomainException('storyline_draft_revision_conflict');
    }
    $remediation_result = NULL;
    $sub_tool_calls = [];
    if ($draft['status'] !== 'published') {
      $sub_tool_calls[] = [
        'tool' => 'plan_storyline_remediation',
        'message' => 'Now looking up missing canonical assets before publication…',
      ];
      $planned = $this->remediationPlanner->execute([
        'expected_revision' => $expected_revision,
      ], $context);
      $proposed_args = $planned['proposed_execution']['arguments'] ?? NULL;
      if ($planned['has_changes']) {
        if (!is_array($proposed_args)) {
          throw new \LogicException('storyline_publication_remediation_plan_invalid');
        }
        $sub_tool_calls[] = [
          'tool' => 'apply_storyline_remediation',
          'message' => 'Now applying storyline canonical asset remediation…',
        ];
        $remediation_result = $this->remediationExecutor->execute($proposed_args, $context);
        if (is_array($remediation_result['tool_calls'] ?? NULL)) {
          $sub_tool_calls = array_merge($sub_tool_calls, $remediation_result['tool_calls']);
        }
        $context->invalidate();
      }
    }
    $result = $context->drafts->publishDraft($context->draftId, $expected_revision);
    if ($remediation_result !== NULL) {
      $result['remediated_assets'] = [
        'definitions' => $remediation_result['definitions'] ?? [],
        'rooms' => $remediation_result['rooms'] ?? [],
        'dungeons' => $remediation_result['dungeons'] ?? [],
        'quests' => $remediation_result['quests'] ?? [],
      ];
    }
    if ($sub_tool_calls !== []) {
      $result['tool_calls'] = $sub_tool_calls;
    }
    $context->invalidate();
    return $result + ['changed' => empty($result['idempotent_replay'])];
  }

}
