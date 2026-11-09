<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;

/**
 * Publishes one approved storyline draft to canonical template storage.
 */
final class PublishStorylineDraftTool implements EditorGmToolInterface {

  public function __construct(
    private readonly ?PlanStorylineRemediationTool $remediationPlanner = NULL,
    private readonly ?ApplyStorylineRemediationTool $remediationExecutor = NULL,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'publish_storyline_draft',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Persist any missing authored canonical assets (actors, items, rooms) and publish an approved storyline draft revision to canonical templates.',
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
    $remediation_result = NULL;
    $sub_tool_calls = [];
    if ($this->remediationPlanner !== NULL && $this->remediationExecutor !== NULL) {
      try {
        $sub_tool_calls[] = [
          'tool' => 'plan_storyline_remediation',
          'message' => 'Now looking up missing canonical assets before publication…',
        ];
        $planned = $this->remediationPlanner->execute([
          'expected_revision' => $expected_revision,
        ], $context);
        $proposed_args = $planned['proposed_execution']['arguments'] ?? NULL;
        if (is_array($proposed_args) && (!empty($proposed_args['definitions']) || !empty($proposed_args['rooms']))) {
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
      catch (\InvalidArgumentException $e) {
        if ($e->getMessage() !== 'storyline_remediation_empty') {
          throw $e;
        }
      }
    }
    $result = $context->drafts->publishDraft($context->draftId, $expected_revision);
    if ($remediation_result !== NULL) {
      $result['remediated_assets'] = [
        'definitions' => $remediation_result['definitions'] ?? [],
        'rooms' => $remediation_result['rooms'] ?? [],
      ];
    }
    if ($sub_tool_calls !== []) {
      $result['tool_calls'] = $sub_tool_calls;
    }
    $context->invalidate();
    return $result + ['changed' => TRUE];
  }

}
