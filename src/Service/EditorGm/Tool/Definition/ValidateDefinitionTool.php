<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Validation tool: validates a candidate payload without saving. */
final class ValidateDefinitionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'validate_definition',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Validate a complete definition payload against the family schema.',
      FALSE,
      'CanonicalDefinitionService::validateDefinition()',
      [
        EditorGmToolDefinition::argument('family', 'string', FALSE, 'Definition family; defaults to the page scope.'),
        EditorGmToolDefinition::argument('definition_id', 'string', FALSE, 'Definition id when blast-radius preview is wanted.'),
        EditorGmToolDefinition::argument('payload', 'object', TRUE, 'Complete schema-shaped definition payload.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DefinitionEditorGmToolContext::of($context);
    $family = $context->scopedFamily($arguments);
    $payload = EditorGmToolContext::requireArray($arguments, 'payload');
    $findings = $context->definitions->validateDefinition($family, $payload);
    return [
      'family' => $family,
      'valid' => $findings === [],
      'findings' => $findings,
      'affected_published_rooms' => isset($arguments['definition_id'])
        ? $context->definitions->publishedRoomsReferencing($family, $context->scopedDefinitionId($arguments))
        : [],
    ];
  }

}
