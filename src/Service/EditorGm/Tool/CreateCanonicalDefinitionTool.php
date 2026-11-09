<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Definition tool: persists a new schema-validated canonical object definition.
 */
final class CreateCanonicalDefinitionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'create_canonical_definition',
      EditorGmToolDefinition::FAMILY_DEFINITION,
      'Create and persist a new schema-validated canonical definition (obstacle, hazard, trap, item, creature, or actor) in the canonical library.',
      TRUE,
      'CanonicalDefinitionService::saveDefinition()',
      [
        EditorGmToolDefinition::argument('family', 'string', TRUE, 'Placeable family: obstacle|hazard|trap|item|creature|actor.'),
        EditorGmToolDefinition::argument('payload', 'object', TRUE, 'Complete schema-shaped definition payload to store.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $family = EditorGmToolContext::requireString($arguments, 'family');
    $payload = EditorGmToolContext::requireArray($arguments, 'payload');
    $result = $context->definitions->saveDefinition($family, NULL, $payload, NULL);
    $after = $context->definitions->definitionPayload($family, $result['definition_id']);
    return $result + [
      'payload' => $after,
      'blast_radius' => $result['affected_rooms'],
    ];
  }

}
