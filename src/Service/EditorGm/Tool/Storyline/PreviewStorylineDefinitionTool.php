<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/** Returns the canonical definition produced from the current draft. */
final class PreviewStorylineDefinitionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'preview_storyline_definition',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Generate the canonical storyline-definition-v1 preview without saving it.',
      FALSE,
      'StorylineAuthoringDraftService::previewTemplateDefinition()',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $definition = $context->drafts->previewTemplateDefinition($context->draftId);
    $metadata = is_array($definition['metadata'] ?? NULL) ? $definition['metadata'] : [];
    $outline = is_array($metadata['generated_outline'] ?? NULL) ? $metadata['generated_outline'] : [];
    $entry = is_array($outline['entry_point'] ?? NULL) ? $outline['entry_point'] : [];
    $identity = is_array($metadata['storyline_builder']['identity'] ?? NULL)
      ? $metadata['storyline_builder']['identity']
      : [];
    $status = $context->status();

    return [
      'schema_version' => 'storyline-definition-preview-v1',
      'overview' => [
        'title' => (string) ($definition['name'] ?? ''),
        'objective' => (string) ($metadata['goal'] ?? $outline['goal'] ?? ''),
        'synopsis' => (string) ($definition['synopsis'] ?? ''),
        'opening' => (string) ($entry['detail_summary'] ?? ''),
        'quest_giver' => (string) ($entry['primary_quest_giver_name'] ?? ''),
        'player_hook' => (string) ($identity['player_hook'] ?? ''),
        'intended_ending' => (string) ($identity['ending'] ?? ''),
        'counts' => is_array($status['counts'] ?? NULL) ? $status['counts'] : [],
        'missing_content' => array_values((array) ($status['missing_content'] ?? [])),
      ],
      'definition' => $definition,
    ];
  }

}
