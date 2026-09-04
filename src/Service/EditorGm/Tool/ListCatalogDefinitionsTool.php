<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Context tool: pages the normalized placeable catalog.
 *
 * Gives the assistant the same discovery surface the human catalog rail has.
 */
final class ListCatalogDefinitionsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'list_catalog_definitions',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Search and page the normalized placeable object catalog.',
      FALSE,
      'RoomEditorService::catalog()',
      [
        EditorGmToolDefinition::argument('family', 'string', FALSE, 'Restrict to one placeable family.'),
        EditorGmToolDefinition::argument('search', 'string', FALSE, 'Substring match on name or definition id.'),
        EditorGmToolDefinition::argument('limit', 'integer', FALSE, 'Page size, 1-250.'),
        EditorGmToolDefinition::argument('offset', 'integer', FALSE, 'Page offset.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $family = $arguments['family'] ?? NULL;
    if ($family !== NULL && !is_string($family)) {
      throw new \InvalidArgumentException('argument_invalid:family');
    }
    $family = is_string($family) && trim($family) !== '' ? trim($family) : NULL;

    return $context->roomEditor->catalog(
      $family,
      isset($arguments['search']) ? (string) $arguments['search'] : '',
      isset($arguments['limit']) ? (int) $arguments['limit'] : 40,
      isset($arguments['offset']) ? (int) $arguments['offset'] : 0,
    );
  }

}
