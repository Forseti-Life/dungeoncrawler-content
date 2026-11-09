<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Validation tool: validates a canonical definition payload without saving.
 */
final class ValidateCanonicalDefinitionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'validate_canonical_definition',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Validate a complete canonical definition payload against its family schema (obstacle, hazard, trap, item, creature, actor) without saving.',
      FALSE,
      'CanonicalDefinitionService::validateDefinition()',
      [
        EditorGmToolDefinition::argument('family', 'string', TRUE, 'Placeable family: obstacle|hazard|trap|item|creature|actor.'),
        EditorGmToolDefinition::argument('payload', 'object', TRUE, 'Complete schema-shaped definition payload.'),
        EditorGmToolDefinition::argument('definition_id', 'string', FALSE, 'Optional existing definition id when blast-radius preview is wanted.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $family = EditorGmToolContext::requireString($arguments, 'family');
    $payload = EditorGmToolContext::requireArray($arguments, 'payload');
    $findings = $context->definitions->validateDefinition($family, $payload);
    return [
      'family' => $family,
      'valid' => $findings === [],
      'findings' => $findings,
      'affected_published_rooms' => isset($arguments['definition_id']) && trim((string) $arguments['definition_id']) !== ''
        ? $context->definitions->publishedRoomsReferencing($family, EditorGmToolContext::requireString($arguments, 'definition_id'))
        : [],
    ];
  }

}
