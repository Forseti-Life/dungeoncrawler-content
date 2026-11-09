<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorDefinitionGenerationService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Generates a preview-only obstacle definition proposal. */
final class GenerateObstacleDefinitionTool implements EditorGmToolInterface {

  public function __construct(private readonly EditorDefinitionGenerationService $generation) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'generate_obstacle_definition',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Generate an original obstacle/furniture/terrain-object definition payload proposal for create_definition.',
      FALSE,
      'propose only; validate_definition/create_definition approval required',
      [
        EditorGmToolDefinition::argument('prompt', 'string', TRUE, 'Original obstacle/object request (e.g. banquet table, chair, lava pit, portcullis), 1..2000 chars.'),
        EditorGmToolDefinition::argument('definition_id', 'string', FALSE, 'Optional canonical object_id when fulfilling an existing reference.'),
        EditorGmToolDefinition::argument('category', 'string', FALSE, 'bar|table|stool|crate|door|decor|wall|custom, default decor.'),
        EditorGmToolDefinition::argument('size', 'string', FALSE, 'tiny|small|medium|large|huge|gargantuan, default medium.'),
        EditorGmToolDefinition::argument('movable', 'boolean', FALSE, 'Whether the obstacle can be moved, default false.'),
        EditorGmToolDefinition::argument('stackable', 'boolean', FALSE, 'Whether items/objects can stack on top, default false.'),
        EditorGmToolDefinition::argument('blocks_movement', 'boolean', FALSE, 'Whether the obstacle blocks movement across the hex, default true.'),
        EditorGmToolDefinition::argument('seed', 'integer', FALSE, 'Optional deterministic seed 0..2147483647.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    return $this->generation->generateObstacleDefinition($arguments, DefinitionEditorGmToolContext::of($context));
  }

}
