<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Execution tool: saves an approved canonical definition update. */
final class UpdateDefinitionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'update_definition',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      'Persist an approved validated edit to one canonical definition.',
      TRUE,
      'CanonicalDefinitionService::saveDefinition()',
      [
        EditorGmToolDefinition::argument('family', 'string', FALSE, 'Definition family; defaults to the page scope.'),
        EditorGmToolDefinition::argument('definition_id', 'string', FALSE, 'Definition id; defaults to the page scope on edit pages.'),
        EditorGmToolDefinition::argument('payload', 'object', TRUE, 'Complete schema-shaped definition payload to store.'),
        EditorGmToolDefinition::argument('expected_version', 'string', FALSE, 'Version the edit was planned against; rejects concurrent edits.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DefinitionEditorGmToolContext::of($context);
    $family = $context->scopedFamily($arguments);
    $definition_id = $context->scopedDefinitionId($arguments);
    $payload = EditorGmToolContext::requireArray($arguments, 'payload');
    $expected_version = isset($arguments['expected_version'])
      ? EditorGmToolContext::requireString($arguments, 'expected_version')
      : NULL;
    $before = $context->definitions->definitionPayload($family, $definition_id);
    $result = $context->definitions->saveDefinition($family, $definition_id, $payload, $expected_version);
    $context->invalidate();
    $after = $context->definitions->definitionPayload($family, $definition_id);
    return $result + [
      'changed' => $before !== $after,
      'payload' => $after,
      'blast_radius' => $result['affected_rooms'],
    ];
  }

}
