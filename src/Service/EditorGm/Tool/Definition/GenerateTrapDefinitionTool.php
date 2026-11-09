<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Generates a preview-only trap definition proposal. */
final class GenerateTrapDefinitionTool implements EditorGmToolInterface {

  public function __construct(private readonly EditorDefinitionGenerationService $generation) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'generate_trap_definition',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Generate an original trap definition payload proposal for create_definition.',
      FALSE,
      'propose only; validate_definition/create_definition approval required',
      [
        EditorGmToolDefinition::argument('prompt', 'string', TRUE, 'Original trap request (e.g. poison dart glyph, swinging scythe, spiked pit), 1..2000 chars.'),
        EditorGmToolDefinition::argument('definition_id', 'string', FALSE, 'Optional canonical trap_id when fulfilling an existing reference.'),
        EditorGmToolDefinition::argument('level', 'integer', FALSE, 'Trap level -1..25, default 1.'),
        EditorGmToolDefinition::argument('type', 'string', FALSE, 'mechanical|magical|haunt|environmental, default mechanical.'),
        EditorGmToolDefinition::argument('complexity', 'string', FALSE, 'simple|complex, default simple.'),
        EditorGmToolDefinition::argument('rarity', 'string', FALSE, 'common|uncommon|rare|unique, default common.'),
        EditorGmToolDefinition::argument('seed', 'integer', FALSE, 'Optional deterministic seed 0..2147483647.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    return $this->generation->generateTrapDefinition($arguments, DefinitionEditorGmToolContext::of($context));
  }

}
