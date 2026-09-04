<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Context tool: inspects one normalized catalog definition.
 */
final class InspectCatalogEntryTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'inspect_catalog_entry',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Inspect one normalized placeable definition used for room placement.',
      FALSE,
      'RoomEditorService::catalogEntry()',
      [
        EditorGmToolDefinition::argument('family', 'string', TRUE, 'Placeable family.'),
        EditorGmToolDefinition::argument('definition_id', 'string', TRUE, 'Canonical definition id.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $family = EditorGmToolContext::requireString($arguments, 'family');
    $definition_id = EditorGmToolContext::requireString($arguments, 'definition_id');
    $entry = $context->roomEditor->catalogEntry($family, $definition_id);
    if ($entry === NULL) {
      throw new \OutOfBoundsException('catalog_entry_not_found');
    }
    return ['definition' => $entry];
  }

}
