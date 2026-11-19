<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Generates a preview-only canonical actor definition proposal: an NPC or a creature. */
final class GenerateNpcDefinitionTool implements EditorGmToolInterface {

  public function __construct(private readonly EditorDefinitionGenerationService $generation) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'generate_npc_definition',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Generate an original canonical actor payload proposal for create_definition: an NPC, or a creature when class=creature.',
      FALSE,
      'propose only; validate_definition/create_definition approval required',
      [
        EditorGmToolDefinition::argument('prompt', 'string', TRUE, 'Original NPC or creature request, 1..2000 chars.'),
        EditorGmToolDefinition::argument('definition_id', 'string', FALSE, 'Required canonical id when fulfilling an existing reference.'),
        EditorGmToolDefinition::argument('level', 'integer', FALSE, 'Level -1..30, default 1.'),
        EditorGmToolDefinition::argument('class', 'string', FALSE, 'Actor class. Use creature for a creature stat block; defaults to the role.'),
        EditorGmToolDefinition::argument('species', 'string', FALSE, 'Kind of creature when class=creature (animal|undead|fiend|...), default animal.'),
        EditorGmToolDefinition::argument('rarity', 'string', FALSE, 'common|uncommon|rare|unique when class=creature, default common.'),
        EditorGmToolDefinition::argument('role', 'string', FALSE, 'Optional role such as merchant|guard|contact|villain|resident, or skirmisher|guardian|leader for a creature.'),
        EditorGmToolDefinition::argument('attitude', 'string', FALSE, 'hostile|unfriendly|indifferent|friendly|helpful.'),
        EditorGmToolDefinition::argument('seed', 'integer', FALSE, 'Optional deterministic seed 0..2147483647.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    return $this->generation->generateNpcDefinition($arguments, DefinitionEditorGmToolContext::of($context));
  }

}
