<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Suite;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorSuiteGmToolContext;

/**
 * Returns the whole hub state: surfaces, recent drafts, attention.
 */
final class SummarizeEditorSuiteTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'summarize_editor_suite',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Summarize the editor suite: accessible surfaces with live counts, the author\'s recent drafts, and every cross-editor attention item.',
      FALSE,
      'EditorSuiteService::summary()',
      [],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = EditorSuiteGmToolContext::of($context);
    return ['summary' => $context->summary()];
  }

}
