<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Context tool: lists canonical definitions for one family. */
final class ListDefinitionsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'list_definitions',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Search and page canonical definitions in a family.',
      FALSE,
      'CanonicalDefinitionService::catalog()',
      [
        EditorGmToolDefinition::argument('family', 'string', FALSE, 'Definition family; defaults to the page scope.'),
        EditorGmToolDefinition::argument('search', 'string', FALSE, 'Substring match on name or definition id.'),
        EditorGmToolDefinition::argument('limit', 'integer', FALSE, 'Page size, 1-250.'),
        EditorGmToolDefinition::argument('offset', 'integer', FALSE, 'Page offset.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DefinitionEditorGmToolContext::of($context);
    return $context->definitions->catalog(
      $context->scopedFamily($arguments),
      isset($arguments['search']) ? (string) $arguments['search'] : '',
      isset($arguments['limit']) ? (int) $arguments['limit'] : 40,
      isset($arguments['offset']) ? (int) $arguments['offset'] : 0,
    );
  }

}
