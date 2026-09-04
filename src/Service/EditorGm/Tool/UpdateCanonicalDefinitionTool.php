<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Definition tool: persists an approved, schema-validated definition.
 *
 * Mutation is delegated to CanonicalDefinitionService::saveDefinition(), the
 * single validated write path shared with the human definition editor. The
 * tool refuses partial payloads: callers send the complete schema-shaped
 * payload they intend to store, and an invalid payload hard-fails with
 * per-pointer findings rather than being written blind.
 */
final class UpdateCanonicalDefinitionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'update_canonical_definition',
      EditorGmToolDefinition::FAMILY_DEFINITION,
      'Persist an approved, schema-validated edit to one canonical object definition.',
      TRUE,
      'CanonicalDefinitionService::saveDefinition()',
      [
        EditorGmToolDefinition::argument('family', 'string', TRUE, 'Placeable family.'),
        EditorGmToolDefinition::argument('definition_id', 'string', TRUE, 'Canonical definition id.'),
        EditorGmToolDefinition::argument('payload', 'object', TRUE, 'Complete schema-shaped definition payload to store.'),
        EditorGmToolDefinition::argument('expected_version', 'string', FALSE, 'Version the edit was planned against; rejects concurrent edits.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $family = EditorGmToolContext::requireString($arguments, 'family');
    $definition_id = EditorGmToolContext::requireString($arguments, 'definition_id');
    $payload = EditorGmToolContext::requireArray($arguments, 'payload');
    $expected_version = isset($arguments['expected_version'])
      ? EditorGmToolContext::requireString($arguments, 'expected_version')
      : NULL;

    $before = $context->definitions->definitionPayload($family, $definition_id);
    $result = $context->definitions->saveDefinition($family, $definition_id, $payload, $expected_version);
    $after = $context->definitions->definitionPayload($family, $definition_id);

    return $result + [
      'changed' => $before !== $after,
      'payload' => $after,
    ];
  }

}
