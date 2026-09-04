<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Definition tool: reports where a definition is placed in the active draft.
 *
 * Answers "is it safe to change this object?" before an edit is approved.
 */
final class SummarizeDefinitionUsageTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'summarize_definition_usage',
      EditorGmToolDefinition::FAMILY_DEFINITION,
      'List placements in the active draft that reference one canonical definition.',
      FALSE,
      'draft room payload',
      [
        EditorGmToolDefinition::argument('family', 'string', TRUE, 'Placeable family.'),
        EditorGmToolDefinition::argument('definition_id', 'string', TRUE, 'Canonical definition id.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $family = EditorGmToolContext::requireString($arguments, 'family');
    $definition_id = EditorGmToolContext::requireString($arguments, 'definition_id');

    $usages = [];
    foreach ((array) ($context->room()['placements'] ?? []) as $placement) {
      if (!is_array($placement)) {
        continue;
      }
      $ref = is_array($placement['definition_ref'] ?? NULL) ? $placement['definition_ref'] : [];
      if ((string) ($ref['family'] ?? '') !== $family
        || (string) ($ref['definition_id'] ?? '') !== $definition_id) {
        continue;
      }
      $usages[] = [
        'instance_id' => (string) ($placement['instance_id'] ?? ''),
        'anchor_hex' => $placement['anchor_hex'] ?? NULL,
        'facing' => $placement['facing'] ?? NULL,
        'has_overrides' => !empty($placement['overrides']),
      ];
    }

    return [
      'family' => $family,
      'definition_id' => $definition_id,
      'usage_count' => count($usages),
      'usages' => $usages,
    ];
  }

}
