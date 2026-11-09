<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Planning tool: generates a canonical definition proposal for obstacle, hazard, trap, item, creature, or actor.
 */
final class GenerateCanonicalDefinitionTool implements EditorGmToolInterface {

  public function __construct(private readonly EditorCanonicalGenerationPlanService $generation) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'generate_canonical_definition',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Generate an original canonical definition payload proposal for obstacle, hazard, trap, item, creature, or actor.',
      FALSE,
      'propose only; validate_canonical_definition/create_canonical_definition approval required',
      [
        EditorGmToolDefinition::argument('family', 'string', TRUE, 'Placeable family: obstacle|hazard|trap|item|creature|actor.'),
        EditorGmToolDefinition::argument('prompt', 'string', TRUE, 'Original definition request (e.g. oak banquet table, lava fissure hazard, poison dart trap), 1..2000 chars.'),
        EditorGmToolDefinition::argument('definition_id', 'string', FALSE, 'Optional canonical id slug.'),
        EditorGmToolDefinition::argument('level', 'integer', FALSE, 'Optional level (-1..30 depending on family).'),
        EditorGmToolDefinition::argument('rarity', 'string', FALSE, 'Optional rarity (common|uncommon|rare|unique|epic|legendary).'),
        EditorGmToolDefinition::argument('category', 'string', FALSE, 'Obstacle category: bar|table|stool|crate|door|decor|wall|custom.'),
        EditorGmToolDefinition::argument('size', 'string', FALSE, 'Size: tiny|small|medium|large|huge|gargantuan.'),
        EditorGmToolDefinition::argument('complexity', 'string', FALSE, 'Hazard/trap complexity: simple|complex.'),
        EditorGmToolDefinition::argument('type', 'string', FALSE, 'Trap type: mechanical|magical|haunt|environmental.'),
        EditorGmToolDefinition::argument('item_type', 'string', FALSE, 'Item type when family=item.'),
        EditorGmToolDefinition::argument('creature_type', 'string', FALSE, 'Creature type when family=creature.'),
        EditorGmToolDefinition::argument('role', 'string', FALSE, 'Creature/actor role when family=creature|actor.'),
        EditorGmToolDefinition::argument('attitude', 'string', FALSE, 'Actor attitude when family=actor.'),
        EditorGmToolDefinition::argument('seed', 'integer', FALSE, 'Optional deterministic seed 0..2147483647.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    return $this->generation->generateCanonicalDefinition($arguments, $context);
  }

}
