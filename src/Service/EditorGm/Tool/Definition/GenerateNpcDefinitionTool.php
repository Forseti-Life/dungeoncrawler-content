<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Generates a preview-only canonical actor/NPC definition proposal. */
final class GenerateNpcDefinitionTool implements EditorGmToolInterface {

  public function __construct(private readonly EditorDefinitionGenerationService $generation) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'generate_npc_definition',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Generate an original canonical actor NPC payload proposal for create_definition.',
      FALSE,
      'propose only; validate_definition/create_definition approval required',
      [
        EditorGmToolDefinition::argument('prompt', 'string', TRUE, 'Original NPC request, 1..2000 chars.'),
        EditorGmToolDefinition::argument('level', 'integer', FALSE, 'NPC level -1..30, default 1.'),
        EditorGmToolDefinition::argument('role', 'string', FALSE, 'Optional role such as merchant|guard|contact|villain|resident.'),
        EditorGmToolDefinition::argument('attitude', 'string', FALSE, 'hostile|unfriendly|indifferent|friendly|helpful.'),
        EditorGmToolDefinition::argument('seed', 'integer', FALSE, 'Optional deterministic seed 0..2147483647.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    return $this->generation->generateNpcDefinition($arguments, DefinitionEditorGmToolContext::of($context));
  }

}
