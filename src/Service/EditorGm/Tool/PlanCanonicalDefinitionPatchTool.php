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
        EditorGmToolDefinition::argument('patch', 'object', TRUE, 'Top-level payload keys to add or replace.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $family = EditorGmToolContext::requireString($arguments, 'family');
    $definition_id = EditorGmToolContext::requireString($arguments, 'definition_id');
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
        'tool_name' => 'update_canonical_definition',
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
