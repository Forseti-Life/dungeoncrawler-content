<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Planning tool: merges a patch and reports the exact proposed save. */
final class PlanDefinitionPatchTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_definition_patch',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Merge a top-level patch into the current payload and prepare an update_definition call.',
      FALSE,
      'planning only; execution requires update_definition',
      [
        EditorGmToolDefinition::argument('family', 'string', FALSE, 'Definition family; defaults to the page scope.'),
        EditorGmToolDefinition::argument('definition_id', 'string', FALSE, 'Definition id; defaults to the page scope on edit pages.'),
        EditorGmToolDefinition::argument('patch', 'object', TRUE, 'Top-level payload keys to add or replace.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DefinitionEditorGmToolContext::of($context);
    $family = $context->scopedFamily($arguments);
    $definition_id = $context->scopedDefinitionId($arguments);
    $patch = EditorGmToolContext::requireArray($arguments, 'patch');
    if ($patch === []) {
      throw new \InvalidArgumentException('definition_patch_empty');
    }
    $current = $context->definitions->definitionPayload($family, $definition_id);
    $proposed = array_replace($current, $patch);
    $field_changes = [];
    foreach ($patch as $key => $value) {
      $before = $current[$key] ?? NULL;
      if ($before !== $value) {
        $field_changes[] = [
          'field' => (string) $key,
          'before' => $before,
          'after' => $value,
          'is_new_field' => !array_key_exists($key, $current),
        ];
      }
    }
    $findings = $context->definitions->validateDefinition($family, $proposed);
    $affected_rooms = $context->definitions->publishedRoomsReferencing($family, $definition_id);
    $current_version = $context->definitions->currentVersion($family, $definition_id);
    return [
      'family' => $family,
      'definition_id' => $definition_id,
      'field_changes' => $field_changes,
      'has_changes' => $field_changes !== [],
      'valid' => $findings === [],
      'findings' => $findings,
      'current_version' => $current_version,
      'affected_published_rooms' => $affected_rooms,
      'version_after_save' => $affected_rooms === [] || $current_version === NULL
        ? $current_version
        : $context->definitions->incrementPatch($context->definitions->normalizeSemanticVersion($current_version)),
      'proposed_execution' => $findings === [] ? [
        'tool_name' => 'update_definition',
        'authority' => 'CanonicalDefinitionService::saveDefinition()',
        'arguments' => [
          'family' => $family,
          'definition_id' => $definition_id,
          'payload' => $proposed,
          'expected_version' => $current_version,
        ],
      ] : NULL,
    ];
  }

}
