<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Planning tool: proposes a canonical definition patch without saving it.
 *
 * update_canonical_definition intentionally requires a complete payload. This
 * tool builds that complete payload from the current entry plus a requested
 * patch, and reports exactly which fields would change, so a definition edit
 * can be reviewed before it is committed. Nothing is written.
 */
final class PlanCanonicalDefinitionPatchTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_canonical_definition_patch',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Merge a requested patch into a canonical definition and report the resulting update_canonical_definition arguments.',
      FALSE,
      'planning only; execution requires update_canonical_definition',
      [
        EditorGmToolDefinition::argument('family', 'string', TRUE, 'Placeable family.'),
        EditorGmToolDefinition::argument('definition_id', 'string', TRUE, 'Canonical definition id.'),
        EditorGmToolDefinition::argument('patch', 'object', TRUE, 'Attribute keys to add or replace.'),
        EditorGmToolDefinition::argument('name', 'string', FALSE, 'Replacement display name.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $family = EditorGmToolContext::requireString($arguments, 'family');
    $definition_id = EditorGmToolContext::requireString($arguments, 'definition_id');
    $patch = EditorGmToolContext::requireArray($arguments, 'patch');
    if ($patch === [] && !isset($arguments['name'])) {
      throw new \InvalidArgumentException('definition_patch_empty');
    }

    $entry = $context->roomEditor->loadCanonicalEntry($family, $definition_id);
    $current_attributes = is_array($entry['schema_data'] ?? NULL) ? $entry['schema_data'] : [];
    $current_name = (string) ($entry['name'] ?? '');
    $proposed_name = isset($arguments['name']) ? EditorGmToolContext::requireString($arguments, 'name') : $current_name;
    $proposed_attributes = array_replace($current_attributes, $patch);

    $field_changes = [];
    foreach ($patch as $key => $value) {
      $before = $current_attributes[$key] ?? NULL;
      if ($before !== $value) {
        $field_changes[] = [
          'field' => (string) $key,
          'before' => $before,
          'after' => $value,
          'is_new_field' => !array_key_exists($key, $current_attributes),
        ];
      }
    }

    return [
      'family' => $family,
      'definition_id' => $definition_id,
      'name_changes' => $proposed_name === $current_name
        ? NULL
        : ['before' => $current_name, 'after' => $proposed_name],
      'field_changes' => $field_changes,
      'has_changes' => $field_changes !== [] || $proposed_name !== $current_name,
      'proposed_execution' => [
        'tool_name' => 'update_canonical_definition',
        'arguments' => [
          'family' => $family,
          'definition_id' => $definition_id,
          'name' => $proposed_name,
          'schema_data' => $proposed_attributes,
        ],
      ],
    ];
  }

}
