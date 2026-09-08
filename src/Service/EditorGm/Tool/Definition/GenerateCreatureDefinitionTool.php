<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Generates a preview-only creature definition proposal. */
final class GenerateCreatureDefinitionTool implements EditorGmToolInterface {

  public function __construct(private readonly EditorDefinitionGenerationService $generation) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'generate_creature_definition',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Generate an original creature definition payload proposal for create_definition.',
      FALSE,
      'propose only; validate_definition/create_definition approval required',
      [
        EditorGmToolDefinition::argument('prompt', 'string', TRUE, 'Original creature request, 1..2000 chars.'),
        EditorGmToolDefinition::argument('level', 'integer', FALSE, 'Creature level -1..25, default 1.'),
        EditorGmToolDefinition::argument('role', 'string', FALSE, 'Optional role such as skirmisher|guardian|leader.'),
        EditorGmToolDefinition::argument('creature_type', 'string', FALSE, 'Canonical creature type, default animal.'),
        EditorGmToolDefinition::argument('rarity', 'string', FALSE, 'common|uncommon|rare|unique, default common.'),
        EditorGmToolDefinition::argument('seed', 'integer', FALSE, 'Optional deterministic seed 0..2147483647.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    return $this->generation->generateCreatureDefinition($arguments, DefinitionEditorGmToolContext::of($context));
  }

}
