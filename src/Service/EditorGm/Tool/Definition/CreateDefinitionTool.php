<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Execution tool: creates a new canonical definition. */
final class CreateDefinitionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'create_definition',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Create an approved, schema-validated canonical definition.',
      TRUE,
      'CanonicalDefinitionService::saveDefinition()',
      [
        EditorGmToolDefinition::argument('family', 'string', FALSE, 'Definition family; defaults to the page scope.'),
        EditorGmToolDefinition::argument('payload', 'object', TRUE, 'Complete schema-shaped definition payload. The id comes from the family id property.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DefinitionEditorGmToolContext::of($context);
    $family = $context->scopedFamily($arguments);
    $payload = EditorGmToolContext::requireArray($arguments, 'payload');
    $result = $context->definitions->saveDefinition($family, NULL, $payload, NULL);
    $context->invalidate();
    return $result + [
      'payload' => $context->definitions->definitionPayload($family, $result['definition_id']),
      'blast_radius' => $result['affected_rooms'],
    ];
  }

}
