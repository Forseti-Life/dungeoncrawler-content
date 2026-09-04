<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Definition tool: persists approved canonical definition edits.
 *
 * Mutation is delegated to RoomEditorService so canonical library authority
 * stays in one place. The tool refuses partial payloads: callers must send the
 * full name and attribute payload they intend to store.
 */
final class UpdateCanonicalDefinitionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'update_canonical_definition',
      EditorGmToolDefinition::FAMILY_DEFINITION,
      'Persist an approved edit to one canonical object definition.',
      TRUE,
      'CanonicalDefinitionService::saveCanonicalEntry()',
      [
        EditorGmToolDefinition::argument('family', 'string', TRUE, 'Placeable family.'),
        EditorGmToolDefinition::argument('definition_id', 'string', TRUE, 'Canonical definition id.'),
        EditorGmToolDefinition::argument('name', 'string', TRUE, 'Definition display name.'),
        EditorGmToolDefinition::argument('schema_data', 'object', TRUE, 'Complete attribute payload to store.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $family = EditorGmToolContext::requireString($arguments, 'family');
    $definition_id = EditorGmToolContext::requireString($arguments, 'definition_id');
    $name = EditorGmToolContext::requireString($arguments, 'name');
    $schema_data = EditorGmToolContext::requireArray($arguments, 'schema_data');

    $before = $context->definitions->loadCanonicalEntry($family, $definition_id);
    $context->definitions->saveCanonicalEntry($family, $definition_id, $name, $schema_data);
    $after = $context->definitions->loadCanonicalEntry($family, $definition_id);

    return [
      'family' => $family,
      'definition_id' => $definition_id,
      'name_changed' => $before['name'] !== $after['name'],
      'attributes_changed' => $before['schema_data'] !== $after['schema_data'],
      'entry' => $after,
    ];
  }

}
