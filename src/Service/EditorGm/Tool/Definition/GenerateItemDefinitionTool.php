<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Generates a preview-only item definition proposal. */
final class GenerateItemDefinitionTool implements EditorGmToolInterface {

  public function __construct(private readonly EditorDefinitionGenerationService $generation) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'generate_item_definition',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Generate an original item definition payload proposal for create_definition.',
      FALSE,
      'propose only; validate_definition/create_definition approval required',
      [
        EditorGmToolDefinition::argument('prompt', 'string', TRUE, 'Original item request, 1..2000 chars.'),
        EditorGmToolDefinition::argument('level', 'integer', FALSE, 'Item level 0..25, default 1.'),
        EditorGmToolDefinition::argument('rarity', 'string', FALSE, 'common|uncommon|rare|epic|legendary, default common.'),
        EditorGmToolDefinition::argument('item_type', 'string', FALSE, 'Canonical item type, default held_item.'),
        EditorGmToolDefinition::argument('seed', 'integer', FALSE, 'Optional deterministic seed 0..2147483647.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    return $this->generation->generateItemDefinition($arguments, DefinitionEditorGmToolContext::of($context));
  }

}
