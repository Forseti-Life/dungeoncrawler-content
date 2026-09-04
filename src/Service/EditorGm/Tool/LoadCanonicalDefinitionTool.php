<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Definition tool: loads the raw editable canonical object record.
 *
 * This is the harness equivalent of the definition editor link-out, so the
 * assistant can reach object-level authority without leaving the editor. The
 * `payload` key is the schema-shaped payload that plan_canonical_definition_patch
 * and update_canonical_definition operate on.
 */
final class LoadCanonicalDefinitionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'load_canonical_definition',
      EditorGmToolDefinition::FAMILY_DEFINITION,
      'Load the full editable canonical definition record behind a placeable object.',
      FALSE,
      'CanonicalDefinitionService::definitionPayload()',
      [
        EditorGmToolDefinition::argument('family', 'string', TRUE, 'Placeable family.'),
        EditorGmToolDefinition::argument('definition_id', 'string', TRUE, 'Canonical definition id.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $family = EditorGmToolContext::requireString($arguments, 'family');
    $definition_id = EditorGmToolContext::requireString($arguments, 'definition_id');
    return [
      'entry' => $context->definitions->loadCanonicalEntry($family, $definition_id),
      'payload' => $context->definitions->definitionPayload($family, $definition_id),
    ];
  }

}
