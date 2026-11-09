<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/** Returns canonical narrative analysis for the current draft. */
final class PreviewStorylineNarrativeAnalysisTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'preview_storyline_narrative_analysis',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Return canonical authored order, conditional branch graph, trigger/effect implications, conflicts, relationships, and narrative findings.',
      FALSE,
      'StorylineAuthoringDraftService::validateDraft()',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $validation = $context->validation();
    return [
      'analysis' => $validation['analysis'] ?? [],
      'valid' => (bool) ($validation['valid'] ?? FALSE),
    ];
  }

}
