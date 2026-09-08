<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGenerationService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;

/**
 * Generates a preview-only Room Editor command plan.
 */
final class GenerateRoomLayoutTool implements EditorGmToolInterface {

  public function __construct(private readonly EditorGenerationService $generation) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'generate_room_layout',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Generate an original room layout as a validating command_plan proposal; writes nothing until apply_room_commands is approved.',
      FALSE,
      'propose only; execution requires preview_command_plan/apply_room_commands through RoomEditorService',
      [
        EditorGmToolDefinition::argument('prompt', 'string', TRUE, 'Original room request, 1..2000 chars.'),
        EditorGmToolDefinition::argument('theme', 'string', FALSE, 'Optional theme, 0..100 chars.'),
        EditorGmToolDefinition::argument('size_category', 'string', FALSE, 'tiny|small|medium|large|huge|gargantuan, default medium.'),
        EditorGmToolDefinition::argument('room_type', 'string', FALSE, 'Canonical room type, default chamber.'),
        EditorGmToolDefinition::argument('level', 'integer', FALSE, 'Encounter level -1..25, default 1.'),
        EditorGmToolDefinition::argument('min_hexes', 'integer', FALSE, 'Minimum generated hexes, default 16.'),
        EditorGmToolDefinition::argument('max_hexes', 'integer', FALSE, 'Maximum generated hexes, hard cap 80.'),
        EditorGmToolDefinition::argument('placeable_families', 'array', FALSE, 'Optional list of creature|actor|item|obstacle|trap|hazard.'),
        EditorGmToolDefinition::argument('seed', 'integer', FALSE, 'Optional deterministic seed 0..2147483647.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = RoomEditorGmToolContext::of($context);
    return $this->generation->generateRoomLayout($arguments, $context);
  }

}
